<?php

namespace App\Imports;

use App\Models\Inventory;
use App\Models\Part;
use App\Models\PlanStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StoImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    protected $logs = [];
    protected $totalCount = 0;
    protected $successCount = 0;
    protected $failedCount = 0;
    protected $duplicateCount = 0;
    protected $totalQty = 0; // Tambahkan property untuk menghitung total quantity
    protected $date;
    protected $defaultDate;
    protected $importResults = [];
    protected $processedData = []; // Tambahkan property untuk menyimpan data yang sudah diproses dalam satu Excel
    protected $duplicateAction = 'update'; // 'skip' or 'update'
    protected $progress = 0;
    protected $progressMax = 0;

    public function __construct($date = null, $duplicateAction = 'update')
    {
        // Use provided date or current date as default
        $this->defaultDate = $date ? Carbon::parse($date) : Carbon::today();
        $this->duplicateAction = $duplicateAction;
        
        // Validate that date is not in the next month
        $nextMonth = Carbon::today()->addMonth()->startOfMonth();
        // if ($this->date->gte($nextMonth)) {
        //     throw new \Exception("Tanggal tidak boleh bulan berikutnya");
        // }
    }

    public function chunkSize(): int
    {
        return 500; // Increased for faster processing
    }
    
    // Add batch insert support
    public function batchSize(): int
    {
        return 200; // Increased batch size for faster database operations
    }

    public function collection(Collection $rows)
    {
        ini_set('max_execution_time', 900);
        ini_set('memory_limit', '2G');
        DB::disableQueryLog();

        $this->totalCount = $rows->count();
        $this->progressMax = $this->totalCount;

        // Group and sum qty per INV_ID per month
        $monthlyTotals = [];
        foreach ($rows as $row) {
            $rowArray = array_change_key_case($row->toArray(), CASE_LOWER);
            $invId = trim($rowArray['inv_id'] ?? '');
            if (empty($invId)) continue;

            $qty = (float)($rowArray['total_qty'] ?? 0);
            $date = $this->parseDate($rowArray);
            $monthKey = $invId . '|' . $date->format('Y-m'); // Group by INV_ID and month

            if (!isset($monthlyTotals[$monthKey])) {
                $monthlyTotals[$monthKey] = [
                    'inv_id' => $invId,
                    'month' => $date->format('Y-m'),
                    'qty' => $qty,
                    'dates' => [$date->format('Y-m-d')],
                ];
            } else {
                $monthlyTotals[$monthKey]['qty'] += $qty;
                $monthlyTotals[$monthKey]['dates'][] = $date->format('Y-m-d');
            }
        }

        // Process each monthly total
        foreach ($monthlyTotals as $key => $data) {
            $invId = $data['inv_id'];
            $month = $data['month'];
            $qty = $data['qty'];
            $dates = $data['dates'];

            // Find the first date in the month for record
            $date = Carbon::createFromFormat('Y-m-d', min($dates));

            $part = Part::where('Inv_id', $invId)->first();
            if (!$part) {
                $this->failedCount++;
                continue;
            }

            // Find inventory for this part and month
            $inventory = Inventory::where('id_part', $part->id)
                ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                ->orderBy('date', 'asc')
                ->first();

            // Calculate act_stock from dailyStockLogs for this month
            $actStock = $part->dailyStockLogs()
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$month])
                ->sum('Total_qty');
            $this->totalQty += $qty;

            $gap = $actStock - $qty;
            $remark = ($qty == 0 || $actStock != $qty) ? 'Abnormal' : 'Normal';
            $gapDescription = $this->getGapDescription($remark, $gap);

            if ($inventory) {
                // Pastikan $inventory->date adalah Carbon
                $inventoryDate = $inventory->date instanceof Carbon
                    ? $inventory->date
                    : Carbon::parse($inventory->date);

                // If same month, add qty; if new month, reset qty
                if ($inventoryDate->format('Y-m') == $month) {
                    $planStockBefore = $inventory->plan_stock;
                    $inventory->plan_stock += $qty;
                } else {
                    $planStockBefore = 0;
                    $inventory->plan_stock = $qty;
                    $inventory->date = $date->format('Y-m-d');
                }
                $inventory->act_stock = $actStock;
                $inventory->remark = $remark;
                $inventory->note_remark = $gapDescription;
                $inventory->save();

                PlanStock::create([
                    'id_inventory' => $inventory->id,
                    'plan_stock_before' => $planStockBefore,
                    'plan_stock_after' => $inventory->plan_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->successCount++;
            } else {
                $inventory = Inventory::create([
                    'id_part' => $part->id,
                    'date' => $date->format('Y-m-d'),
                    'plan_stock' => $qty,
                    'act_stock' => $actStock,
                    'remark' => $remark,
                    'note_remark' => $gapDescription
                ]);
                PlanStock::create([
                    'id_inventory' => $inventory->id,
                    'plan_stock_before' => 0,
                    'plan_stock_after' => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->successCount++;
            }

            // Store minimal result info
            if (count($this->importResults) < 500) {
                $this->importResults[] = [
                    'inv_id' => $invId,
                    'part_name' => $part->Part_name ?? 'Unknown',
                    'status' => $inventory ? 'updated' : 'success'
                ];
            }
            $this->progress++;
            // Tambahkan log progress setiap 100 data
            if ($this->progress % 100 === 0) {
                $this->addLog("Progress import: {$this->progress}/{$this->progressMax}");
            }
        }
    }
    
    /**
     * Get actual stock for a part from dailyStockLogs more efficiently
     */
    protected function getActualStockForPart($part)
    {
        // Take only the most recent logs for speed
        $actStock = $part->dailyStockLogs->take(10)->sum('Total_qty');
        return $actStock;
    }
    
    /**
     * Get gap description in a more efficient way
     */
    protected function getGapDescription($remark, $gap)
    {
        if ($remark != 'Abnormal') {
            return null;
        }
        
        if ($gap > 0) {
            return '+' . abs($gap);
        } elseif ($gap < 0) {
            return '-' . abs($gap);
        } else {
            return "Tidak ada stock";
        }
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }
    public function getDate()
    {
        return $this->defaultDate->format('Y-m-d');
    }
    public function getDuplicateCount(): int
    {
        return $this->duplicateCount;
    }
    public function getTotalQty(): float
    {
        return $this->totalQty;
    }
    
    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->progressMax == 0) return 0;
        return min(100, intval(($this->progress / $this->progressMax) * 100));
    }
    
    /**
     * Get current progress - optimized for speed
     */
    public function getProgress(): array
    {
        return [
            'current' => $this->progress,
            'total' => $this->progressMax,
            'percentage' => $this->getProgressPercentage(),
            'success' => $this->successCount,
            'failed' => $this->failedCount,
            'duplicate' => $this->duplicateCount
        ];
    }
    
    /**
     * Get import results with extreme memory optimization
     */
    public function getImportResults(): array
    {
        // Always return summary for best performance
        $summary = [
            [
                'inv_id' => 'IMPORT COMPLETE',
                'part_name' => '',
                'date' => $this->getDate(),
                'qty' => $this->totalQty,
                'status' => 'success',
                'message' => "Successfully processed {$this->successCount} items. Failed: {$this->failedCount}, Duplicates: {$this->duplicateCount}"
            ]
        ];
        
        // Return only first 25 items plus summary for minimal memory usage
        return count($this->importResults) > 0 
            ? array_merge(array_slice($this->importResults, 0, 25), $summary)
            : $summary;
    }

    protected function addLog($message)
    {
        if (count($this->logs) < 200) { // Reduced from 500 to prevent memory issues
            $this->logs[] = $message;
        }
        Log::info("StoImport: " . $message);
    }
    
    public function setDuplicateAction($action)
    {
        if (in_array($action, ['skip', 'update'])) {
            $this->duplicateAction = $action;
        }
        return $this;
    }
    
    public function getDuplicateAction()
    {
        return $this->duplicateAction;
    }

    /**
     * Parse date from row data
     */
    protected function parseDate($row)
    {
        if (isset($row['date']) && !empty($row['date'])) {
            try {
                // Add specific format parsing for common Excel date formats
                if (is_string($row['date'])) {
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $row['date'])) {
                        // Format: DD/MM/YYYY
                        return Carbon::createFromFormat('d/m/Y', $row['date']);
                    } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $row['date'])) {
                        // Format: DD-MM-YYYY
                        return Carbon::createFromFormat('d-m-Y', $row['date']);
                    } else {
                        // Try default Carbon parsing as fallback
                        return Carbon::parse($row['date']);
                    }
                } else {
                    // Handle Excel numeric dates
                    return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date']));
                }
            } catch (\Exception $e) {
                $this->addLog("Format tanggal tidak valid: " . $row['date'] . ". Menggunakan tanggal default.");
                return $this->defaultDate;
            }
        } else {
            return $this->defaultDate;
        }
    }
}