<?php

namespace App\Imports;

use App\Models\Bom;
use App\Models\Customer;
use App\Models\DailyStockLog;
use App\Models\Forecast;
use App\Models\Part;
use App\Models\WorkDays;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;

class ForecastImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    public array $logs = [];
    private $customersCache = null;
    private $partsCache = null;
    private $workDaysCache = null;
    private $bomDataCache = null;
    private $processedCount = 0;
    private $totalRows = 0;
    private $componentForecastsCache = [];
    private $componentUsageCounter = []; // Track how many times each component is used
    private $processedComponents = []; // Track untuk menghindari infinite recursion
    private $importedParts = []; // Track parts yang diimport untuk force refresh daily stock
    private $summaryGenerated = false; // Flag untuk mencegah duplikasi summary

    // Throttling configuration
    private $throttlingConfig = [
        'enabled' => false,
        'small_batch_size' => 10,      // Untuk data besar
        'medium_batch_size' => 20,     // Untuk data sedang
        'large_batch_size' => 50,      // Untuk data kecil
        'micro_sleep_ms' => 100,       // Sleep antar batch (milliseconds)
        'chunk_sleep_ms' => 500,       // Sleep antar chunk (milliseconds)
        'progress_interval' => 5       // Progress report setiap N batch
    ];

    // Statistik untuk logging yang lebih baik
    private $stats = [
        'created' => 0,
        'updated' => 0,
        'skipped_missing_customer' => 0,
        'skipped_missing_part' => 0,
        'skipped_invalid_date' => 0,
        'skipped_missing_workdays' => 0,
        'skipped_missing_frequensi_delivery' => 0,
        'skipped_other' => 0,
        'components_processed' => 0,
        'daily_stock_updated' => 0,
        'daily_stock_errors' => 0,
        'max_bom_depth_reached' => 0, // Track berapa level terdalam yang diproses
        'deep_cascade_processed' => 0 // Track berapa banyak deep cascade yang diproses
    ];

    // Verbosity setting untuk log yang lebih ringkas
    private $verbosity = 'detailed'; // 'detailed', 'limited', 'minimal'

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function chunkSize(): int
    {
        // Sesuaikan chunk size berdasarkan ukuran data dan memory yang tersedia
        $availableMemory = $this->getAvailableMemoryMB();

        if ($availableMemory > 512) {
            return 100; // Chunk besar jika memory cukup
        } elseif ($availableMemory > 256) {
            return 50;  // Chunk sedang
        } else {
            return 25;  // Chunk kecil jika memory terbatas
        }
    }

    public function batchSize(): int
    {
        // Batch size yang lebih kecil dari chunk size untuk mengurangi beban database
        return max(10, intval($this->chunkSize() / 2));
    }

    // Helper untuk cek available memory
    private function getAvailableMemoryMB(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return 1024; // Unlimited memory
        }

        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $currentUsage = memory_get_usage(true);
        $availableBytes = $memoryLimitBytes - $currentUsage;

        return max(64, intval($availableBytes / (1024 * 1024))); // Convert to MB
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = intval($limit);

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function parseTanggal($input, $rowNo, $fieldName = 'tanggal'): ?Carbon
    {
        if (empty($input)) {
            $this->logs[] = "Baris $rowNo: Kolom $fieldName kosong.";
            return null;
        }

        try {
            if (is_numeric($input)) {
                return Carbon::instance(Date::excelToDateTimeObject($input));
            }

            $replacements = [
                'januari' => 'january',
                'februari' => 'february',
                'maret' => 'march',
                'mei' => 'may',
                'juni' => 'june',
                'juli' => 'july',
                'agustus' => 'august',
                'oktober' => 'october',
                'desember' => 'december',
            ];

            $inputLower = Str::lower($input);
            $normalized = str_replace(array_keys($replacements), array_values($replacements), $inputLower);

            return Carbon::parse($normalized);
        } catch (\Exception $e) {
            $this->logs[] = "Baris $rowNo: Format $fieldName tidak valid → '{$input}'";
            return null;
        }
    }

    // Helper function untuk format tanggal yang aman
    private function formatDate($date, $format = 'Y-m-d'): string
    {
        if ($date instanceof Carbon) {
            return $date->format($format);
        }

        if (is_string($date)) {
            try {
                return Carbon::parse($date)->format($format);
            } catch (\Exception $e) {
                return $date; // Return as is if parsing fails
            }
        }

        return (string) $date;
    }

    public function collection(Collection $rows)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $this->totalRows = count($rows);

        // Tentukan verbosity berdasarkan ukuran data
        if ($this->totalRows >= 1000) {
            $this->verbosity = 'minimal';
        } elseif ($this->totalRows >= 100) {
            $this->verbosity = 'limited';
        } else {
            $this->verbosity = 'detailed';
        }

        // $this->logs[] = "[START] Import {$this->totalRows} baris data";

        // Determine optimal processing strategy based on data size
        $this->determineProcessingStrategy($this->totalRows);

        // Cache data untuk performa
        $this->cacheCustomers();
        $this->cacheParts();
        $this->cacheWorkDays();
        $this->cacheBomData();

        // Optimasi chunk size berdasarkan ukuran data
        $optimalChunkSize = $this->calculateOptimalChunkSize($this->totalRows);

        // Log chunk size hanya untuk data besar atau detailed mode
        // if ($this->verbosity === 'detailed' || $this->totalRows >= 1000) {
        //     $this->logs[] = "[INFO] Chunk size: {$optimalChunkSize}";
        // }

        $chunks = $rows->chunk($optimalChunkSize);
        $totalChunks = $chunks->count();

        foreach ($chunks as $chunkIndex => $chunk) {
            $startTime = microtime(true);

            // Process chunk dengan throttling
            $this->processChunkWithThrottling($chunk, $chunkIndex, $totalChunks, $chunkIndex * $optimalChunkSize);

            // Progress update dan optimasi memory setiap beberapa chunk
            $this->handleProgressAndMemory($chunkIndex, $totalChunks, $startTime);
        }

        // Process semua component forecasts yang terakumulasi
        $this->processAccumulatedComponentForecasts();

        // TAMBAHAN: Force refresh semua daily stock logs yang terkait dengan parts yang diimport
        // Ini memastikan bahwa meskipun forecast tidak berubah, daily stock tetap di-refresh
        $this->forceRefreshDailyStockForImportedParts();

        // Generate summary
        $this->generateImportSummary();
    }

    private function calculateOptimalChunkSize(int $totalRows): int
    {
        // Hitung chunk size optimal berdasarkan total data
        if ($totalRows > 10000) {
            return 200; // Chunk besar untuk data banyak
        } elseif ($totalRows > 5000) {
            return 100;
        } elseif ($totalRows > 1000) {
            return 50;
        } else {
            return 25; // Chunk kecil untuk data sedikit
        }
    }

    /**
     * Determine processing strategy based on data size
     */
    private function determineProcessingStrategy(int $totalRows): void
    {
        // if ($totalRows >= 1000) {
        //     $this->throttlingConfig['enabled'] = true;

        //     if ($totalRows >= 5000) {
        //         // Data sangat besar - throttling aggressive
        //         $this->throttlingConfig['small_batch_size'] = 5;
        //         $this->throttlingConfig['micro_sleep_ms'] = 200;
        //         $this->throttlingConfig['chunk_sleep_ms'] = 1000;
        //         $this->logs[] = "[THROTTLING] Data besar ({$totalRows} baris) - menggunakan throttling aggressive";

        //     } elseif ($totalRows >= 2000) {
        //         // Data besar - throttling moderate
        //         $this->throttlingConfig['small_batch_size'] = 8;
        //         $this->throttlingConfig['micro_sleep_ms'] = 150;
        //         $this->throttlingConfig['chunk_sleep_ms'] = 750;
        //         $this->logs[] = "[THROTTLING] Data sedang-besar ({$totalRows} baris) - menggunakan throttling moderate";

        //     } else {
        //         // Data sedang - throttling light
        //         $this->throttlingConfig['small_batch_size'] = 12;
        //         $this->throttlingConfig['micro_sleep_ms'] = 100;
        //         $this->throttlingConfig['chunk_sleep_ms'] = 500;
        //         $this->logs[] = "[THROTTLING] Data sedang ({$totalRows} baris) - menggunakan throttling light";
        //     }
        // } else {
        //     $this->logs[] = "[INFO] Data kecil ({$totalRows} baris) - throttling dinonaktifkan";
        // }
    }

    /**
     * Process chunk with throttling for server-friendly processing
     */
    private function processChunkWithThrottling(Collection $chunk, int $chunkIndex, int $totalChunks, int $startIndex): void
    {
        if (!$this->throttlingConfig['enabled']) {
            // Process normal jika throttling disabled - gunakan approach yang lebih aman
            $this->processChunkSafely($chunk, $startIndex);
            return;
        }

        // Split chunk into smaller batches untuk throttling
        $batchSize = $this->throttlingConfig['small_batch_size'];
        $batches = $chunk->chunk($batchSize);
        $totalBatches = $batches->count();

        $this->logs[] = "[THROTTLING] Chunk " . ($chunkIndex + 1) . "/{$totalChunks}: Memproses {$totalBatches} batch (@{$batchSize} items/batch)";

        foreach ($batches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);

            // Process batch dengan error handling yang lebih baik
            $this->processBatch($batch, $startIndex + ($batchIndex * $batchSize));

            // Server-friendly sleep antar batch
            $this->throttleSleep('micro');

            // Progress report setiap interval tertentu
            if (($batchIndex + 1) % $this->throttlingConfig['progress_interval'] === 0 || $batchIndex === $totalBatches - 1) {
                $batchProgress = round((($batchIndex + 1) / $totalBatches) * 100, 1);
                $overallProgress = round((($chunkIndex * $this->calculateOptimalChunkSize($this->totalRows) + ($batchIndex + 1) * $batchSize) / $this->totalRows) * 100, 1);
                $batchTime = round((microtime(true) - $batchStartTime) * 1000, 1);

                // $this->logs[] = "[PROGRESS] Batch {$batchProgress}% | Overall {$overallProgress}% | Time: {$batchTime}ms";
            }

            // Memory check setiap beberapa batch
            // if (($batchIndex + 1) % 3 === 0) {
            //     $memoryMB = round(memory_get_usage(true) / (1024 * 1024), 1);
            //     if ($memoryMB > 400) {
            //         $this->logs[] = "[MEMORY] High usage: {$memoryMB}MB - forcing cleanup";
            //         gc_collect_cycles();
            //     }
            // }
        }

        // Sleep yang lebih lama antar chunk untuk memberi jeda server
        $this->throttleSleep('chunk');
    }

    /**
     * Process chunk dengan approach yang lebih aman (tanpa throttling)
     */
    private function processChunkSafely(Collection $chunk, int $startIndex): void
    {
        $grouped = [];

        foreach ($chunk as $index => $row) {
            $globalIndex = $startIndex + $index;
            $inv_id = trim($row['inv_id'] ?? '');
            $customer_name = trim($row['customer'] ?? '');
            $forecast_raw = $row['forecast_month'] ?? null;

            if (!$this->isInvIdInBom($inv_id)) {
                $this->logProgress("Baris " . ($globalIndex + 2) . ": INV_ID '{$inv_id}' tidak ditemukan di BOM", true);
                $this->processedCount++;
                continue;
            }

            $forecastParsed = $this->parseTanggal($forecast_raw, $globalIndex + 2, 'forecast_month');
            if (!$forecastParsed) {
                $this->processedCount++;
                continue;
            }

            $forecastMonth = $forecastParsed->startOfMonth();
            $groupKey = $inv_id . '|' . $customer_name . '|' . $this->formatDate($forecastMonth, 'Y-m');
            $grouped[$groupKey][] = ['index' => $globalIndex, 'row' => $row, 'forecastMonth' => $forecastMonth];
        }

        // Process dengan transaction yang aman
        $this->processGroupedDataSafely($grouped);

        $this->processedCount += count($chunk);
        $this->clearMemoryCache();
    }

    /**
     * Process grouped data dengan transaction handling yang lebih aman
     */
    private function processGroupedDataSafely(array $grouped): void
    {
        if (empty($grouped)) {
            return;
        }

        // Perbaikan pengelolaan transaksi - hindari "There is no active transaction"
        try {
            // Gunakan 5 retries untuk transaksi dan pastikan semua selesai dalam satu transaksi
            DB::transaction(function () use ($grouped) {
                foreach ($grouped as $key => $entries) {
                    $this->processForecastGroup($entries);
                }
            }, 5); // Tingkatkan retry ke 5 kali untuk memberi kesempatan lebih

        } catch (\Throwable $e) {
            // Log error tapi lanjutkan dengan individual processing tanpa transaksi
            $this->logs[] = "[TRANSACTION ERROR] " . $e->getMessage() . " - switching to individual processing";

            // Process satu per satu tanpa menggunakan transaksi DB
            foreach ($grouped as $key => $entries) {
                try {
                    // Tambahkan delay kecil untuk mencegah kelebihan beban database
                    usleep(5000); // 5ms delay
                    $this->processForecastGroup($entries);
                } catch (\Throwable $itemError) {
                    $this->logs[] = "[ERROR] Failed to process item: " . $itemError->getMessage();
                }
            }
        }
    }    /**
         * Process individual batch (smaller than chunk)
         */
    private function processBatch(Collection $batch, int $startIndex): void
    {
        $grouped = [];

        foreach ($batch as $index => $row) {
            $globalIndex = $startIndex + $index;
            $inv_id = trim($row['inv_id'] ?? '');
            $customer_name = trim($row['customer'] ?? '');
            $forecast_raw = $row['forecast_month'] ?? null;

            if (!$this->isInvIdInBom($inv_id)) {
                $this->logProgress("Baris " . ($globalIndex + 2) . ": INV_ID '{$inv_id}' tidak ditemukan di BOM", true);
                $this->processedCount++;
                continue;
            }

            $forecastParsed = $this->parseTanggal($forecast_raw, $globalIndex + 2, 'forecast_month');
            if (!$forecastParsed) {
                $this->processedCount++;
                continue;
            }

            $forecastMonth = $forecastParsed->startOfMonth();
            $groupKey = $inv_id . '|' . $customer_name . '|' . $this->formatDate($forecastMonth, 'Y-m');
            $grouped[$groupKey][] = ['index' => $globalIndex, 'row' => $row, 'forecastMonth' => $forecastMonth];
        }

        // Process dengan safe method yang menghindari transaction error
        $this->processGroupedDataSafely($grouped);

        $this->processedCount += count($batch);
        $this->clearMemoryCache();
    }

    /**
     * Process grouped data with throttling-aware transaction handling
     */
    private function processGroupedDataWithThrottling(array $grouped): void
    {
        if (empty($grouped)) {
            return;
        }

        // Jangan gunakan sleep dalam transaction untuk menghindari timeout
        try {
            DB::transaction(function () use ($grouped) {
                foreach ($grouped as $key => $entries) {
                    $this->processForecastGroup($entries);
                }
            }, 2); // Retry maksimal 2x untuk throttling scenario

            // Sleep setelah transaction selesai
            if ($this->throttlingConfig['enabled'] && count($grouped) > 3) {
                $this->throttleSleep('micro');
            }

        } catch (\Exception $e) {
            $this->logs[] = "[ERROR] Batch transaction failed: " . $e->getMessage();

            // Individual processing tanpa transaction jika batch gagal
            foreach ($grouped as $key => $entries) {
                try {
                    // Process individual item tanpa wrapping transaction
                    $this->processForecastGroup($entries);

                    // Sleep antar item untuk mencegah database overload
                    if ($this->throttlingConfig['enabled']) {
                        usleep(25000); // 25ms antar item
                    }

                } catch (\Exception $itemError) {
                    $this->logs[] = "[ERROR] Failed to process individual item: " . $itemError->getMessage();
                }
            }
        }
    }

    /**
     * Throttling sleep dengan berbagai mode
     */
    private function throttleSleep(string $mode = 'micro'): void
    {
        if (!$this->throttlingConfig['enabled']) {
            return;
        }

        switch ($mode) {
            case 'micro':
                $sleepMs = $this->throttlingConfig['micro_sleep_ms'];
                break;
            case 'chunk':
                $sleepMs = $this->throttlingConfig['chunk_sleep_ms'];
                break;
            default:
                $sleepMs = 100;
        }

        // Convert milliseconds to microseconds
        usleep($sleepMs * 1000);
    }

    /**
     * Adaptive throttling berdasarkan performa server
     */
    private function adaptiveThrottling(float $processingTimeMs): void
    {
        if (!$this->throttlingConfig['enabled']) {
            return;
        }

        $memoryUsageMB = memory_get_usage(true) / (1024 * 1024);

        // Adjust throttling berdasarkan kondisi server
        if ($memoryUsageMB > 600 || $processingTimeMs > 1000) {
            // Server under stress - increase throttling
            $this->throttlingConfig['micro_sleep_ms'] = min(300, $this->throttlingConfig['micro_sleep_ms'] * 1.5);
            $this->throttlingConfig['chunk_sleep_ms'] = min(1500, $this->throttlingConfig['chunk_sleep_ms'] * 1.3);

            if ($memoryUsageMB > 600) {
                $this->logs[] = "[ADAPTIVE] Memory tinggi ({$memoryUsageMB}MB) - memperlambat throttling";
            }
            if ($processingTimeMs > 1000) {
                $this->logs[] = "[ADAPTIVE] Processing lambat ({$processingTimeMs}ms) - memperlambat throttling";
            }

        } elseif ($memoryUsageMB < 200 && $processingTimeMs < 200) {
            // Server performing well - decrease throttling slightly
            $this->throttlingConfig['micro_sleep_ms'] = max(50, $this->throttlingConfig['micro_sleep_ms'] * 0.9);
            $this->throttlingConfig['chunk_sleep_ms'] = max(200, $this->throttlingConfig['chunk_sleep_ms'] * 0.9);
        }
    }

    /**
     * Server health check untuk monitoring
     */
    private function logServerHealth(): void
    {
        if (!$this->throttlingConfig['enabled']) {
            return;
        }

        static $healthCheckCount = 0;
        $healthCheckCount++;

        // Log server health setiap 50 processed items
        if ($healthCheckCount % 50 === 0) {
            $memoryMB = round(memory_get_usage(true) / (1024 * 1024), 1);
            $peakMemoryMB = round(memory_get_peak_usage(true) / (1024 * 1024), 1);

            $this->logs[] = "[SERVER HEALTH] Memory: {$memoryMB}MB | Peak: {$peakMemoryMB}MB | Processed: {$this->processedCount}/{$this->totalRows}";

            // Warning jika mendekati limit
            if ($memoryMB > 700) {
                $this->logs[] = "[WARNING] Memory usage tinggi! Pertimbangkan restart jika diperlukan";
            }
        }
    }

    private function handleProgressAndMemory(int $chunkIndex, int $totalChunks, float $startTime): void
    {
        $processingTime = microtime(true) - $startTime;
        $processingTimeMs = $processingTime * 1000;

        // Adaptive throttling berdasarkan performa
        $this->adaptiveThrottling($processingTimeMs);

        // Progress update setiap 10% atau setiap 5 chunk (mana yang lebih jarang)
        $progressInterval = max(1, intval($totalChunks / 10));
        $chunkInterval = 5;

        if ($chunkIndex % min($progressInterval, $chunkInterval) === 0) {
            // Hapus progress logging untuk mengurangi spam
            // Tetap lakukan garbage collection dan sleep untuk performa
            gc_collect_cycles();

            if ($processingTime < 0.1) {
                $sleepTime = $this->throttlingConfig['enabled'] ? 20000 : 10000;
                usleep($sleepTime);
            }
        }

        // Hapus server health monitoring untuk mengurangi log spam
    }

    protected function isInvIdInBom(string $inv_id): bool
    {
        // Cek apakah inv_id ada sebagai product atau component di BOM
        return $this->bomDataCache->contains(function ($item) use ($inv_id) {
            return optional($item->product)->Inv_id === $inv_id ||
                optional($item->component)->Inv_id === $inv_id;
        });
    }

    protected function cacheBomData()
    {
        if ($this->bomDataCache === null) {
            $this->bomDataCache = Bom::with(['product', 'component'])->get();
        }
    }

    protected function cacheCustomers()
    {
        if ($this->customersCache === null) {
            $this->customersCache = Customer::all()->keyBy('username');
        }
    }

    protected function cacheParts()
    {
        if ($this->partsCache === null) {
            $this->partsCache = Part::with('package')
                ->select(['id', 'Inv_id', 'id_customer', 'Part_name', 'Part_number'])
                ->get()
                ->keyBy('Inv_id'); // Hanya gunakan Inv_id sebagai key
        }
    }

    protected function cacheWorkDays()
    {
        if ($this->workDaysCache === null) {
            $this->workDaysCache = collect();
            foreach (WorkDays::all() as $wd) {
                try {
                    // Get raw month value and format it
                    $monthValue = $wd->getAttributes()['month']; // Get raw database value
                    $monthKey = Carbon::parse($monthValue)->format('Y-m');
                    $this->workDaysCache->put($monthKey, $wd);
                } catch (\Exception $e) {
                    // Skip invalid dates
                    continue;
                }
            }
        }
    }

    protected function processAccumulatedComponentForecasts()
    {
        if (empty($this->componentForecastsCache)) {
            return;
        }

        $totalComponents = count($this->componentForecastsCache);
        // $this->logs[] = "[COMPONENT] Processing {$totalComponents} accumulated components...";

        // Determine batch size berdasarkan throttling config
        $batchSize = $this->throttlingConfig['enabled'] ? 15 : 40;

        // Process dalam batch untuk mengurangi beban database
        $componentBatches = array_chunk($this->componentForecastsCache, $batchSize, true);
        $totalBatches = count($componentBatches);

        foreach ($componentBatches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);

            // Process dengan approach yang lebih safe tanpa nested transaction
            $this->processComponentBatchSafely($batch, $batchIndex, $totalBatches);

            // Throttling setelah batch selesai
            if ($this->throttlingConfig['enabled'] && $totalBatches > 1) {
                $this->throttleSleep('micro');
            }

            // Progress report untuk component processing
            // if (($batchIndex + 1) % 3 === 0 || $batchIndex === $totalBatches - 1) {
            //     $progress = round((($batchIndex + 1) / $totalBatches) * 100, 1);
            //     $batchTime = round((microtime(true) - $batchStartTime) * 1000, 1);
            //     $currentBatch = $batchIndex + 1;
            //     $this->logs[] = "[COMPONENT PROGRESS] {$progress}% ({$currentBatch}/{$totalBatches}) | Time: {$batchTime}ms";
            // }
        }

        // Log summary of multiple usage components
        $this->logMultipleUsageComponents();

        // Clear cache
        $this->componentForecastsCache = [];
        $this->componentUsageCounter = [];
    }

    /**
     * Process component batch dengan approach yang safe
     */
    private function processComponentBatchSafely(array $batch, int $batchIndex, int $totalBatches): void
    {
        try {
            // Tingkatkan retry untuk mengurangi kegagalan transaksi
            DB::transaction(function () use ($batch) {
                foreach ($batch as $key => $data) {
                    $this->saveComponentForecast($data);
                }
            }, 5); // Tambahkan retry ke 5 kali untuk mengatasi kegagalan transaksi

        } catch (\Throwable $e) {
            $this->logs[] = "[COMPONENT ERROR] Batch {$batchIndex} transaction failed: " . $e->getMessage();

            // Tambahkan delay sebelum mencoba individual processing
            usleep(100000); // 100ms delay sebelum recovery

            // Individual processing tanpa transaction jika batch gagal
            foreach ($batch as $key => $data) {
                try {
                    // Pastikan tidak menggunakan transaksi DB di sini
                    $this->saveComponentForecast($data);

                    // Extra throttling untuk individual recovery
                    usleep(10000); // 10ms untuk individual retry
                } catch (\Throwable $itemError) {
                    $this->logs[] = "[COMPONENT ERROR] Item {$key} failed: " . $itemError->getMessage();
                }
            }
        }
    }

    private function logMultipleUsageComponents(): void
    {
        // Hitung komponen yang digunakan oleh multiple products
        $multipleUsageComponents = array_filter($this->componentUsageCounter, function($count) {
            return $count > 1;
        });

        if (!empty($multipleUsageComponents) && $this->verbosity !== 'minimal') {
            $totalMultipleUsage = count($multipleUsageComponents);
            // $this->logs[] = "[MULTI-USAGE] {$totalMultipleUsage} components digunakan oleh multiple products";

            // Log detail untuk beberapa component pertama jika detailed mode
            if ($this->verbosity === 'detailed') {
                $count = 0;
                foreach ($multipleUsageComponents as $cacheKey => $usageCount) {
                    if ($count >= 3) break; // Batasi log detail max 3 component

                    [$componentId, $monthKey] = explode('|', $cacheKey);
                    $component = Part::find($componentId);
                    $componentInvId = optional($component)->Inv_id ?? $componentId;
                    $finalFreqDelivery = $this->componentForecastsCache[$cacheKey]['frequensi_delivery'] ?? 'null';

                    // $this->logs[] = "[MULTI-DETAIL] Component {$componentInvId}: used by {$usageCount} products, final freq_delivery: {$finalFreqDelivery}";
                    $count++;
                }
            }
        }
    }

    protected function saveComponentForecast(array $data)
    {
        // Handle parent_forecast_id - use first one if it's an array
        $parentForecastId = is_array($data['parent_forecast_id'])
            ? $data['parent_forecast_id'][0]
            : $data['parent_forecast_id'];

        // Get the part untuk component ini
        $componentPart = $this->partsCache->get($data['id_part']);
        if (!$componentPart) {
            // Jika part tidak ditemukan di cache, coba ambil dari database
            $componentPart = Part::find($data['id_part']);
            if (!$componentPart) {
                $this->logs[] = "[COMPONENT ERROR] Part dengan ID {$data['id_part']} tidak ditemukan";
                return null;
            }
        }

        // Cek apakah component forecast sudah ada untuk akumulasi yang benar
        $existingComponent = Forecast::where('id_part', $data['id_part'])
            ->where('forecast_month', $data['forecast_month'])
            ->first();

        $isNewComponent = false;
        $finalMin = $data['min']; // Track final min value untuk daily stock update

        if ($existingComponent && $existingComponent->is_component) {
            // Jika sudah ada, AKUMULASI semua nilai termasuk frequensi_delivery
            $oldPO = $existingComponent->PO_pcs;
            $oldFreqDelivery = $existingComponent->frequensi_delivery ?? 0;

            $newPO = $oldPO + $data['po_pcs'];
            $newFreqDelivery = $oldFreqDelivery + ($data['frequensi_delivery'] ?? 0); // Akumulasi frequensi_delivery
            $newTotalBomQty = ($existingComponent->total_bom_qty ?? 1) + ($data['total_bom_qty'] ?? ($data['bom_quantity'] ?? 1)); // Akumulasi total BOM qty

            // Recalculate min dan max berdasarkan logika yang sama dengan product
            $newMin = 0;
            $newMax = 0;
            if ($newFreqDelivery > 0) {
                $newMin = (int) ceil($newPO / max($newFreqDelivery, 1));
                // Max = min × TOTAL BOM quantity dari semua products yang menggunakan component ini
                $newMax = (int) ceil($newMin * max($newTotalBomQty, 1));
            } else {
                // Fallback ke data yang diberikan jika tidak ada frequensi_delivery
                $newMin = $existingComponent->min + $data['min'];
                $newMax = $existingComponent->max + $data['max'];
            }

            $finalMin = $newMin; // Update final min untuk daily stock

            $componentForecast = Forecast::where('id_part', $data['id_part'])
                ->where('forecast_month', $data['forecast_month'])
                ->update([
                    'PO_pcs' => $newPO,
                    'min' => $newMin,
                    'max' => $newMax,
                    'id_work' => $data['id_work'],
                    'hari_kerja' => $data['hari_kerja'],
                    'frequensi_delivery' => $newFreqDelivery, // Gunakan nilai terakumulasi
                    'issued_at' => $data['issued_at'],
                    'is_component' => true,
                    'parent_forecast_id' => $parentForecastId,
                    'is_product5_hierarchy' => $data['is_product5_hierarchy'],
                    'bom_quantity' => $data['bom_quantity'],
                    'bom_unit' => $data['bom_unit'],
                    'total_bom_qty' => $newTotalBomQty, // Simpan total BOM quantity
                    'updated_at' => now() 
                ]);            
                    $returnForecast = Forecast::find($existingComponent->id);

        } else {
            // Jika belum ada, buat baru
            $isNewComponent = true;

            $componentForecast = Forecast::updateOrCreate(
                [
                    'id_part' => $data['id_part'],
                    'forecast_month' => $data['forecast_month']
                ],
                [
                    'id_work' => $data['id_work'],
                    'hari_kerja' => $data['hari_kerja'],
                    'frequensi_delivery' => $data['frequensi_delivery'], // Untuk component baru, langsung gunakan nilai dari cache
                    'PO_pcs' => $data['po_pcs'],
                    'min' => $data['min'],
                    'max' => $data['max'],
                    'issued_at' => $data['issued_at'],
                    'is_component' => true,
                    'parent_forecast_id' => $parentForecastId,
                    'is_product5_hierarchy' => $data['is_product5_hierarchy'],
                    'bom_quantity' => $data['bom_quantity'],
                    'bom_unit' => $data['bom_unit'],
                    'updated_at' => now()
                ]
            );

            $returnForecast = $componentForecast;
        }

        // SELALU update daily stock logs untuk component (baik baru maupun update)
        // Ini memastikan setiap import akan refresh daily stock calculation
        $this->updateDailyLogs($componentPart, $finalMin, 0, $isNewComponent);

        // Track component part untuk force refresh nanti
        if (!in_array($data['id_part'], $this->importedParts)) {
            $this->importedParts[] = $data['id_part'];
        }

        return $returnForecast;
    }

    /**
     * Force refresh daily stock untuk semua parts yang diimport
     * Memastikan setiap import akan update daily stock calculation
     */
    protected function forceRefreshDailyStockForImportedParts()
    {
        if (empty($this->importedParts)) {
            return;
        }

        $totalParts = count($this->importedParts);
        // $this->logs[] = "[DAILY-STOCK] Force refreshing daily stock untuk {$totalParts} imported parts...";

        $refreshed = 0;
        $errors = 0;

        foreach ($this->importedParts as $partId) {
            try {
                // Ambil part
                $part = Part::find($partId);
                if (!$part) {
                    continue;
                }

                // Ambil forecast terbaru untuk part ini
                $latestForecast = Forecast::where('id_part', $partId)
                    ->orderBy('forecast_month', 'desc')
                    ->first();

                if (!$latestForecast || $latestForecast->min <= 0) {
                    continue;
                }

                // Update semua daily stock logs untuk part ini dengan nilai min terbaru
                $updated = DailyStockLog::where('id_inventory', $partId)
                    ->update([
                        'stock_per_day' => DB::raw('CASE
                            WHEN Total_qty > 0 THEN Total_qty / ' . $latestForecast->min . '
                            ELSE 0
                        END')
                    ]);

                if ($updated > 0) {
                    $refreshed += $updated;

                    // Log individual part refresh hanya untuk detailed mode
                    // if ($this->verbosity === 'detailed') {
                    //     $this->logProgress("[REFRESH] Part {$part->Inv_id}: {$updated} daily records refreshed with min={$latestForecast->min}", true);
                    // }
                }

            } catch (\Exception $e) {
                $errors++;
                // $this->logProgress("[REFRESH ERROR] Part ID {$partId}: " . $e->getMessage(), true);
            }
        }

        // Update statistik global
        if (!isset($this->stats['force_refreshed_daily_stock'])) {
            $this->stats['force_refreshed_daily_stock'] = 0;
        }
        $this->stats['force_refreshed_daily_stock'] += $refreshed;

        // Log summary
        if ($refreshed > 0) {
            // $this->logs[] = "[DAILY-STOCK] Force refresh completed: {$refreshed} daily stock records updated";
        }
        if ($errors > 0) {
            $this->logs[] = "[DAILY-STOCK ERROR] {$errors} parts failed during force refresh";
        }
    }

    protected function generateImportSummary()
    {
        // Guard untuk mencegah duplikasi summary
        if ($this->summaryGenerated) {
            return;
        }

        $this->summaryGenerated = true;

        $totalProcessed = $this->stats['created'] + $this->stats['updated'];
        $totalSkipped = array_sum([
            $this->stats['skipped_missing_customer'],
            $this->stats['skipped_missing_part'],
            $this->stats['skipped_invalid_date'],
            $this->stats['skipped_missing_workdays'],
            $this->stats['skipped_missing_frequensi_delivery'],
            $this->stats['skipped_other']
        ]);

        // Gabungkan semua informasi dalam satu summary yang kompak
        $summaryParts = [];

        // Opsi untuk summary kompak (1 baris) atau detail (multiple baris)
        if ($this->verbosity === 'minimal' || $totalProcessed == 0) {
            // Summary sangat kompak dalam 1-2 baris saja
            $stockInfo = "";
            if (isset($this->stats['daily_stock_updated']) && $this->stats['daily_stock_updated'] > 0) {
                $totalDailyStockUpdated = $this->stats['daily_stock_updated'];
                if (isset($this->stats['force_refreshed_daily_stock'])) {
                    $totalDailyStockUpdated += $this->stats['force_refreshed_daily_stock'];
                }
                $stockInfo = " | Stock: {$totalDailyStockUpdated} records";
            }

            $skipInfo = "";
            if ($totalSkipped > 0) {
                $skipInfo = " | Skipped: {$totalSkipped}";
            }

            $memoryMB = round(memory_get_usage(true) / (1024 * 1024), 1);
            // $summaryParts[] = "[IMPORT] {$totalProcessed} forecast diproses ({$this->stats['created']} baru, {$this->stats['updated']} update){$stockInfo}{$skipInfo} | Memory: {$memoryMB}MB";

        } else {
            // Summary detail seperti sebelumnya
            $summaryParts[] = "[SUCCESS] {$totalProcessed} forecast diproses ({$this->stats['created']} baru, {$this->stats['updated']} diupdate)";

            // Tambahkan info skipped jika ada
            if ($totalSkipped > 0) {
                $successRate = round(($totalProcessed / ($totalProcessed + $totalSkipped)) * 100, 1);
                $summaryParts[] = "[SKIP] {$totalSkipped} baris dilewati (Success rate: {$successRate}%)";
            }

            // Tambahkan info daily stock update
            if (isset($this->stats['daily_stock_updated']) && $this->stats['daily_stock_updated'] > 0) {
                $totalDailyStockUpdated = $this->stats['daily_stock_updated'];
                if (isset($this->stats['force_refreshed_daily_stock'])) {
                    $totalDailyStockUpdated += $this->stats['force_refreshed_daily_stock'];
                }
                $summaryParts[] = "[STOCK] {$totalDailyStockUpdated} daily stock records diperbarui";
            }

            // Tambahkan memory info
            $finalMemoryMB = round(memory_get_usage(true) / (1024 * 1024), 1);
            if ($finalMemoryMB > 100 || $this->verbosity === 'detailed') {
                $summaryParts[] = "[MEMORY] {$finalMemoryMB}MB used";
            }
        }

        // Tambahkan error info yang penting (selalu ditampilkan terlepas verbosity)
        if ($this->stats['skipped_missing_frequensi_delivery'] > 0) {
            $summaryParts[] = "[FREQUENSI_DELIVERY] {$this->stats['skipped_missing_frequensi_delivery']} INV_ID dilewati karena PO_pcs terisi tapi frequensi_delivery kosong";
        }

        if (isset($this->stats['daily_stock_errors']) && $this->stats['daily_stock_errors'] > 0) {
            $summaryParts[] = "[STOCK ERROR] {$this->stats['daily_stock_errors']} parts gagal update daily stock";
        }

        // Log semua summary sekaligus
        foreach ($summaryParts as $summary) {
            $this->logs[] = $summary;
        }
    }

    protected function logProgress($message, $forceLog = false)
    {
        // Only log every 10th processed item or forced logs to reduce memory usage
        if ($forceLog || $this->processedCount % 10 === 0) {
            $this->logs[] = $message;
        }
    }

    protected function clearMemoryCache()
    {
        // Aggressive memory cleanup setiap 50 processed items
        if ($this->processedCount % 50 === 0) {
            // Clear any temporary variables
            if ($this->processedCount % 200 === 0) {
                // Reset processed components array secara berkala untuk mencegah memory leak
                $this->processedComponents = [];
            }

            gc_collect_cycles();

            // Log memory usage jika memory tinggi
            $memoryUsage = memory_get_usage(true) / (1024 * 1024);
            if ($memoryUsage > 500) { // Jika memory usage > 500MB
                $this->logs[] = "[WARNING] High memory usage: " . round($memoryUsage, 1) . "MB";
            }
        }
    }

    protected function processForecastGroup(array $entries)
    {
        $firstRow = $entries[0]['row'];
        $no = $entries[0]['index'] + 2;
        $inv_id = trim($firstRow['inv_id'] ?? '');
        $part_name = trim($firstRow['part_name'] ?? '');
        $part_number = trim($firstRow['part_number'] ?? '');
        $customer_name = trim($firstRow['customer'] ?? '');
        $forecastMonth = $entries[0]['forecastMonth'];
        $monthKey = $this->formatDate($forecastMonth, 'Y-m');

        // Validasi data
        if (!$inv_id || !$customer_name || empty($forecastMonth)) {
            $this->logProgress("Baris $no: Kolom tidak lengkap.", true);
            return;
        }

        $customer = $this->customersCache[$customer_name] ?? null;
        if (!$customer) {
            $this->stats['skipped_missing_customer']++;
            // Log hanya jika detailed mode dan belum terlalu banyak
            if ($this->verbosity === 'detailed' && $this->stats['skipped_missing_customer'] <= 2) {
                $this->logProgress("[SKIP] Customer '{$customer_name}' tidak ditemukan", true);
            }
            return;
        }

        // Gunakan hanya inv_id untuk pencarian part (tanpa customer constraint)
        $part = $this->partsCache[$inv_id] ?? null;
        if (!$part) {
            $this->stats['skipped_missing_part']++;
            // Log hanya jika detailed mode dan belum terlalu banyak
            if ($this->verbosity === 'detailed' && $this->stats['skipped_missing_part'] <= 2) {
                $this->logProgress("[SKIP] Part '{$inv_id}' tidak ditemukan", true);
            }
            return;
        }

        // Hapus validasi part name dan number untuk menyederhanakan

        // Cek jika forecast sudah ada untuk update
        $existingForecast = Forecast::where('id_part', $part->id)->where('forecast_month', $forecastMonth)->first();
        // Tidak perlu log di sini, akan dihandle di create/update section

        $workDay = $this->workDaysCache[$monthKey] ?? null;
        if (!$workDay || $workDay->hari_kerja <= 0) {
            $this->stats['skipped_missing_workdays']++;
            if ($this->verbosity === 'detailed' && $this->stats['skipped_missing_workdays'] <= 2) {
                $this->logProgress("[SKIP] Work days belum diatur untuk {$monthKey}", true);
            }
            return;
        }

        // Hitung total PO dan issued_at terakhir
        $hariKerja = (int) $workDay->hari_kerja;
        $poPcsTotal = 0;
        $issuedAts = [];
        $frequensiDelivery = 0;

        foreach ($entries as $entry) {
            $r = $entry['row'];
            $poPcsTotal += (int) $r['po_pcs'];

            // Ambil frequensi_delivery dari row
            if (isset($r['frequensi_delivery']) && !empty($r['frequensi_delivery'])) {
                $frequensiDelivery = (int) $r['frequensi_delivery'];
            }

            if ($parsed = $this->parseTanggal($r['issued_at'] ?? null, $entry['index'] + 2, 'issued_at')) {
                $issuedAts[] = $parsed;
            }
        }

        // Validasi frequensi_delivery berdasarkan kondisi PO_pcs
        if ($poPcsTotal > 0) {
            // Jika PO_pcs terisi (tidak 0), maka frequensi_delivery WAJIB ada dan tidak boleh null/0
            if (!$frequensiDelivery || $frequensiDelivery <= 0) {
                $this->stats['skipped_missing_frequensi_delivery']++;
                $this->logProgress("[SKIP] INV_ID '{$inv_id}' - PO_pcs terisi ({$poPcsTotal}) tetapi frequensi_delivery kosong (wajib diisi)", true);
                return;
            }
        } else {
            // Jika PO_pcs tidak terisi atau 0, maka set frequensi_delivery ke 0
            $frequensiDelivery = 0;
        }

        $issuedAtFinal = collect($issuedAts)->sort()->last()?->toDateString();
        if (!$issuedAtFinal) {
            $this->stats['skipped_invalid_date']++;
            if ($this->verbosity === 'detailed' && $this->stats['skipped_invalid_date'] <= 2) {
                $this->logProgress("[SKIP] Tanggal issued tidak valid", true);
            }
            return;
        }

        // Hitung min dan max berdasarkan kondisi PO_pcs dan frequensi_delivery
        $min = 0;
        $max = 0;

        if ($poPcsTotal > 0 && $frequensiDelivery > 0) {
            // Jika ada PO_pcs dan frequensi_delivery, gunakan formula normal
            $min = (int) ceil($poPcsTotal / $frequensiDelivery);
            $max = $min * 3;
        } elseif ($poPcsTotal > 0 && $frequensiDelivery == 0) {
            // Jika ada PO_pcs tapi frequensi_delivery = 0, set min/max ke 0
            $min = 0;
            $max = 0;
        } else {
            // Jika PO_pcs = 0, maka min/max juga 0
            $min = 0;
            $max = 0;
        }

        // Penanganan khusus Product 5
        $isProduct5 = ($inv_id === '5');
        if ($isProduct5 && $min > 0) {
            $min = $min * 3;
            $max = $min * 3;
        }

        try {
            // Simpan forecast utama dengan updateOrCreate
            // frequensi_delivery disimpan sesuai aturan:
            // - Jika PO_pcs > 0, frequensi_delivery wajib > 0
            // - Jika PO_pcs = 0, frequensi_delivery = 0
            $forecast = Forecast::updateOrCreate(
                [
                    'id_part' => $part->id,
                    'forecast_month' => $forecastMonth
                ],
                [
                    'id_work' => $workDay->id,
                    'hari_kerja' => $hariKerja,
                    'frequensi_delivery' => $frequensiDelivery, // Disimpan sesuai validasi di atas
                    'PO_pcs' => $poPcsTotal,
                    'min' => $min,
                    'max' => $max,
                    'issued_at' => $issuedAtFinal,
                    // 'is_product5_hierarchy' => $isProduct5
                ]
            );

            // Log apakah ini update atau create baru
            $isNewForecast = $forecast->wasRecentlyCreated;
            // if ($isNewForecast) {
            //     $this->stats['created']++;
            //     // Log detail dengan metode perhitungan yang digunakan
            //     if (($this->totalRows < 100 && $this->stats['created'] <= 10) || $this->stats['created'] <= 3) {
            //         $this->logProgress("[NEW] {$inv_id} | PO:{$poPcsTotal} | Min:{$min} | FD:{$frequensiDelivery}", true);
            //     }
            // } else {
            //     $this->stats['updated']++;
            //     // Log detail dengan metode perhitungan yang digunakan
            //     if (($this->totalRows < 100 && $this->stats['updated'] <= 10) || $this->stats['updated'] <= 3) {
            //         $this->logProgress("[UPD] {$inv_id} | PO:{$poPcsTotal} | Min:{$min} | FD:{$frequensiDelivery}", true);
            //     }
            // }

            // Reset processed components untuk setiap forecast baru untuk menghindari infinite recursion
            $this->processedComponents = [];

            // Track part yang diimport untuk force refresh daily stock nanti
            if (!in_array($part->id, $this->importedParts)) {
                $this->importedParts[] = $part->id;
            }

            // Proses komponen BOM dengan recursive processing (starting from depth 0)
            $this->processBomComponents($part, $forecast, $no, $isProduct5);

            // Update DailyStockLog dengan nilai stock_per_day yang baru berdasarkan min
            // Selalu update baik untuk data baru maupun data yang diupdate
            $this->updateDailyLogs($part, $min, $no, $isNewForecast);

            // Log successful processing (only occasionally to reduce memory)
            // $this->logProgress("Berhasil proses forecast untuk {$inv_id} - {$customer_name}");

        } catch (\Exception $e) {
            $this->logProgress("Baris $no: Error saat menyimpan forecast: " . $e->getMessage(), true);
        }
    }

    protected function processBomComponents(Part $product, Forecast $parentForecast, int $rowNo, bool $isProduct5Hierarchy = false, int $depth = 0)
    {
        // Limit depth untuk mencegah infinite recursion yang terlalu dalam
        if ($depth > 10) {
            $this->logProgress("Baris $rowNo: Maximum depth reached (10 levels) untuk product {$product->Inv_id}", true);
            return;
        }

        $bomComponents = $this->bomDataCache->where('product_id', $product->id);

        // Step 1: Kumpulkan dan totalkan semua component berdasarkan component_id
        $componentTotals = [];

        foreach ($bomComponents as $bom) {
            if (!$bom->component) {
                $this->logProgress("Baris $rowNo: Komponen dengan ID {$bom->component_id} tidak ditemukan.", true);
                continue;
            }

            $componentId = $bom->component_id;

            // Total quantity untuk component yang sama
            if (!isset($componentTotals[$componentId])) {
                $componentTotals[$componentId] = [
                    'component' => $bom->component,
                    'total_quantity' => 0,
                    'unit' => $bom->unit,
                    'bom_level' => $depth + 1 // Track BOM level untuk debugging
                ];
            }

            $componentTotals[$componentId]['total_quantity'] += (double) $bom->quantity;
        }

        // Log informasi component yang ditotalkan (tanpa spam)
        if (count($componentTotals) > 0) {
            $this->stats['components_processed']++;

            // Track max depth yang pernah dicapai
            if ($depth > $this->stats['max_bom_depth_reached']) {
                $this->stats['max_bom_depth_reached'] = $depth;
            }

            // Track deep cascade processing
            if ($depth > 0) {
                $this->stats['deep_cascade_processed']++;
            }

            // Log deep level processing untuk debugging
            // if ($depth > 0 && $this->verbosity === 'detailed') {
            //     $productInvId = $product->Inv_id ?? $product->id;
            //     $this->logProgress("[DEEP-BOM] Level {$depth}: Product {$productInvId} memiliki " . count($componentTotals) . " komponen", true);
            // }
        }

        // Step 2: Process setiap component dengan total quantity yang sudah dijumlahkan
        foreach ($componentTotals as $componentId => $componentData) {
            $component = $componentData['component'];
            $totalQuantity = $componentData['total_quantity'];
            $bomLevel = $componentData['bom_level'];

            // Create unique key untuk tracking recursion dengan depth
            $recursionKey = $componentId . '|' . $this->formatDate($parentForecast->forecast_month) . '|' . $product->id . '|' . $depth;

            // Cek untuk menghindari infinite recursion
            if (in_array($recursionKey, $this->processedComponents)) {
                continue; // Skip tanpa log untuk mengurangi spam
            }

            // Tandai sebagai sedang diproses
            $this->processedComponents[] = $recursionKey;

            // Hitung nilai komponen dengan total quantity - CASCADING CALCULATION
            $componentPoPcs = (int) ceil($parentForecast->PO_pcs * $totalQuantity);

            // Hitung frequensi_delivery untuk component berdasarkan parent product's frequency delivery
            // PERBAIKAN: Selalu ambil frequensi_delivery dari parent product, bukan dari hari_kerja
            $componentFreqDelivery = null;
            if ($parentForecast->frequensi_delivery && $parentForecast->frequensi_delivery > 0) {
                // Gunakan frequensi_delivery dari parent product
                $componentFreqDelivery = (int) ceil($parentForecast->frequensi_delivery * $totalQuantity);
            } else {
                // Jika parent product tidak punya frequensi_delivery, set ke 0
                $componentFreqDelivery = 0;
            }

            // Hitung min dan max berdasarkan logika yang sama dengan product
            // Min = PO_pcs ÷ frequensi_delivery (setelah di-count berdasarkan BOM)
            // Max = min × total_quantity (bukan × 3)
            $componentMin = 0;
            $componentMax = 0;

            if ($componentFreqDelivery && $componentFreqDelivery > 0) {
                $componentMin = (int) ceil($componentPoPcs / max($componentFreqDelivery, 1));
                $componentMax = (int) ceil($componentMin * $totalQuantity); // Max = min × count qty BOM
            } else {
                // Fallback jika tidak ada frequensi_delivery - gunakan hari kerja hanya untuk kalkulasi
                // tapi tetap set componentFreqDelivery = 0
                $componentMin = (int) ceil($componentPoPcs / max($parentForecast->hari_kerja, 1));
                $componentMax = (int) ceil($componentMin * $totalQuantity);
            }

            // Jika dalam hierarki Product 5 - cascading ke semua level
            if ($isProduct5Hierarchy) {
                $componentMin = $componentMin * 3;
                $componentMax = $componentMax * 3; // Tetap proporsional dengan min
                // Untuk Product 5, frequensi_delivery juga dikali 3 jika ada
                if ($componentFreqDelivery) {
                    $componentFreqDelivery = (int) ceil($componentFreqDelivery * 3);
                }
            }

            // Create cache key untuk component forecasting (tanpa product ID dan depth untuk akumulasi global)
            $cacheKey = $componentId . '|' . $this->formatDate($parentForecast->forecast_month);

            // Log detail cascading calculation untuk debugging level dalam
            // if ($depth > 0 && $this->verbosity === 'detailed' && $this->stats['deep_cascade_processed'] <= 5) {
            //     $componentInvId = optional($component)->Inv_id ?? $componentId;
            //     $parentInvId = $product->Inv_id ?? $product->id;
            //     $this->logProgress("[CASCADE-L{$bomLevel}] {$parentInvId} → {$componentInvId}: qty={$totalQuantity}, PO={$componentPoPcs}, min={$componentMin}", true);
            // }

            // Akumulasi data komponen untuk menangani component yang digunakan di multiple products
            if (isset($this->componentForecastsCache[$cacheKey])) {
                // Akumulasi SEMUA nilai jika component sudah ada (termasuk frequensi_delivery)
                $oldPoPcs = $this->componentForecastsCache[$cacheKey]['po_pcs'];
                $oldFreqDelivery = $this->componentForecastsCache[$cacheKey]['frequensi_delivery'] ?? 0;
                $oldTotalBomQty = $this->componentForecastsCache[$cacheKey]['total_bom_qty'] ?? 0; // Track total BOM quantity

                $newPoPcs = $oldPoPcs + $componentPoPcs;
                $newFreqDelivery = $oldFreqDelivery + ($componentFreqDelivery ?? 0); // Akumulasi frequensi_delivery
                $newTotalBomQty = $oldTotalBomQty + $totalQuantity; // Akumulasi total BOM quantity

                // Recalculate min dan max berdasarkan total yang terakumulasi
                $newMin = 0;
                $newMax = 0;
                if ($newFreqDelivery > 0) {
                    $newMin = (int) ceil($newPoPcs / max($newFreqDelivery, 1));
                    // Max = min × TOTAL BOM quantity dari semua usage
                    $newMax = (int) ceil($newMin * max($newTotalBomQty, 1));
                } else {
                    // Fallback calculation
                    $newMin = $this->componentForecastsCache[$cacheKey]['min'] + $componentMin;
                    $newMax = $this->componentForecastsCache[$cacheKey]['max'] + $componentMax;
                }

                // Update cache dengan nilai terakumulasi
                $this->componentForecastsCache[$cacheKey]['po_pcs'] = $newPoPcs;
                $this->componentForecastsCache[$cacheKey]['frequensi_delivery'] = $newFreqDelivery;
                $this->componentForecastsCache[$cacheKey]['min'] = $newMin;
                $this->componentForecastsCache[$cacheKey]['max'] = $newMax;
                $this->componentForecastsCache[$cacheKey]['total_bom_qty'] = $newTotalBomQty; // Simpan total BOM qty

                // Log akumulasi dengan depth information (limit log untuk mengurangi spam)
                // if ($this->verbosity === 'detailed' && $depth <= 1 && $this->componentUsageCounter[$cacheKey] <= 3) {
                //     $componentInvId = optional($component)->Inv_id ?? $componentId;
                //     $this->logProgress("[ACCUM-L{$bomLevel}] Component {$componentInvId}: PO:{$oldPoPcs}+{$componentPoPcs}={$newPoPcs}, used by {$this->componentUsageCounter[$cacheKey]} products", true);
                // }                // Increment usage counter
                $this->componentUsageCounter[$cacheKey] = ($this->componentUsageCounter[$cacheKey] ?? 1) + 1;            } else {
                // Inisialisasi data komponen baru
                $this->componentForecastsCache[$cacheKey] = [
                    'id_part' => $componentId,
                    'forecast_month' => $parentForecast->forecast_month,
                    'id_work' => $parentForecast->id_work,
                    'hari_kerja' => $parentForecast->hari_kerja,
                    'frequensi_delivery' => $componentFreqDelivery, // Gunakan nilai yang dihitung berdasarkan parent product
                    'po_pcs' => $componentPoPcs,
                    'min' => $componentMin,
                    'max' => $componentMax,
                    'issued_at' => $parentForecast->issued_at,
                    'parent_forecast_id' => $parentForecast->id,
                    'is_product5_hierarchy' => $isProduct5Hierarchy,
                    'bom_quantity' => $totalQuantity, // Gunakan total quantity yang sudah dijumlahkan
                    'bom_unit' => $componentData['unit'],
                    'total_bom_qty' => $totalQuantity, // Track total BOM quantity untuk kalkulasi max
                    'bom_level' => $bomLevel // Track depth level
                ];

                // Initialize usage counter
                $this->componentUsageCounter[$cacheKey] = 1;

                // Log component baru dengan level info (limit untuk mengurangi spam)
                // if ($depth > 0 && $this->verbosity === 'detailed' && $this->stats['deep_cascade_processed'] <= 5) {
                //     $componentInvId = optional($component)->Inv_id ?? $componentId;
                //     $this->logProgress("[NEW-L{$bomLevel}] Component {$componentInvId}: PO={$componentPoPcs}, min={$componentMin}", true);
                // }
            }

            // DEEP RECURSIVE: Cek apakah component ini juga merupakan product yang punya sub-component
            // Ini adalah kunci untuk deep cascading - setiap component bisa jadi product lagi
            $subBomComponents = $this->bomDataCache->where('product_id', $componentId);
            if ($subBomComponents->count() > 0) {
                // Create temporary forecast model untuk recursive processing dengan nilai yang sudah dihitung
                $tempComponentForecast = new Forecast();
                $tempComponentForecast->PO_pcs = $componentPoPcs;
                $tempComponentForecast->min = $componentMin;
                $tempComponentForecast->max = $componentMax;
                $tempComponentForecast->forecast_month = $parentForecast->forecast_month;
                $tempComponentForecast->id_work = $parentForecast->id_work;
                $tempComponentForecast->hari_kerja = $parentForecast->hari_kerja;
                $tempComponentForecast->frequensi_delivery = $componentFreqDelivery;
                $tempComponentForecast->issued_at = $parentForecast->issued_at;
                $tempComponentForecast->id = $parentForecast->id;

                // RECURSIVE CALL dengan increment depth untuk deep cascading
                $this->processBomComponents($component, $tempComponentForecast, $rowNo, $isProduct5Hierarchy, $depth + 1);
            }

            // Remove dari processed untuk memungkinkan processing di context lain
            if (($key = array_search($recursionKey, $this->processedComponents)) !== false) {
                unset($this->processedComponents[$key]);
            }
        }
    }

    protected function updateDailyLogs(Part $part, int $min, int $rowNo, bool $isNewForecast = false)
    {
        if ($min <= 0) {
            // Skip jika min tidak valid
            return;
        }

        try {
            // Selalu update daily stock logs untuk part ini, termasuk yang Total_qty = 0
            // Ini memastikan semua daily stock records terpengaruh oleh import baru
            $totalDailyStockRecords = DailyStockLog::where('id_inventory', $part->id)->count();

            if ($totalDailyStockRecords === 0) {
                // Tidak ada daily stock records untuk part ini
                return;
            }

            // Batch update dengan limit yang disesuaikan dengan throttling config
            $batchSize = $this->throttlingConfig['enabled'] ? 25 : 50;
            $updated = 0;
            $attempts = 0;
            $maxAttempts = 20; // Increase max attempts for larger datasets

            // Update SEMUA DailyStockLog records untuk part ini (termasuk yang Total_qty = 0)
            // Formula: stock_per_day = Total_qty / min (dengan handling untuk Total_qty = 0)
            do {
                $affectedRows = DailyStockLog::where('id_inventory', $part->id)
                    ->limit($batchSize)
                    ->update([
                        'stock_per_day' => DB::raw('CASE
                            WHEN Total_qty > 0 THEN Total_qty / ' . $min . '
                            ELSE 0
                        END')
                    ]);

                $updated += $affectedRows;
                $attempts++;

                // Throttling sleep untuk mengurangi beban database
                if ($affectedRows > 0 && $this->throttlingConfig['enabled']) {
                    usleep(20000); // 20ms antar batch daily logs
                } elseif ($affectedRows > 0) {
                    usleep(5000); // 5ms default untuk performance
                }

                // Safety check untuk mencegah infinite loop
                if ($attempts >= $maxAttempts) {
                    $this->logs[] = "[STOCK WARNING] Part {$part->Inv_id}: Reached max attempts ({$maxAttempts}), stopping update";
                    break;
                }

            } while ($affectedRows === $batchSize && $attempts < $maxAttempts);

            // Enhanced logging dengan informasi yang lebih detail
            if ($updated > 0) {
                // Update statistik
                if (!isset($this->stats['daily_stock_updated'])) {
                    $this->stats['daily_stock_updated'] = 0;
                }
                $this->stats['daily_stock_updated'] += $updated;

                // Log untuk tracking setiap import baru
                // if ($isNewForecast || $this->verbosity === 'detailed') {
                //     $action = $isNewForecast ? 'NEW' : 'UPD';
                //     if ($this->stats['daily_stock_updated'] <= 5 || $this->verbosity === 'detailed') {
                //         $this->logProgress("[STOCK-{$action}] Part {$part->Inv_id}: {$updated} daily records updated (min: {$min})", true);
                //     }
                // } else {
                //     // Log minimal untuk update existing forecast
                //     if ($this->verbosity !== 'minimal' && $this->stats['daily_stock_updated'] <= 3) {
                //         // $this->logProgress("[STOCK-UPD] Part {$part->Inv_id}: {$updated} daily records refreshed", true);
                //     }
                // }

                // Log summary untuk setiap 100 records yang diupdate
                // if ($this->stats['daily_stock_updated'] % 100 === 0 && $this->verbosity !== 'minimal') {
                //     $this->logProgress("[STOCK-PROGRESS] {$this->stats['daily_stock_updated']} total daily stock records updated", true);
                // }
            }

        } catch (\Exception $e) {
            $this->logProgress("Baris $rowNo: Gagal update DailyStockLog untuk part {$part->Inv_id}: " . $e->getMessage(), true);

            // Track error statistik
            if (!isset($this->stats['daily_stock_errors'])) {
                $this->stats['daily_stock_errors'] = 0;
            }
            $this->stats['daily_stock_errors']++;
        }
    }
}