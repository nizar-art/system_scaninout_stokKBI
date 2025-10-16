<?php

namespace App\Imports;

use App\Models\Bom;
use App\Models\Forecast;
use App\Models\Part;
use App\Models\WorkDays;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BomImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    private $errors = [];
    private $successCount = 0;
    private $updatedCount = 0;
    private $partsCache = null;
    private $workDaysCache = null;

    public function collection(Collection $rows)
    {
        // Cache data untuk performa
        $this->cacheParts();
        $this->cacheWorkDays();

        // Proses dalam chunk
        $rows->chunk(500)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $affectedProducts = [];

                foreach ($chunk as $index => $row) {
                    $result = $this->processRow($row, $index + 2);
                    if ($result && $result['product_id']) {
                        $affectedProducts[$result['product_id']] = true;
                    }
                }

                // Update forecast untuk Product 5 dan turunannya yang terpengaruh
                $this->updateProduct5Forecasts($affectedProducts);
            });
        });
    }

    protected function cacheParts()
    {
        if ($this->partsCache === null) {
            $this->partsCache = Part::all()->keyBy('Inv_id');
        }
    }

    protected function cacheWorkDays()
    {
        if ($this->workDaysCache === null) {
            $this->workDaysCache = WorkDays::all()->keyBy(function ($wd) {
                return Carbon::parse($wd->month)->format('Y-m');
            });
        }
    }

    protected function processRow($row, $rowNumber)
    {
        if ($row->filter()->isEmpty()) {
            $this->errors[] = [
                'row' => $rowNumber,
                'errors' => ['Baris kosong - tidak diproses'],
                'data' => []
            ];
            return null;
        }

        $data = [
            'product_code' => $row['product_code'] ?? $row['product_code'] ?? $row['kode_produk'] ?? null,
            'component_code' => $row['component_code'] ?? $row['component_code'] ?? $row['kode_komponen'] ?? null,
            'quantity' => $row['quantity'] ?? $row['qty'] ?? $row['kuantitas'] ?? null,
            'unit' => $row['unit'] ?? $row['satuan'] ?? 'pcs',
        ];

        $validator = $this->validateRow($data, $rowNumber);

        if ($validator->fails()) {
            $this->errors[] = [
                'row' => $rowNumber,
                'errors' => $validator->errors()->all(),
                'data' => $data
            ];
            return null;
        }

        return $this->processBom($data, $rowNumber);
    }

    protected function validateRow($data, $rowNumber)
    {
        return Validator::make($data, [
            'product_code' => [
                'required',
                function ($attribute, $value, $fail) use ($rowNumber) {
                    if (empty($value)) {
                        $fail("Baris $rowNumber: Kode produk wajib diisi");
                    } elseif (!isset($this->partsCache[$value])) {
                        $fail("Baris $rowNumber: Kode produk '$value' tidak ditemukan");
                    }
                }
            ],
            'component_code' => [
                'required',
                function ($attribute, $value, $fail) use ($data, $rowNumber) {
                    if (empty($value)) {
                        $fail("Baris $rowNumber: Kode komponen wajib diisi");
                    } elseif (!isset($this->partsCache[$value])) {
                        $fail("Baris $rowNumber: Kode komponen '$value' tidak ditemukan");
                    } elseif ($value === $data['product_code']) {
                        $fail("Baris $rowNumber: Produk tidak boleh menjadi komponen dirinya sendiri");
                    }
                }
            ],
            'quantity' => [
                'required',
                function ($attribute, $value, $fail) use ($rowNumber) {
                    if (empty($value)) {
                        $fail("Baris $rowNumber: Quantity wajib diisi");
                        return;
                    }

                    // Normalize value - replace comma with dot dan trim whitespace
                    $normalizedValue = str_replace(',', '.', trim($value));

                    if (!is_numeric($normalizedValue)) {
                        $fail("Baris $rowNumber: Quantity harus berupa angka");
                        return;
                    }

                    $numericValue = (double) $normalizedValue;

                    if ($numericValue <= 0) {
                        $fail("Baris $rowNumber: Quantity harus lebih besar dari 0");
                        return;
                    }
                }
            ],
            // 'unit' => [
            //     'required',
            //     'string',
            //     function ($attribute, $value, $fail) use ($rowNumber) {
            //         $allowedUnits = ['pcs', 'kg', 'g', 'm', 'cm', 'l', 'ml'];
            //         if (!in_array(strtolower($value), $allowedUnits)) {
            //             $fail("Baris $rowNumber: Unit '$value' tidak valid. Gunakan salah satu dari: " . implode(', ', $allowedUnits));
            //         }
            //     }
            // ],
        ]);
    }

    protected function processBom($data, $rowNumber)
    {
        try {
            $product = $this->partsCache[$data['product_code']];
            $component = $this->partsCache[$data['component_code']];

            // Normalize quantity - simpan sebagai double sesuai dengan data Excel
            $quantityStr = str_replace(',', '.', trim($data['quantity']));
            $quantity = (double) $quantityStr; // Tidak perlu round, simpan sesuai data asli

            $unit = strtolower($data['unit']);

            $bom = Bom::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'component_id' => $component->id
                ],
                [
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'updated_at' => Carbon::now()
                ]
            );

            $bom->wasRecentlyCreated ? $this->successCount++ : $this->updatedCount++;

            return [
                'product_id' => $product->id,
                'component_id' => $component->id,
                'is_product5' => ($product->Inv_id === '5')
            ];
        } catch (\Exception $e) {
            $this->errors[] = [
                'row' => $rowNumber,
                'errors' => ["Baris $rowNumber: Terjadi kesalahan sistem - " . $e->getMessage()],
                'data' => $data
            ];
            return null;
        }
    }

    protected function updateProduct5Forecasts($affectedProducts)
    {
        // Cari semua Product 5 yang terpengaruh
        $product5Ids = Part::whereIn('id', array_keys($affectedProducts))
                        ->where('Inv_id', '5')
                        ->pluck('id');

        if ($product5Ids->isEmpty()) {
            return;
        }

        // Proses update forecast untuk setiap Product 5
        foreach ($product5Ids as $productId) {
            $this->updateForecastForProduct($productId);
        }
    }

    protected function updateForecastForProduct($productId)
    {
        $forecasts = Forecast::where('id_part', $productId)
                        ->where('is_component', false)
                        ->get();

        foreach ($forecasts as $forecast) {
            // Hitung ulang min dan max - gunakan frequensi_delivery jika ada, fallback ke hari_kerja
            $divisor = ($forecast->frequensi_delivery && $forecast->frequensi_delivery > 0)
                ? $forecast->frequensi_delivery
                : $forecast->hari_kerja;
            $min = (int) ceil($forecast->PO_pcs / max($divisor, 1));
            $max = $min * 3;

            // Khusus Product 5
            if ($forecast->is_product5_hierarchy) {
                $min = $min * 3;
                $max = $min * 3;
            }

            // Update forecast utama
            $forecast->update([
                'min' => $min,
                'max' => $max
            ]);

            // Update semua komponen turunannya
            $this->updateComponentForecasts($forecast);
        }
    }

    protected function updateComponentForecasts(Forecast $parentForecast)
    {
        $components = Bom::where('product_id', $parentForecast->id_part)
                        ->with('component')
                        ->get();

        foreach ($components as $bom) {
            if (!$bom->component) {
                continue;
            }

            // Hitung nilai komponen dengan menjaga presisi asli dari data Excel
            $bomQuantity = (double) $bom->quantity; // Gunakan nilai asli tanpa round
            $componentMin = (int) ceil($parentForecast->min * $bomQuantity);
            $componentMax = $componentMin * 3;

            // Jika dalam hierarki Product 5
            if ($parentForecast->is_product5_hierarchy) {
                $componentMin = $componentMin * 3;
                $componentMax = $componentMin * 3;
            }

            // Update forecast komponen
            Forecast::where('id_part', $bom->component_id)
                ->where('forecast_month', $parentForecast->forecast_month)
                ->where('is_component', true)
                ->where('parent_forecast_id', $parentForecast->id)
                ->update([
                    'min' => $componentMin,
                    'max' => $componentMax,
                    'bom_quantity' => $bom->quantity,
                    'bom_unit' => $bom->unit
                ]);

            // Proses sub-komponen secara rekursif
            $componentForecast = Forecast::where('id_part', $bom->component_id)
                                    ->where('forecast_month', $parentForecast->forecast_month)
                                    ->first();

            if ($componentForecast) {
                $this->updateComponentForecasts($componentForecast);
            }
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getUpdatedCount()
    {
        return $this->updatedCount;
    }

    public function getTotalProcessed()
    {
        return $this->successCount + $this->updatedCount + count($this->errors);
    }

    public function headingRow(): int
    {
        return 1;
    }
}
