<?php

namespace App\Http\Controllers;

use App\Events\DailyStoReminderEvent;
use App\Exports\DailyStockExport;
use App\Imports\DailyStockImport;
use App\Models\BoxComplete;
use App\Models\BoxUncomplete;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DailyStockLog;
use App\Models\Forecast;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\DailyStoImportBroadcastEvent;

class DailyStockLogController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyStockLog::with(['part.customer', 'user', 'areaHead'])
            ->orderBy('updated_at', 'desc');

        // Apply standard filters (no changes)
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category);
            });
        }

        if ($request->plant) {
            $plantParam = $request->plant;
            if (strpos($plantParam, ',') !== false) {
                $plantArray = array_map('trim', explode(',', $plantParam));
                $query->whereHas('areaHead.plant', function ($q) use ($plantArray) {
                    $q->whereIn('id', $plantArray);
                }   );
            } else {
                $query->whereHas('areaHead.plant', function ($q) use ($plantParam) {
                    $q->where('id', $plantParam);
                });
            }
        }

        // Apply customer filter
        if ($request->customer) {
            $query->whereHas('part.customer', function ($q) use ($request) {
                $q->where('username', $request->customer);
            });
        }

        // Apply area filter (hidden, only if present in request)
        if ($request->filled('area')) {
            $areaParam = $request->area;
            if (strpos($areaParam, ',') !== false) {
                $areaArray = array_map('trim', explode(',', $areaParam));
                $query->whereHas('areaHead', function ($q) use ($areaArray) {
                    $q->whereIn('id', $areaArray);
                });
            } else {
                $query->whereHas('areaHead', function ($q) use ($areaParam) {
                    $q->where('id', $areaParam);
                });
            }
        }

        // Apply date filter
        if ($request->date) {
            Log::info('DailyStockLog: Date filter received', ['date' => $request->date]); // Debug log
            $query->whereDate('date', $request->date);
        }

        // Handle inv_id filter - this will take priority if coming from the chart
        if ($request->filled('inv_id')) {
            $invIds = $request->inv_id;
            Log::info('DailyStockLog: inv_id filter received', ['inv_id' => $invIds]); // Debug log

            // Jika inv_id mengandung kombinasi inv_id|area|plant (dari dashboard)
            if (strpos($invIds, '|') !== false || (is_array($invIds) && strpos(implode(',', $invIds), '|') !== false)) {
                // Bisa berupa string dipisah koma atau array
                $combos = is_array($invIds) ? $invIds : explode(',', $invIds);
                $query->where(function($q) use ($combos) {
                    foreach ($combos as $combo) {
                        $parts = explode('|', $combo);
                        $inv = $parts[0] ?? null;
                        $area = $parts[1] ?? null;
                        $plant = $parts[2] ?? null;
                        if ($inv && $area && $plant) {
                            $q->orWhere(function($sub) use ($inv, $area, $plant) {
                                $sub->whereHas('part', function($q2) use ($inv) {
                                    $q2->where('Inv_id', $inv);
                                })
                                ->whereHas('areaHead', function($q3) use ($area, $plant) {
                                    $q3->where('id', $area);
                                    $q3->whereHas('plant', function($q4) use ($plant) {
                                        $q4->where('name', $plant);
                                    });
                                });
                            });
                        }
                    }
                });
                // Tambahkan filter kategori stock_per_day jika dari dashboard dan stock_category ada
                if ($request->from_dashboard && $request->filled('stock_category')) {
                    $category = $request->stock_category;
                    $query->where(function ($q) use ($category) {
                        switch ($category) {
                            case '>3':
                                $q->whereRaw('ROUND(stock_per_day, 1) > 3');
                                break;
                            case '3':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 2.6 AND ROUND(stock_per_day, 1) <= 3');
                                break;
                            case '2.5':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 2.1 AND ROUND(stock_per_day, 1) <= 2.5');
                                break;
                            case '2':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 1.6 AND ROUND(stock_per_day, 1) <= 2');
                                break;
                            case '1.5':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 1.3 AND ROUND(stock_per_day, 1) <= 1.6');
                                break;
                            case '1':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 0.6 AND ROUND(stock_per_day, 1) <= 1.2');
                                break;
                            case '0.5':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 0.3 AND ROUND(stock_per_day, 1) <= 0.5');
                                break;
                            case '0':
                                $q->whereRaw('ROUND(stock_per_day, 1) >= 0 AND ROUND(stock_per_day, 1) <= 0.2');
                                break;
                        }
                    });
                }
            } else if (strpos($invIds, ',') !== false) {
                // Multiple Inv IDs - these are specific to the chart category
                $invIdArray = array_map('trim', explode(',', $invIds));
                Log::info('DailyStockLog: Using multiple Inv IDs', ['inv_id_array' => $invIdArray]); // Debug log
                $query->whereHas('part', function ($q) use ($invIdArray) {
                    $q->whereIn('Inv_id', $invIdArray);
                });
            } else {
                // Single Inv ID (existing logic)
                Log::info('DailyStockLog: Using single Inv ID with LIKE', ['inv_id' => $invIds]); // Debug log
                $query->whereHas('part', function ($q) use ($request) {
                    $q->where('Inv_id', 'LIKE', '%'.$request->inv_id.'%');
                });
            }
        }
        // Selalu filter stock_category jika from_dashboard dan stock_category ada
        if ($request->from_dashboard && $request->filled('stock_category')) {
            $category = $request->stock_category;
            Log::info('DailyStockLog: Filtering by stock_category (selalu cek)', ['category' => $category]);
            $query->where(function ($q) use ($category) {
                switch ($category) {
                    case '>3':
                        $q->whereRaw('ROUND(stock_per_day, 1) > 3');
                        break;
                    case '3':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 2.6 AND ROUND(stock_per_day, 1) <= 3');
                        break;
                    case '2.5':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 2.1 AND ROUND(stock_per_day, 1) <= 2.5');
                        break;
                    case '2':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 1.6 AND ROUND(stock_per_day, 1) <= 2');
                        break;
                    case '1.5':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 1.3 AND ROUND(stock_per_day, 1) <= 1.6');
                        break;
                    case '1':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 0.6 AND ROUND(stock_per_day, 1) <= 1.2');
                        break;
                    case '0.5':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 0.3 AND ROUND(stock_per_day, 1) <= 0.5');
                        break;
                    case '0':
                        $q->whereRaw('ROUND(stock_per_day, 1) >= 0 AND ROUND(stock_per_day, 1) <= 0.2');
                        break;
                }
            });
        }

        // Hide khusus kategori Raw Material yang status-nya FUNSAI
        $query->where(function($q) {
            $q->whereHas('part.category', function($cat) {
                $cat->where('name', '!=', 'Raw Material');
            })
            ->orWhere(function($sub) {
                $sub->whereHas('part.category', function($cat) {
                    $cat->where('name', 'Raw Material');
                })
                ->where('status', '!=', 'FUNSAI');
            });
        });

        $categories = Category::all();
        $customers = Customer::orderBy('username')->get();
        $perPage = $request->get('per_page', 25);
        $dailyStockLogs = $query->paginate($perPage);
        $plants = Plant::orderBy('name')->get();

        $partIds = collect($dailyStockLogs->items())->pluck('part_id')->unique()->filter()->toArray();

        // Preload forecasts grouped by part ID and month
        $forecasts = Forecast::whereIn('id_part', $partIds)
            ->get()
            ->groupBy([
                'id_part',
                function ($item) {
                    return Carbon::parse($item->forecast_month)->format('Y-m');
                },
            ]);

         // buat email pengingat jika belum upload stok harian
        // Trigger event hanya jika jam 10-18 (menit berapapun)
        $now = Carbon::now();
        if ($now->hour >= 10 && $now->hour <= 18) {
            // Pastikan hanya trigger pada jam genap (10, 12, 14, 16, 18)
            $allowedHours = [10, 12, 14, 16, 18];
            if (in_array($now->hour, $allowedHours)) {
                event(new DailyStoReminderEvent($allowedHours));
            }
        }

        return view('Daily_stok.index', [
            'dailyStockLogs' => $dailyStockLogs,
            'statuses' => ['OK', 'NG', 'VIRGIN', 'FUNSAI'],
            'categories' => $categories,
            'customers' => $customers,
            'forecasts' => $forecasts,
            'plants' => $plants,
            'from_dashboard' => $request->from_dashboard,
            'stock_category' => $request->stock_category,
        ]);
    }

    /**
     * Get forecast value (min or max) for a log
     */
    private function getForecastValue($log, $forecasts, $type)
    {
        if (! $log->part || ! $log->date) {
            return $this->getDefaultValue($type);
        }

        $partId = $log->part->id;
        $logMonth = Carbon::parse($log->date)->format('Y-m');

        if (isset($forecasts[$partId][$logMonth])) {
            $forecast = $forecasts[$partId][$logMonth][0];

            return $forecast->{$type} ?? $this->getDefaultValue($type);
        }

        return $this->getDefaultValue($type);
    }

    /**
     * Get default value for min/max
     */
    private function getDefaultValue($type)
    {
        return '-';
    }

    public function export(Request $request)
    {
        // Cek jumlah data sebelum export
        $query = \App\Models\DailyStockLog::query();
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->category) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category);
            });
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->customer) {
            $query->whereHas('part.customer', function ($q) use ($request) {
                $q->where('username', $request->customer);
            });
        }
        if ($request->plant) {
            $query->whereHas('areaHead.plan', function ($q) use ($request) {
                $q->where('id', $request->plant);
            });
        }
        if ($request->inv_id) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inv_id . '%');
            });
        }
        $total = $query->count();
        if ($total > 5000) {
            return redirect()->route('daily-stock.index')->with('error', 'Export gagal: Data terlalu banyak (' . $total . ' baris). Silakan gunakan filter untuk memperkecil data yang diexport (max 5000 baris).');
        }
        try {
            return Excel::download(
                new DailyStockExport($request->status, $request->category, $request->date, $request->customer, $request->plant, $request->inv_id),
                'daily_stock_export.xlsx'
            );
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Maximum execution time')) {
                return redirect()->route('daily-stock.index')->with('error', 'Export gagal: Data terlalu banyak, proses terlalu lama. Silakan gunakan filter status, kategori, tanggal, atau plant untuk memperkecil data yang diexport.');
            }
            return redirect()->route('daily-stock.index')->with('error', 'Export gagal: ' . $message);
        }
    }

    /**
     * Hapus beberapa daily stock logs.
     */
    public function deletedailystock(Request $request)
    {
        try {
            $ids = explode(',', $request->ids);

            // Validasi bahwa user memiliki akses untuk menghapus
            if (empty($ids)) {
                return redirect()->back()->with('error', 'No items selected.');
            }

            // Ambil semua data yang akan dihapus beserta relasinya
            $reports = DailyStockLog::with(['part.inventory', 'boxComplete', 'boxUncomplete'])
                ->whereIn('id', $ids)
                ->get();

            DB::beginTransaction();

            foreach ($reports as $report) {
                $inventory = optional($report->part)->inventory;

                // Jika inventory tersedia, kurangi act_stock
                if ($inventory) {
                    $inventory->act_stock -= $report->Total_qty;
                    $inventory->save();
                }

                // Hapus relasi ke BoxComplete jika ada
                if ($report->id_box_complete) {
                    BoxComplete::where('id', $report->id_box_complete)->delete();
                }

                // Hapus relasi ke BoxUncomplete jika ada
                if ($report->id_box_uncomplete) {
                    BoxUncomplete::where('id', $report->id_box_uncomplete)->delete();
                }

                // Hapus log harian
                $report->delete();
            }

            DB::commit();

            return redirect()->route('daily-stock.index')
                ->with('success', count($reports).' Item yang dipilih telah berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete multiple daily stock reports: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }
    }

    /**
     * Import daily stock logs from an Excel file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv|max:2048',
        ]);

        $filePath = $request->file('file')->store('temp');

        DB::beginTransaction();
        try {
            // First pass - check for validation and duplicates
            $import = new DailyStockImport;
            $import->setPreviewMode(true);
            Excel::import($import, $filePath);

            // Cek apakah ada kolom status di file Excel
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($request->file('file')->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($request->file('file')->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $headerRowLower = null;
            for ($rowIdx = 1; $rowIdx <= $highestRow; $rowIdx++) {
                $rowValues = $sheet->rangeToArray('A'.$rowIdx.':'.$sheet->getHighestColumn().$rowIdx)[0];
                $rowLower = array_map('strtolower', $rowValues);
                if (in_array('status', $rowLower) && in_array('date', $rowLower)) {
                    $headerRowLower = $rowLower;
                    break;
                }
            }
            if (! $headerRowLower) {
                DB::rollBack();
                Storage::delete($filePath);
                // Tampilkan sweetalert jika kolom status tidak ada
                return redirect()
                    ->route('daily-stock.index')
                    ->with('swal_error', 'Anda belum mengisi kolom <b>status</b> pada file Excel. Silakan download format import terbaru dan lengkapi kolom status!');
            }

            // Get skipped rows for validation errors
            $skippedRows = $import->getSkippedRows();

            // Check for CRITICAL validation errors (plant/area/qty issues)
            $criticalErrors = array_filter($skippedRows, function ($row) {
                return strpos($row['reason'], 'Plant') !== false ||
                    strpos($row['reason'], 'Area') !== false ||
                    strpos($row['reason'], 'Quantity tidak boleh negatif') !== false ||
                    strpos($row['reason'], 'Format tanggal tidak valid') !== false ||
                    strpos($row['reason'], 'Kolom wajib') !== false;
            });

            // NON-CRITICAL errors (inv_id not found) will be skipped but import continues
            $nonCriticalErrors = array_filter($skippedRows, function ($row) {
                return strpos($row['reason'], 'Part dengan ID') !== false;
            });

            if (! empty($criticalErrors)) {
                DB::rollBack();
                Storage::delete($filePath);

                // Format error messages for display
                $errorMessages = array_map(function ($row) {
                    return 'Baris: '.json_encode($row['row']).' - '.$row['reason'];
                }, $criticalErrors);

                // Limit to 5 errors to avoid overwhelming the user
                $displayErrors = array_slice($errorMessages, 0, 5);
                $additionalErrors = count($errorMessages) > 5 ? (count($errorMessages) - 5) : 0;

                $message = 'Validasi gagal:<br><br>'.implode('<br>', $displayErrors);
                if ($additionalErrors > 0) {
                    $message .= "<br><br>Dan {$additionalErrors} error lainnya...";
                }

                return redirect()
                    ->route('daily-stock.index')
                    ->with('error', $message)
                    ->with('error_details', $errorMessages);
            }

            // Store results in session
            session([
                'import_data' => [
                    'file_path' => $filePath,
                    'imported' => $import->getImportedCount(),
                    'duplicates' => $import->getExistingData(),
                    'skipped' => count($nonCriticalErrors), // Only count non-critical skips
                    'total_rows' => $import->getImportedCount() + count($skippedRows),
                ],
            ]);

            DB::commit();

            // If duplicates found, show confirmation
            if ($import->hasDuplicates()) {
                $duplicateCount = count($import->getExistingData());
                $duplicateMessage = "Ditemukan {$duplicateCount} data dengan part, tanggal, dan area yang sama. ";
                $duplicateMessage .= 'Apa yang ingin Anda lakukan dengan data duplikat?';

                // Add info about skipped parts if any
                if (! empty($nonCriticalErrors)) {
                    $skippedCount = count($nonCriticalErrors);
                    $duplicateMessage .= "<br><br>Catatan: {$skippedCount} baris dengan part tidak ditemukan akan di-skip.";
                }

                return redirect()
                    ->route('daily-stock.index')
                    ->with('warning', $duplicateMessage)
                    ->with('confirm_duplicates', true);
            }

            // No duplicates - process immediately
            return $this->processImmediateImport();

        } catch (\Exception $e) {
            DB::rollBack();
            Storage::delete($filePath);
            Log::error('Import failed: '.$e->getMessage());

            return redirect()
                ->route('daily-stock.index')
                ->with('error', 'Import gagal: '.$e->getMessage());
        }
    }

    /**
     * Process import without duplicates.
     */
    public function processImmediateImport()
    {
        $importData = session('import_data');

        if (! $importData) {
            return redirect()
                ->route('daily-stock.index')
                ->with('error', 'Data import tidak ditemukan.');
        }

        DB::beginTransaction();
        try {
            $import = new DailyStockImport;
            Excel::import($import, $importData['file_path']);

            DB::commit();
            Storage::delete($importData['file_path']);
            session()->forget('import_data');

            $successMessage = 'Import berhasil!';
            $successMessage .= ' | Data diimport: '.$importData['imported'];

            if ($importData['skipped'] > 0) {
                $successMessage .= ' | Data part tidak ditemukan (di-skip): '.$importData['skipped'];
            }

            // Kirim broadcast email untuk kategori yang baru saja diimport
            $this->sendImportBroadcastForTodaysImport();

            return redirect()
                ->route('daily-stock.index')
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Storage::delete($importData['file_path']);
            session()->forget('import_data');
            Log::error('Import error: '.$e->getMessage());

            return redirect()
                ->route('daily-stock.index')
                ->with('error', 'Gagal memproses import: '.$e->getMessage());
        }
    }

        /**
     * Process import with duplicate handling (skip/overwrite).
     */
    public function processImport(Request $request)
    {
        $importData = session('import_data');

        if (! $importData) {
            return redirect()
                ->route('daily-stock.index')
                ->with('error', 'Data import tidak ditemukan.');
        }

        DB::beginTransaction();
        try {
            $import = new DailyStockImport;
            $action = $request->input('duplicate_action', 'skip'); // 'skip' or 'overwrite'
            $import->setDuplicateAction($action);

            Excel::import($import, $importData['file_path']);

            DB::commit();
            Storage::delete($importData['file_path']);
            session()->forget('import_data');

            $message = 'Import selesai!';
            $message .= ' | Data diimport: '.$importData['imported'];

            if (! empty($importData['duplicates'])) {
                if ($action === 'skip') {
                    $message .= ' | Data duplikat di-skip: '.count($importData['duplicates']);
                }
                // elseif ($action === 'overwrite') {
                //     $message .= " | Data duplikat di-update: " . count($importData['duplicates']);
                // }
            }
             
            // Kirim broadcast email untuk kategori yang baru saja diimport
            $this->sendImportBroadcastForTodaysImport();

            if ($importData['skipped'] > 0) {
                $message .= ' | Data part tidak ditemukan (di-skip): '.$importData['skipped'];
            }

            return redirect()
                ->route('daily-stock.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Storage::delete($importData['file_path']);
            session()->forget('import_data');
            Log::error('Process import error: '.$e->getMessage());

            return redirect()
                ->route('daily-stock.index')
                ->with('error', 'Gagal memproses: '.$e->getMessage());
        }
    }


     private function sendImportBroadcastForTodaysImport()
    {
        try {
            $currentUserId = auth()->id();
            $importTime = now();
            
            // Get the latest timestamp for the current user's imports
            $latestTimestamp = DailyStockLog::where('prepared_by', $currentUserId)
                ->max('created_at');
                
            if (!$latestTimestamp) {
                Log::info('Tidak ada data import terbaru untuk dikirim notifikasi');
                return;
            }
            
            // Get only records from the latest batch (exact same timestamp)
            $recentLogs = DailyStockLog::with(['user', 'part.category', 'areaHead.plant'])
                ->where('created_at', $latestTimestamp)
                ->where('prepared_by', $currentUserId)
                ->get();
        
            if ($recentLogs->isEmpty()) {
                Log::info('Tidak ada data import terbaru untuk dikirim notifikasi');
                return;
            }
            
            // Group by plant
            $grouped = $recentLogs->groupBy(function($log) {
                return optional(optional($log->areaHead)->plant)->name ?: '';
            });
            
            Log::info('Memproses notifikasi broadcast import', [
                'jumlah_grup' => $grouped->count(),
                'total_log_import_terbaru' => $recentLogs->count(),
                'import_time' => $latestTimestamp
            ]);
            
            foreach ($grouped as $plant => $logs) {
                if (empty($plant)) {
                    Log::warning('Melewati notifikasi broadcast - tidak ada plant yang didefinisikan');
                    continue;
                }
                
                // Debug: log all raw category names from the import
                $rawCategories = [];
                foreach ($logs as $log) {
                    $rawCat = optional(optional($log->part)->category)->name;
                    if ($rawCat) {
                        $rawCategories[] = $rawCat;
                    }
                }
                
                Log::debug('Kategori mentah ditemukan dalam batch impor', [
                    'plant' => $plant,
                    'raw_categories' => $rawCategories,
                    'count' => count($rawCategories)
                ]);
                
                // Get unique categories from logs that were just imported (case-preserved)
                $categories = [];
                foreach ($logs as $log) {
                    $categoryName = optional(optional($log->part)->category)->name;
                    if ($categoryName && !$this->categoryExists($categories, $categoryName)) {
                        $categories[] = $categoryName;
                    }
                }
                
                if (count($categories) > 0) {
                    $preparedBy = optional($logs->first()->user)->username ?? auth()->user()->username ?? 'System';
                    
                    Log::info('Memicu event broadcast import', [
                        'preparedBy' => $preparedBy,
                        'plant' => $plant,
                        'categories' => $categories, 
                        'jumlah_log' => $logs->count(),
                        'jumlah_kategori' => count($categories),
                        'dari_file_import' => true
                    ]);
                    
                    // Make sure we're passing an array of categories to the event
                    event(new DailyStoImportBroadcastEvent(
                        $preparedBy, 
                        $categories,
                        $plant, 
                        strtotime($latestTimestamp)
                    ));
                } else {
                    Log::warning('Tidak ada kategori yang ditemukan untuk grup import ini', [
                        'plant' => $plant
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error saat mengirim notifikasi broadcast import: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Check if a category exists in the array (case-insensitive)
     */
    private function categoryExists(array $categories, string $categoryName): bool
    {
        $lowerCategory = strtolower(trim($categoryName));
        foreach ($categories as $category) {
            if (strtolower(trim($category)) === $lowerCategory) {
                return true;
            }
        }
        return false;
    }
}