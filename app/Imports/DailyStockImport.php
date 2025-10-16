<?php

namespace App\Imports;

use App\Models\DailyStockLog;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\Forecast;
use App\Models\HeadArea;
use App\Models\Plant;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DailyStockImport implements ToCollection, WithChunkReading, WithBatchInserts
{
    private $importedCount = 0;
    private $skippedRows = [];
    private $existingData = [];
    private $previewMode = false;
    private $partsCache = [];
    private $forecastsCache = [];
    private $inventoriesCache = [];
    private $headAreasCache = [];
    private $plantsCache = [];
    private $plantsNameCache = [];
    private $duplicateAction = 'skip'; // 'skip' atau 'overwrite'

    protected $statusUpdatedRows = [];
    protected $statusUpdatedCount = 0;

    public function setDuplicateAction(string $action): void
    {
        $this->duplicateAction = $action;
    }

    public function setPreviewMode(bool $mode): self
    {
        $this->previewMode = $mode;
        return $this;
    }

    public function getExistingData(): array
    {
        return $this->existingData;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }

    public function getStatusUpdatedRows()
    {
        return $this->statusUpdatedRows;
    }
    public function getStatusUpdatedCount()
    {
        return $this->statusUpdatedCount;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 250;
    }

    public function collection(Collection $rows)
    {
        $this->cacheData();

        // Deteksi header dinamis
        $headerRowIndex = null;
        $headerKeys = null;
        foreach ($rows as $i => $row) {
            $lowerRow = array_map(function($v) { return strtolower(trim($v)); }, $row->toArray());
            if (in_array('date', $lowerRow) && in_array('inv_id', $lowerRow)) {
                $headerRowIndex = $i;
                $headerKeys = $lowerRow;
                break;
            }
        }
        if ($headerRowIndex === null) {
            throw new Exception('Header kolom tidak ditemukan (wajib ada: date, inv_id, dll)');
        }

        // Proses data mulai setelah header
        foreach ($rows as $i => $row) {
            if ($i <= $headerRowIndex) continue;
            $assocRow = array_combine($headerKeys, $row->toArray());
            $this->processRow($assocRow);
        }
    }

    protected function cacheData(): void
    {
        $this->cachePlants();
        $this->cacheParts();
        $this->cacheForecasts();
        $this->cacheInventories();
        $this->cacheHeadAreas();
    }

    protected function cachePlants(): void
    {
        Plant::chunk(1000, function ($plants) {
            foreach ($plants as $plant) {
                $lowerName = strtolower(trim($plant->name));
                $this->plantsCache[$plant->id] = $plant;
                $this->plantsNameCache[$lowerName] = $plant->id;
            }
        });
    }

    protected function cacheParts(): void
    {
        Part::chunk(1000, function ($parts) {
            foreach ($parts as $part) {
                $this->partsCache[$part->Inv_id] = $part;
            }
        });
    }

    protected function cacheForecasts(): void
    {
        Forecast::where('forecast_month', '>=', now()->subMonth())
            ->chunk(1000, function ($forecasts) {
                foreach ($forecasts as $forecast) {
                    $this->forecastsCache[$forecast->id_part] = $forecast;
                }
            });
    }

    protected function cacheInventories(): void
    {
        Inventory::chunk(1000, function ($inventories) {
            foreach ($inventories as $inventory) {
                $this->inventoriesCache[$inventory->id_part] = $inventory;
            }
        });
    }

    protected function cacheHeadAreas(): void
    {
        HeadArea::with('plant')->chunk(1000, function ($headAreas) {
            foreach ($headAreas as $headArea) {
                if ($headArea->plant) {
                    $plantName = strtolower(trim($headArea->plant->name));
                    $areaName = strtolower(trim($headArea->nama_area));
                    $this->headAreasCache[$areaName . '|' . $plantName] = $headArea->id;
                }
            }
        });
    }

    protected function processRow(array $row): void
    {
        try {
            // Normalisasi nama kolom (case insensitive)
            $row = array_change_key_case($row, CASE_LOWER);

            // Skip baris yang seluruh kolomnya kosong
            if ($this->isRowEmpty($row)) {
                return;
            }

            // Validasi kolom wajib
            $requiredColumns = ['inv_id', 'plant', 'area', 'date', 'status'];
            foreach ($requiredColumns as $column) {
                if (!isset($row[$column]) || trim($row[$column]) === '') {
                    // Skip baris yang memiliki kolom wajib kosong
                    if ($column === 'status') {
                        $this->skippedRows[] = [
                            'row' => $row,
                            'reason' => "Kolom status wajib diisi"
                        ];
                    }
                    return;
                }
            }

            $invId = trim($row['inv_id']);
            $totalQty = (int) $row['qty'];
            $plantName = trim($row['plant']);
            $areaName = trim($row['area']);
            $statusRaw = strtoupper(trim($row['status']));
            $allowedStatus = ['OK', 'NG', 'VIRGIN', 'FUNSAI'];
            if (!in_array($statusRaw, $allowedStatus)) {
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Status tidak valid: {$row['status']}"
                ];
                return;
            }

            // Parse tanggal dari Excel
            $importDate = $this->parseImportDate($row['date']);

            // Validasi part
            if (!isset($this->partsCache[$invId])) {
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Part dengan ID {$invId} tidak ditemukan"
                ];
                return;
            }

            // Validasi plant
            $lowerPlantName = strtolower($plantName);
            if (!isset($this->plantsNameCache[$lowerPlantName])) {
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Plant '{$plantName}' tidak ditemukan"
                ];
                return;
            }

            // Validasi area untuk plant
            $lowerAreaName = strtolower($areaName);
            $cacheKey = $lowerAreaName . '|' . $lowerPlantName;

            if (!isset($this->headAreasCache[$cacheKey])) {
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Area '{$areaName}' tidak ditemukan untuk Plant '{$plantName}'"
                ];
                return;
            }

            $headAreaId = $this->headAreasCache[$cacheKey];
            $part = $this->partsCache[$invId];

            // Validasi quantity tidak negatif
            if ($totalQty < 0) {
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Quantity tidak boleh negatif"
                ];
                return;
            }

            // Cek data existing untuk part, area, dan tanggal yang sama
            $existingLog = DailyStockLog::where('id_inventory', $part->id)
                ->where('id_area_head', $headAreaId)
                ->whereDate('date', $importDate)
                ->first();

            if ($existingLog) {
                $currentUserId = auth()->id();

                // Jika area sama dan user berbeda, tolak impor
                if ($existingLog->prepared_by != $currentUserId) {
                    $this->skippedRows[] = [
                        'row' => $row,
                        'reason' => "Data untuk area ini sudah diinput oleh user lain - Data tidak dapat diimport"
                    ];
                    return;
                }

                // Jika status berbeda, buat record baru
                if ($existingLog->status !== $statusRaw) {
                    // Pastikan tidak ada duplikasi untuk status baru
                    $isDuplicate = DailyStockLog::where('id_inventory', $part->id)
                        ->where('id_area_head', $headAreaId)
                        ->whereDate('date', $importDate)
                        ->where('status', $statusRaw)
                        ->exists();

                    if (!$isDuplicate) {
                        $this->createNewRecord($part->id, $totalQty, $importDate, $headAreaId, $statusRaw);
                    }
                    return;
                }

                // Jika area sama dan user sama, update quantity
                if ($existingLog->Total_qty != $totalQty) {
                    $oldQty = $existingLog->Total_qty;

                    $stockPerDay = $this->calculateStockPerDay($part->id, $totalQty);

                    $existingLog->Total_qty = $totalQty;
                    $existingLog->stock_per_day = $stockPerDay;
                    $existingLog->updated_at = now();
                    $existingLog->save();

                    $this->statusUpdatedRows[] = [
                        'inv_id' => $invId,
                        'part_name' => $part->Part_name,
                        'plant' => $plantName,
                        'area' => $areaName,
                        'old_qty' => $oldQty,
                        'new_qty' => $totalQty,
                        'old_status' => $existingLog->status,
                        'new_status' => $statusRaw,
                        'import_date' => $importDate,
                        'row_data' => $row
                    ];
                    $this->statusUpdatedCount++;
                    $this->importedCount++; // Count updates as imports too
                    return;
                }

                // Jika tidak ada perubahan, anggap sebagai duplikat
                $this->existingData[] = [
                    'inv_id' => $invId,
                    'part_name' => $part->Part_name,
                    'plant' => $plantName,
                    'area' => $areaName,
                    'existing_qty' => $totalQty,
                    'import_date' => $importDate,
                    'row_data' => $row
                ];

                if ($this->previewMode) {
                    return;
                }

                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => "Data dengan part, tanggal, quantity, area, dan status yang sama sudah ada - Data tidak diimport"
                ];
                return;
            }

            // Buat record baru
            $this->createNewRecord($part->id, $totalQty, $importDate, $headAreaId, $statusRaw);

            // Update inventory data
            $this->updateInventoryWithRemark($part->id);

        } catch (\Exception $e) {
            // Hanya log error untuk baris yang memiliki data
            if (!empty($row['date']) || !empty($row['inv_id']) || !empty($row['plant']) || !empty($row['area'])) {
                Log::error("Error import: " . $e->getMessage());
                $this->skippedRows[] = [
                    'row' => $row,
                    'reason' => $e->getMessage()
                ];
            }
        }
    }

    protected function isRowEmpty(array $row): bool
    {
        $empty = true;
        foreach ($row as $value) {
            if (!empty($value) && trim($value) !== '') {
                $empty = false;
                break;
            }
        }
        return $empty;
    }

    protected function parseImportDate($dateInput): string
    {
        $today = now();
        $currentYear = $today->year;

        if (empty($dateInput) || trim($dateInput) === '') {
            throw new \Exception("Tanggal tidak boleh kosong");
        }

        try {
            // Jika sudah format Y-m-d
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
                $date = Carbon::createFromFormat('Y-m-d', $dateInput);
                $date->year = $currentYear;
            }
            // Format d-m-Y atau d/m/Y
            elseif (preg_match('/^\d{2}[-\/]{1}\d{2}[-\/]{1}\d{4}$/', $dateInput)) {
                $delimiter = strpos($dateInput, '-') !== false ? '-' : '/';
                [$day, $month, $year] = explode($delimiter, $dateInput);
                $date = Carbon::createFromFormat('d-m-Y', "$day-$month-$year");
                $date->year = $currentYear;
            }
            // Format m/d/Y atau m-d-Y
            elseif (preg_match('/^\d{1,2}[-\/]{1}\d{1,2}[-\/]{1}\d{4}$/', $dateInput)) {
                $delimiter = strpos($dateInput, '-') !== false ? '-' : '/';
                [$month, $day, $year] = explode($delimiter, $dateInput);
                $date = Carbon::createFromFormat('m-d-Y', "$month-$day-$year");
                $date->year = $currentYear;
            }
            // Excel numeric date
            elseif (is_numeric($dateInput)) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateInput);
                $date = Carbon::instance($date);
                $date->year = $currentYear;
            }
            // Fallback: Carbon parse
            else {
                $date = Carbon::parse($dateInput);
                $date->year = $currentYear;
            }
        } catch (\Exception $e) {
            // Jika parsing gagal, gunakan hari ini
            $date = $today;
        }

        // Jika bulan dan tahun beda dengan hari ini, pakai hari ini
        if ($date->format('Y-m') !== $today->format('Y-m')) {
            return $today->format('Y-m-d');
        }
        return $date->format('Y-m-d');
    }

    protected function isDuplicateRecord(int $partId, int $qty, string $date, int $headAreaId): bool
    {
        return DailyStockLog::where('id_inventory', $partId)
            ->where('id_area_head', $headAreaId)
            ->whereDate('date', $date)
            ->where('Total_qty', $qty)
            ->exists();
    }

    protected function createNewRecord(int $partId, int $totalQty, string $date, int $headAreaId, string $status): void
    {
        $stockPerDay = $this->calculateStockPerDay($partId, $totalQty);

        DailyStockLog::create([
            'id_inventory' => $partId,
            'id_area_head' => $headAreaId,
            'prepared_by' => auth()->id(),
            'Total_qty' => $totalQty,
            'stock_per_day' => $stockPerDay,
            'status' => $status,
            'date' => $date,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->importedCount++;
    }

    protected function calculateStockPerDay(int $partId, int $totalQty): float
    {
        if (!isset($this->forecastsCache[$partId])) {
            return 0.0;
        }

        $forecast = $this->forecastsCache[$partId];
        $min = $forecast->min;

        return $min > 0 ? (float) ($totalQty / $min) : 0.0;
    }
    protected function updateInventoryWithRemark(int $partId): void
    {
        if (isset($this->inventoriesCache[$partId])) {
            $inventory = $this->inventoriesCache[$partId];
            $inventoryDate = $inventory->date ? \Carbon\Carbon::parse($inventory->date) : \Carbon\Carbon::today();

            // Hitung total actual stock untuk bulan yang sama dengan inventory
            $month = $inventoryDate->format('Y-m');
            $totalActual = DailyStockLog::where('id_inventory', $partId)
                ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                ->sum('Total_qty');

            $planStock = $inventory->plan_stock ?? 0;
            $gap = $totalActual - $planStock;

            // Remark dan gap description seperti StoImport
            if ($planStock == 0 || $totalActual != $planStock) {
                $remark = 'Abnormal';
                if ($gap > 0) {
                    $gapDescription = '+' . abs($gap);
                } elseif ($gap < 0) {
                    $gapDescription = '-' . abs($gap);
                } else {
                    $gapDescription = "Tidak ada stock";
                }
            } else {
                $remark = 'Normal';
                $gapDescription = null;
            }

            $inventory->update([
                'act_stock' => $totalActual,
                'remark' => $remark,
                'note_remark' => $gapDescription,
            ]);

            $this->inventoriesCache[$partId] = $inventory->fresh();
        }
    }

    public function hasDuplicates(): bool
    {
        return !empty($this->existingData);
    }
}