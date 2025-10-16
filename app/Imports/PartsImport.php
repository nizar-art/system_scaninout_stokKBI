<?php

namespace App\Imports;

use App\Models\Part;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Plant;
use App\Models\Area;
use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PartsImport implements ToCollection, WithHeadingRow
{
    protected $logs = [];
    protected $processedKeys = [];
    protected $successCount = 0;

    public function getLogs()
    {
        return $this->logs;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function collection(Collection $rows)
    {
        $customers = Customer::all()->keyBy('username');
        $plants = Plant::all()->keyBy('name');
        $categories = Category::all()->keyBy('name');

        foreach ($rows as $index => $row) {
            $rowIndexForLog = $index + 2;

            // Validate required fields
            if (empty($row['inv_id']) || empty($row['kategori'])) {
                if (collect($row)->filter()->isEmpty()) {
                    continue;
                }
            }

            // Trim all input values
            $invId = trim($row['inv_id']);
            $customerUsername = trim($row['customer'] ?? '');
            $plantName = trim($row['plan'] ?? '');
            $categoryName = trim($row['kategori']);
            $areaName = trim($row['area'] ?? '');
            $partName = isset($row['part_name']) ? trim($row['part_name']) : null;
            $partNumber = isset($row['part_number']) && trim($row['part_number']) !== '' ? trim($row['part_number']) : null;
            $supplier = isset($row['supplier']) && trim($row['supplier']) !== '' ? trim($row['supplier']) : null;
            $subcont = isset($row['subcont']) && trim($row['subcont']) !== '' ? trim($row['subcont']) : null;

            // Skip duplicate Inv_id in same file
            if (in_array($invId, $this->processedKeys)) {
                continue;
            }

            // Handle category
            $category = $categories->get($categoryName);
            if (!$category) {
                $this->logs[] = "Baris $rowIndexForLog: Kategori '$categoryName' tidak ditemukan.";
                continue;
            }

            // Handle customer - create if not exists
            $customer = null;
            if ($customerUsername !== '') {
                $customer = $customers->get($customerUsername);

                if (!$customer) {
                    try {
                        $customer = Customer::firstOrCreate(
                            ['username' => $customerUsername],
                            ['name' => $customerUsername]
                        );
                        $customers->put($customerUsername, $customer);
                        $this->logs[] = "Baris $rowIndexForLog: Customer baru '$customerUsername' berhasil dibuat";
                    } catch (\Exception $e) {
                        $this->logs[] = "Baris $rowIndexForLog: Gagal membuat customer '$customerUsername'. Error: " . $e->getMessage();
                        continue;
                    }
                }
            }

            // Handle plant
            $plant = $plantName !== '' ? $plants->get($plantName) : null;

            // Handle area
            $area = null;
            if ($plant && $areaName !== '') {
                $area = Area::firstOrCreate(
                    ['id_plan' => $plant->id, 'nama_area' => $areaName],
                    ['id_plan' => $plant->id, 'nama_area' => $areaName]
                );
            }

            // Find or create part
            $part = Part::where('Inv_id', $invId)->first();
            if (!$part) {
                $part = new Part();
                $part->Inv_id = $invId;
            }

            // Update part data
            $part->Part_name = $partName;
            $part->Part_number = $partNumber; // This will be NULL if Excel cell is empty
            $part->id_category = $category->id;
            $part->supplier = $supplier;

            // Handle subcont with default value
            if ($subcont !== null) {
                $part->subcont = $subcont;
            } elseif (!$part->exists) {
                $part->subcont = 'KBI';
            }

            if ($customer) {
                $part->id_customer = $customer->id;
            }

            if ($plant) {
                $part->id_plan = $plant->id;
            }

            if ($area) {
                $part->id_area = $area->id;
            }

            // Save part
            try {
                $part->save();
                $this->successCount++;
                $this->processedKeys[] = $invId;
            } catch (\Exception $e) {
                $this->logs[] = "Baris $rowIndexForLog: Gagal menyimpan data part. Error: " . $e->getMessage();
                continue;
            }

            // Handle package if exists
            if (isset($row['qty_kanban'])) {
                $qty = trim($row['qty_kanban']);
                $qty = ($qty === '-' || $qty === '' || !is_numeric($qty)) ? 0 : (int) $qty;

                // Jika type_pkg ada, gunakan nilainya. Jika tidak, ambil dari database (jika package sudah ada)
                $typePkg = isset($row['type_pkg']) ? trim($row['type_pkg']) : null;

                try {
                    // Cek apakah package sudah ada
                    $existingPackage = Package::where('id_part', $part->id)->first();

                    // Jika type_pkg tidak disediakan, gunakan nilai yang sudah ada (jika package ada)
                    if ($typePkg === null && $existingPackage) {
                        $typePkg = $existingPackage->type_pkg;
                    }

                    // Update atau create package
                    Package::updateOrCreate(
                        ['id_part' => $part->id],
                        [
                            'type_pkg' => $typePkg !== '-' ? $typePkg : null,
                            'qty' => $qty
                        ]
                    );
                } catch (\Exception $e) {
                    $this->logs[] = "Baris $rowIndexForLog: Gagal update package. Error: " . $e->getMessage();
                }
            }
        }
    }
}
