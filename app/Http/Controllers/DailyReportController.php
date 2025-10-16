<?php

namespace App\Http\Controllers;

use App\Models\HeadArea;
use App\Models\Inventory;
use App\Models\DailyStockLog;
use App\Models\Part;
use App\Models\BoxComplete;
use App\Models\BoxUncomplete;
use Illuminate\Http\Request;
use App\Models\Forecast;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use App\Models\Plant;
use App\Models\Area;
use App\Models\HeaderArea;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
class DailyReportController extends Controller
{
    //get halama utama
    public function index()
    {
        return view('daily_report.index');
    }

    // halaman untuk buat isi qty sto
    public function form($inventory_id)
    {
        $inventory = Part::with(['customer', 'category', 'plant', 'area', 'inventories', 'package'])
            ->find($inventory_id);

        if ($inventory) {
            $plants = Plant::all();

            // Ambil data dari session login dengan casting ke integer
            $defaultPlantId = (int) session('selected_plan_id');
            $defaultAreaId = (int) session('selected_area_id');
            $defaultPlantName = session('selected_plan_name');
            $defaultAreaName = session('selected_area_name');

            // Ambil semua HeadArea untuk dropdown dengan relasi plant dan cast id ke integer
            $headAreas = HeadArea::with('plant')->get()->map(function ($headArea) {
                return (object) [
                    'id' => (int) $headArea->id,
                    'nama_area' => $headArea->nama_area,
                    'id_plan' => (int) $headArea->id_plan,
                    'plant_name' => $headArea->plant->name ?? ''
                ];
            });

            $areas = Area::all(); // Tetap ambil Area biasa jika diperlukan

            // Buat mapping plant-area untuk JavaScript dengan casting ke integer
            $plantAreas = Plant::with('headAreas')->get()->mapWithKeys(function ($plant) {
                return [
                    (int) $plant->id => $plant->headAreas->pluck('id')->map(function ($id) {
                        return (int) $id;
                    })->toArray()
                ];
            })->toArray();

            // Data session untuk view dan JavaScript dengan casting ke integer
            $sessionData = [
                'selected_plan_id' => $defaultPlantId,
                'selected_area_id' => $defaultAreaId,
                'selected_plan_name' => $defaultPlantName,
                'selected_area_name' => $defaultAreaName
            ];

            return view('daily_report.form', compact(
                'inventory',
                'plants',
                'headAreas',
                'areas',
                'plantAreas',
                'sessionData'
            ));
        }

        return back()->with('error', 'Inventory tidak ditemukan. Silakan coba lagi.');
    }

    // Proses scan Inventory ID get daily report
    public function scan(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|string' // 'inventory_id' di sini adalah Inv_id dari hasil scan
        ]);

        $inv_id = $request->inventory_id;
        if (strlen($inv_id) >= 11) {
            $inv_id = substr($inv_id, 0, -11);
        }
        // dd($inv_id);

        // Ambil satu part dengan Inv_id yang sesuai
        $inventoryPart = Part::where('Inv_id', $inv_id)
            ->with(['customer', 'category', 'plant', 'area', 'inventories'])
            ->first(); // Mengubah get() menjadi first()

        if (!$inventoryPart) { // Jika null (tidak ditemukan)
            return back()->with('error', 'Inventory ID tidak ditemukan.');
        }

        // Redirect ke metode form dengan primary key (id) dari part yang ditemukan
        return redirect()->route('sto.edit.report', ['inventory_id' => $inventoryPart->id]);
    }

    /**
     * Proses simpan data harian dengan penanganan jaringan lambat
     */
    public function storecreate(Request $request, $inventory_id)
    {
        // Set timeout yang lebih panjang untuk sesi PHP jika perlu
        set_time_limit(300); // 5 menit, sesuaikan sesuai kebutuhan
        
        // Tambahkan identifikasi request unik untuk mencegah duplikasi submission
        $requestId = $request->input('request_id', uniqid());
        $requestCacheKey = "daily_report_submission_{$requestId}";
        
        // Cek apakah request dengan ID ini sudah diproses sebelumnya (debouncing)
        if (Cache::has($requestCacheKey)) {
            return redirect()->route('dailyreport.index')
                ->with('berhasil', 'Data berhasil disimpan')
                ->with('report', Cache::get($requestCacheKey));
        }
        
        // Ambil data Part beserta relasi inventory
        $part = Part::with('inventory', 'plant')->findOrFail($inventory_id);

        // Validasi input dari form
        $data = $request->validate([
            'status' => 'required|string',
            'qty_per_box' => 'nullable|integer',
            'qty_box' => 'nullable|integer',
            'total' => 'nullable|integer',
            'qty_per_box_2' => 'nullable|integer',
            'qty_box_2' => 'nullable|integer',
            'total_2' => 'nullable|integer',
            'grand_total' => 'required|integer',
            'issued_date' => 'required|date',
            'prepared_by' => 'required|integer',
            'plant_id' => 'required|exists:tbl_plan,id',
            'area_id' => 'required|exists:tbl_head_area,id',
        ]);


        // Trigger validasi berdasarkan area dan user
        $currentAreaId = $data['area_id'];
        $currentUserId = $data['prepared_by'];

        // Cek apakah ada log untuk part ini di area yang sama pada tanggal yang sama
        $existingAreaLog = DailyStockLog::where('id_inventory', $part->id)
            ->where('id_area_head', $currentAreaId)
            ->whereDate('date', Carbon::parse($data['issued_date']))
            ->first();

        if ($existingAreaLog) {
            // Jika ada log di area yang sama
            if ($existingAreaLog->prepared_by == $currentUserId) {
                // Case 1: Area sama, user sama - tidak bisa double input
                return redirect()->back()
                    ->with('errorSTO', 'Anda sudah melakukan input untuk part ini di area yang sama pada tanggal tersebut.')
                    ->with('edit_log_id', $existingAreaLog->id)
                    ->withInput();
            } else {
                // Case 3: Area sama, user berbeda - ditolak
                return redirect()->back()
                    ->with('errorSTO', 'Part ini sudah diinput oleh user lain di area yang sama pada tanggal tersebut. Silakan koordinasi dengan user tersebut.')
                    ->with('edit_log_id', $existingAreaLog->id)
                    ->withInput();
            }
        }

        // Gunakan transaction untuk memastikan semua operasi database berhasil atau gagal secara bersama
        try {
            DB::beginTransaction();
            
            $issuedDate = Carbon::parse($data['issued_date']);
            $grandTotal = (int) $data['grand_total'];
            
            // Persiapkan data untuk BoxComplete
            $boxCompleteData = [
                'qty_per_box' => $data['qty_per_box'] ?? 0,
                'qty_box' => $data['qty_box'] ?? 0,
                'total' => $data['total'] ?? 0,
            ];
            
            // Insert BoxComplete
            $boxComplete = BoxComplete::create($boxCompleteData);
            
            // Persiapkan data untuk BoxUncomplete jika ada
            $boxUncompleteId = null;
            if (!empty($data['qty_per_box_2']) && !empty($data['qty_box_2'])) {
                $boxUncompleteData = [
                    'qty_per_box' => $data['qty_per_box_2'] ?? 0,
                    'qty_box' => $data['qty_box_2'] ?? 0,
                    'total' => $data['total_2'] ?? 0,
                ];
                $boxUncomplete = BoxUncomplete::create($boxUncompleteData);
                $boxUncompleteId = $boxUncomplete->id;
            }
            
            // Ambil forecast berdasarkan id_part
            $forecast = Forecast::where('id_part', $part->id)
                ->whereRaw('MONTH(forecast_month) = ?', [$issuedDate->month])
                ->whereRaw('YEAR(forecast_month) = ?', [$issuedDate->year])
                ->first();
            
            // Hitung stock_per_day sebagai float (bukan integer)
            $stock_per_day = ($forecast && $forecast->min > 0)
                ? (float) ($grandTotal / $forecast->min)
                : 0.0;
            
            // Persiapkan data untuk DailyStockLog
            $dailyLogData = [
                'id_inventory' => $part->id,
                'date' => $issuedDate->format('Y-m-d'),
                'id_box_complete' => $boxComplete->id,
                'id_box_uncomplete' => $boxUncompleteId,
                'id_area_head' => $data['area_id'],
                'Total_qty' => $grandTotal,
                'prepared_by' => $data['prepared_by'],
                'status' => $data['status'],
                'stock_per_day' => $stock_per_day,
                'created_at' => now(),
            ];
            
            // Insert DailyStockLog
            $dailyLog = DailyStockLog::create($dailyLogData);
            
            // Update package qty jika qty_per_box diisi dan package ada
            if (!empty($data['qty_per_box']) && $part->package) {
                $part->package->update(['qty' => $data['qty_per_box']]);
            }
            
            // Update inventory status
            if ($part->inventory) {
                // Ambil bulan dari issuedDate
                $month = $issuedDate->format('Y-m');
                $totalActual = DailyStockLog::where('id_inventory', $part->id)
                    ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                    ->sum('Total_qty');
                $planStock = $part->inventory->plan_stock ?? 0;
                $gap = $totalActual - $planStock;

                if ($planStock == 0 || $totalActual != $planStock) {
                    $remark = 'Abnormal';
                    if ($gap > 0) {
                        $note_remark = '+' . abs($gap);
                    } elseif ($gap < 0) {
                        $note_remark = '-' . abs($gap);
                    } else {
                        $note_remark = "Tidak ada stock";
                    }
                } else {
                    $remark = 'Normal';
                    $note_remark = null;
                }

                $part->inventory->update([
                    'act_stock' => $totalActual,
                    'remark' => $remark,
                    'note_remark' => $note_remark,
                ]);
            }
            
            // Simpan ID laporan di cache untuk menangani duplikasi submit (pada koneksi lambat)
            // Cache selama 30 menit, sesuaikan sesuai kebutuhan
            Cache::put($requestCacheKey, $dailyLog->id, now()->addMinutes(30));
            
            DB::commit();
            
            // Setelah commit, simpan hasil dalam cache untuk mempercepat akses berikutnya
            $this->cacheReportData($dailyLog->id);
            
            return redirect()->route('dailyreport.index')
                ->with('berhasil', 'Data berhasil disimpan')
                ->with('report', $dailyLog->id);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log error dengan informasi lebih detail untuk diagnosis
            Log::error("Error saving daily report: " . $e->getMessage(), [
                'user_id' => Auth::id(),
                'inventory_id' => $inventory_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage() . ' Silahkan coba lagi.')
                ->withInput();
        }
    }
    
    /**
     * Optimasi untuk pengambilan data laporan dengan cache
     */
    public function getPrintData($id)
    {
        // Coba ambil dari cache dulu untuk mempercepat
        $cacheKey = "daily_report_print_{$id}";

        if (Cache::has($cacheKey)) {
            return response()->json([
                'html' => Cache::get($cacheKey),
                'from_cache' => true
            ]);
        }
        
        $report = DailyStockLog::with([
            'part.inventory',
            'part.category',
            'part.plant',
            'part.area',
            'part.customer',
            'user',
            'areaHead.plan'
        ])->findOrFail($id);

        $renderer = new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($report->part->Inv_id ?? '-');
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        
        $html = view('pdf.daily_report', compact('report', 'qrCodeBase64'))->render();
        
        // Simpan dalam cache selama 1 jam
        Cache::put($cacheKey, $html, now()->addHour());

        return response()->json([
            'html' => $html,
            'from_cache' => false
        ]);
    }
    
    /**
     * Helper function untuk menyimpan data report dalam cache
     */
    private function cacheReportData($reportId)
    {
        $report = DailyStockLog::with([
            'part.inventory',
            'part.category',
            'part.plant',
            'part.area',
            'part.customer',
            'user',
            'areaHead.plan'
        ])->find($reportId);
        
        if ($report) {
            // Menyimpan data di cache server untuk optimasi performa
            $cacheKey = "daily_report_data_{$reportId}";
            Cache::put($cacheKey, $report, now()->addHours(2));
        }
    }

    // cari by partname atau part number
    public function search(Request $request)
    {
        $query = $request->input('query');
        $perPage = $request->input('per_page', 10); // Pagination untuk mengurangi beban data
        
        $results = Part::with('customer')
            ->where(function($q) use ($query) {
                $q->where('Part_name', 'like', '%' . $query . '%')
                  ->orWhere('Part_number', 'like', '%' . $query . '%');
            })
            ->paginate($perPage, ['id', 'Inv_id as inventory_id', 'Part_name as part_name', 'Part_number as part_number', 'id_customer']);

        if ($request->ajax()) {
            return response()->json([
                'results' => $results->items(),
                'pagination' => [
                    'total' => $results->total(),
                    'per_page' => $results->perPage(),
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage()
                ]
            ]);
        }
        
        return view('daily_report.search', compact('results'));
    }

    // edit status log harian
    public function editLog($id)
    {
        $log = DailyStockLog::with([
            'part.category',
            'part.plant',
            'part.area',
            'part.inventory',
            'boxComplete',
            'boxUncomplete'
        ])->find($id);

        // Cek jika log tidak ditemukan
        if (!$log) {
            return redirect()->route('dailyreport.index')->with('info', 'Data log tidak ditemukan.');
        }

        $statuses = ['OK', 'NG', 'Virgin', 'Funsai'];
        return view('daily_report.edit', compact('log', 'statuses'));
    }

    public function updateLog(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:OK,NG,Virgin,Funsai',
            'qty_per_box' => 'required|numeric|min:0',
            'qty_box' => 'required|numeric|min:0',
            'qty_per_box_2' => 'nullable|numeric|min:0',
            'qty_box_2' => 'nullable|numeric|min:0',
        ]);

        $log = DailyStockLog::with(['boxComplete', 'boxUncomplete', 'part.package'])->findOrFail($id);
        
        // Gunakan transaction untuk memastikan semua operasi database berhasil atau gagal secara bersama
        try {
            DB::beginTransaction();
            
            // Persiapkan data update dalam array
            $completeData = [
                'qty_per_box' => $data['qty_per_box'],
                'qty_box' => $data['qty_box'],
                'total' => $data['qty_per_box'] * $data['qty_box']
            ];
            
            $grandTotal = $completeData['total'];
            
            // Update box complete
            if ($log->boxComplete) {
                $log->boxComplete()->update($completeData);
            } else {
                $boxComplete = BoxComplete::create($completeData);
                $log->update(['id_box_complete' => $boxComplete->id]);
            }
            
            // Handle box uncomplete
            if ($request->filled('qty_box_2')) {
                $uncompleteData = [
                    'qty_per_box' => $data['qty_per_box_2'],
                    'qty_box' => $data['qty_box_2'],
                    'total' => $data['qty_per_box_2'] * $data['qty_box_2']
                ];
                
                $grandTotal += $uncompleteData['total'];
                
                if ($log->boxUncomplete) {
                    $log->boxUncomplete()->update($uncompleteData);
                } else {
                    $boxUncomplete = BoxUncomplete::create($uncompleteData);
                    $log->update(['id_box_uncomplete' => $boxUncomplete->id]);
                }
            } elseif ($log->boxUncomplete) {
                $log->boxUncomplete()->delete();
                $log->update(['id_box_uncomplete' => null]);
            }
            
            // Ambil forecast berdasarkan id_part dan bulan log
            // Pastikan $log->date adalah Carbon
            $carbonDate = $log->date instanceof \Carbon\Carbon ? $log->date : \Carbon\Carbon::parse($log->date);
            $forecast = Forecast::where('id_part', $log->id_inventory)
                ->whereRaw('MONTH(forecast_month) = ?', [$carbonDate->month])
                ->whereRaw('YEAR(forecast_month) = ?', [$carbonDate->year])
                ->first();

            // Hitung stock_per_day
            $stock_per_day = ($forecast && $forecast->min > 0)
                ? (float) ($grandTotal / $forecast->min)
                : 0.0;

            // Update log dengan stock_per_day yang baru
            $log->update([
                'status' => $data['status'],
                'Total_qty' => $grandTotal,
                'stock_per_day' => $stock_per_day
            ]);
            
            // Update package qty jika perlu
            if ($log->part && $log->part->package) {
                $log->part->package->update([
                    'qty' => $data['qty_per_box']
                ]);
            }
            
            // Update inventory act_stock dan remark
            if ($log->part->inventory) {
                // Ambil bulan dari tanggal log
                $month = $carbonDate->format('Y-m');
                $totalActual = DailyStockLog::where('id_inventory', $log->id_inventory)
                    ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                    ->sum('Total_qty');
                $planStock = $log->part->inventory->plan_stock ?? 0;
                $gap = $totalActual - $planStock;

                if ($planStock == 0 || $totalActual != $planStock) {
                    $remark = 'Abnormal';
                    if ($gap > 0) {
                        $note_remark = '+' . abs($gap);
                    } elseif ($gap < 0) {
                        $note_remark = '-' . abs($gap);
                    } else {
                        $note_remark = "Tidak ada stock";
                    }
                } else {
                    $remark = 'Normal';
                    $note_remark = null;
                }

                $log->part->inventory->update([
                    'act_stock' => $totalActual,
                    'remark' => $remark,
                    'note_remark' => $note_remark,
                ]);
            }
            
            DB::commit();
            
            // Hapus cache print dan data agar getPrintData ambil data terbaru
            Cache::forget("daily_report_print_{$log->id}");
            Cache::forget("daily_report_data_{$log->id}");

            return redirect()->route('dailyreport.index')
                ->with('berhasil', 'Data berhasil diupdate')
                ->with('report', $log->id);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function clearReportSession()
    {
        // Hapus session report agar tidak muncul lagi print otomatis
        session()->forget('report');

        // Redirect ke halaman index setelah sesi dihapus
        return redirect()->route('dailyreport.index');
    }

    /**
     * Endpoint untuk menerima dan memproses data yang disimpan secara lokal
     * ketika koneksi lambat/terputus
     */
    public function processOfflineData(Request $request)
    {
        // Validasi struktur data
        $request->validate([
            'reports' => 'required|array',
            'reports.*.inventory_id' => 'required|integer',
            'reports.*.form_data' => 'required|array',
            'reports.*.client_timestamp' => 'required'
        ]);

        $results = [];
        $processedCount = 0;
        $failedCount = 0;
        
        // Proses setiap laporan yang tersimpan lokal
        foreach ($request->reports as $report) {
            // Buat request baru untuk setiap item
            $singleRequest = new Request($report['form_data']);
            $inventoryId = $report['inventory_id'];
            $clientTimestamp = $report['client_timestamp'];
            
            // Cek apakah sudah ada laporan dengan timestamp ini
            $cacheKey = "offline_report_{$inventoryId}_{$clientTimestamp}";
            
            if (Cache::has($cacheKey)) {
                // Report sudah diproses sebelumnya
                $results[] = [
                    'inventory_id' => $inventoryId,
                    'client_timestamp' => $clientTimestamp,
                    'status' => 'already_processed',
                    'report_id' => Cache::get($cacheKey)
                ];
                continue;
            }
            
            try {
                // Gunakan metode yang sudah ada untuk menyimpan data
                // Tapi tangkap hasil daripada redirect
                DB::beginTransaction();
                
                // Proses data seperti di storecreate() tapi tanpa redirect
                $part = Part::with('inventory', 'plant')->findOrFail($inventoryId);
                $data = $this->validateReportData($singleRequest);
                
                // Proses validasi area dan user
                $currentAreaId = $data['area_id'] ?? 0;
                $currentUserId = $data['prepared_by'] ?? 0;

                // Cek duplikasi log area
                $issuedDate = Carbon::parse($data['issued_date'] ?? now());
                $existingAreaLog = DailyStockLog::where('id_inventory', $part->id)
                    ->where('id_area_head', $currentAreaId)
                    ->whereDate('date', $issuedDate)
                    ->first();

                if ($existingAreaLog) {
                    $results[] = [
                        'inventory_id' => $inventoryId,
                        'client_timestamp' => $clientTimestamp,
                        'status' => 'duplicate',
                        'report_id' => $existingAreaLog->id
                    ];
                    continue;
                }

                // Lanjutkan dengan pemrosesan seperti di storecreate()
                $boxComplete = BoxComplete::create([
                    'qty_per_box' => $data['qty_per_box'] ?? 0,
                    'qty_box' => $data['qty_box'] ?? 0,
                    'total' => $data['total'] ?? 0,
                ]);
                
                // Logika untuk box uncomplete, forecast, dll...
                $boxUncompleteId = null;
                if (!empty($data['qty_per_box_2']) && !empty($data['qty_box_2'])) {
                    $boxUncomplete = BoxUncomplete::create([
                        'qty_per_box' => $data['qty_per_box_2'] ?? 0,
                        'qty_box' => $data['qty_box_2'] ?? 0,
                        'total' => $data['total_2'] ?? 0,
                    ]);
                    $boxUncompleteId = $boxUncomplete->id;
                }
                
                $grandTotal = (int) ($data['grand_total'] ?? 0);
                $forecast = Forecast::where('id_part', $part->id)
                    ->whereRaw('MONTH(forecast_month) = ?', [$issuedDate->month])
                    ->whereRaw('YEAR(forecast_month) = ?', [$issuedDate->year])
                    ->first();
                
                $stock_per_day = ($forecast && $forecast->min > 0)
                    ? (float) ($grandTotal / $forecast->min)
                    : 0.0;
                
                // Buat daily log
                $dailyLog = DailyStockLog::create([
                    'id_inventory' => $part->id,
                    'date' => $issuedDate->format('Y-m-d'),
                    'id_box_complete' => $boxComplete->id,
                    'id_box_uncomplete' => $boxUncompleteId,
                    'id_area_head' => $currentAreaId,
                    'Total_qty' => $grandTotal,
                    'prepared_by' => $currentUserId,
                    'status' => $data['status'] ?? 'OK',
                    'stock_per_day' => $stock_per_day,
                    'created_at' => now(),
                    'offline_synced' => true, // Tandai bahwa ini dari sinkronisasi offline
                ]);
                
                // Update package dan inventory seperti di storecreate()
                if (!empty($data['qty_per_box']) && $part->package) {
                    $part->package->update(['qty' => $data['qty_per_box']]);
                }
                
                if ($part->inventory) {
                    // Ambil bulan dari issuedDate
                    $month = $issuedDate->format('Y-m');
                    $totalActual = DailyStockLog::where('id_inventory', $part->id)
                        ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                        ->sum('Total_qty');
                    $planStock = $part->inventory->plan_stock ?? 0;
                    $gap = $totalActual - $planStock;

                    if ($planStock == 0 || $totalActual != $planStock) {
                        $remark = 'Abnormal';
                        if ($gap > 0) {
                            $note_remark = '+' . abs($gap);
                        } elseif ($gap < 0) {
                            $note_remark = '-' . abs($gap);
                        } else {
                            $note_remark = "Tidak ada stock";
                        }
                    } else {
                        $remark = 'Normal';
                        $note_remark = null;
                    }

                    $part->inventory->update([
                        'act_stock' => $totalActual,
                        'remark' => $remark,
                        'note_remark' => $note_remark,
                    ]);
                }
                
                // Simpan ke cache untuk mencegah duplikasi
                Cache::put($cacheKey, $dailyLog->id, now()->addDays(7));
                DB::commit();
                
                // Catat hasil sukses
                $results[] = [
                    'inventory_id' => $inventoryId,
                    'client_timestamp' => $clientTimestamp,
                    'status' => 'success',
                    'report_id' => $dailyLog->id
                ];
                $processedCount++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                // Catat error
                $results[] = [
                    'inventory_id' => $inventoryId,
                    'client_timestamp' => $clientTimestamp,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $failedCount++;
            }
        }
        
        return response()->json([
            'success' => true,
            'processed' => $processedCount,
            'failed' => $failedCount,
            'results' => $results
        ]);
    }
    
    /**
     * Handler untuk sinkronisasi data offline dengan validasi token
     */
    public function syncOfflineData(Request $request)
    {
        // Validasi input
        $request->validate([
            'reports' => 'required|array',
            'reports.*.inventory_id' => 'required',
            'reports.*.form_data' => 'required|array',
            'reports.*.timestamp' => 'required|string',
        ]);

        // Periksa token Authorization jika ada
        $token = $request->bearerToken();
        // Jika token ada, validasi (opsional, karena CSRF sudah memberikan perlindungan)
        if ($token && !$this->validateToken($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authorization token'
            ], 401);
        }

        $results = [];
        $processedCount = 0;
        $failedCount = 0;

        // Loop semua laporan dan proses
        foreach ($request->reports as $report) {
            try {
                // Buat request baru dengan form data
                $newRequest = new Request($report['form_data']);
                $inventoryId = $report['inventory_id'];
                $timestamp = $report['timestamp'];

                // Gunakan method storecreate tapi dalam mode silent (tanpa redirect)
                DB::beginTransaction();
                
                // Ambil data Part beserta relasi inventory
                $part = Part::with('inventory', 'plant')->findOrFail($inventoryId);
                
                // Set timeout lebih panjang untuk pemrosesan batch
                set_time_limit(300);
                
                // Validasi input dari form
                $data = $this->validateReportData($newRequest);
                
                // Cek existing log
                $currentAreaId = $data['area_id'] ?? 0;
                $currentUserId = $data['prepared_by'] ?? 0;
                $issuedDate = Carbon::parse($data['issued_date'] ?? now());
                
                // Cek apakah sudah ada log untuk part ini di area yang sama pada tanggal yang sama
                $existingAreaLog = DailyStockLog::where('id_inventory', $part->id)
                    ->where('id_area_head', $currentAreaId)
                    ->whereDate('date', $issuedDate)
                    ->first();

                if ($existingAreaLog) {
                    // Jika sudah ada log, catat sebagai duplikat
                    $results[] = [
                        'inventory_id' => $inventoryId,
                        'timestamp' => $timestamp,
                        'status' => 'duplicate',
                        'message' => 'Data sudah pernah diinput'
                    ];
                    continue;
                }
                
                // Proses box complete
                $boxComplete = BoxComplete::create([
                    'qty_per_box' => $data['qty_per_box'] ?? 0,
                    'qty_box' => $data['qty_box'] ?? 0,
                    'total' => $data['total'] ?? 0,
                ]);
                
                // Proses box uncomplete jika ada
                $boxUncompleteId = null;
                if (!empty($data['qty_per_box_2']) && !empty($data['qty_box_2'])) {
                    $boxUncomplete = BoxUncomplete::create([
                        'qty_per_box' => $data['qty_per_box_2'] ?? 0,
                        'qty_box' => $data['qty_box_2'] ?? 0,
                        'total' => $data['total_2'] ?? 0,
                    ]);
                    $boxUncompleteId = $boxUncomplete->id;
                }
                
                // Ambil forecast untuk menghitung stock_per_day
                $forecast = Forecast::where('id_part', $part->id)
                    ->whereRaw('MONTH(forecast_month) = ?', [$issuedDate->month])
                    ->whereRaw('YEAR(forecast_month) = ?', [$issuedDate->year])
                    ->first();
                
                $grandTotal = (int) ($data['grand_total'] ?? 0);
                $stock_per_day = ($forecast && $forecast->min > 0)
                    ? (float) ($grandTotal / $forecast->min)
                    : 0.0;
                
                // Buat daily log
                $dailyLog = DailyStockLog::create([
                    'id_inventory' => $part->id,
                    'date' => $issuedDate->format('Y-m-d'),
                    'id_box_complete' => $boxComplete->id,
                    'id_box_uncomplete' => $boxUncompleteId,
                    'id_area_head' => $currentAreaId,
                    'Total_qty' => $grandTotal,
                    'prepared_by' => $currentUserId,
                    'status' => $data['status'] ?? 'OK',
                    'stock_per_day' => $stock_per_day,
                    'created_at' => now(),
                    'synced_from_offline' => true, // Penanda data dari sinkronisasi offline
                ]);
                
                // Update package qty jika qty_per_box diisi
                if (!empty($data['qty_per_box']) && $part->package) {
                    $part->package->update(['qty' => $data['qty_per_box']]);
                }
                
                // Update inventory
                if ($part->inventory) {
                    // Ambil bulan dari issuedDate
                    $month = $issuedDate->format('Y-m');
                    $totalActual = DailyStockLog::where('id_inventory', $part->id)
                        ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
                        ->sum('Total_qty');
                    $planStock = $part->inventory->plan_stock ?? 0;
                    $gap = $totalActual - $planStock;

                    if ($planStock == 0 || $totalActual != $planStock) {
                        $remark = 'Abnormal';
                        if ($gap > 0) {
                            $note_remark = '+' . abs($gap);
                        } elseif ($gap < 0) {
                            $note_remark = '-' . abs($gap);
                        } else {
                            $note_remark = "Tidak ada stock";
                        }
                    } else {
                        $remark = 'Normal';
                        $note_remark = null;
                    }

                    $part->inventory->update([
                        'act_stock' => $totalActual,
                        'remark' => $remark,
                        'note_remark' => $note_remark,
                    ]);
                }
                
                DB::commit();
                
                // Catat hasil sukses
                $results[] = [
                    'inventory_id' => $inventoryId,
                    'timestamp' => $timestamp,
                    'status' => 'success',
                    'report_id' => $dailyLog->id
                ];
                $processedCount++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Log error
                Log::error('Sync error: ' . $e->getMessage(), [
                    'report' => $report,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Catat hasil error
                $results[] = [
                    'inventory_id' => $report['inventory_id'],
                    'timestamp' => $report['timestamp'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $failedCount++;
            }
        }
        
        return response()->json([
            'success' => true,
            'processed' => $processedCount,
            'failed' => $failedCount,
            'results' => $results
        ]);
    }
    
    /**
     * Validasi token untuk sinkronisasi offline
     */
    private function validateToken($token)
    {
        // Implementasi sederhana: validasi token dengan CSRF
        // Dalam implementasi nyata, Anda bisa memeriksa token dari database
        return $token && $token === csrf_token();
    }
    
    /**
     * Helper untuk validasi data laporan
     */
    private function validateReportData($request)
    {
        return $request->validate([
            'status' => 'required|string',
            'qty_per_box' => 'nullable|integer',
            'qty_box' => 'nullable|integer',
            'total' => 'nullable|integer',
            'qty_per_box_2' => 'nullable|integer',
            'qty_box_2' => 'nullable|integer',
            'total_2' => 'nullable|integer',
            'grand_total' => 'required|integer',
            'issued_date' => 'required|date',
            'prepared_by' => 'required|integer',
            'plant_id' => 'required|exists:tbl_plan,id',
            'area_id' => 'required|exists:tbl_head_area,id',
        ]);
    }
}