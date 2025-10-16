<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\Category;
use App\Imports\StoImport;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StoExport;
use App\Models\PlanStock;
use App\Models\DailyStockLog;
use Illuminate\Support\Facades\Log;
use App\Exports\StoHistoryExport;
use App\Jobs\ProcessStoImport;
use App\Models\ImportLog;
use Illuminate\Support\Facades\DB;

class StoController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua kategori untuk filter
        $categories = Category::all();

        // Query untuk mengambil data STO, dengan filter kategori jika ada
        $query = Inventory::with('part.plant', 'part.area', 'part.category')
            ->orderBy('created_at', 'desc');

        // Jika ada kategori yang dipilih, filter berdasarkan kategori dari relasi part
        if ($request->has('category_id') && $request->category_id != '') {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category_id);
            });
        }

        // Filter berdasarkan bulan dan tahun jika ada
        if ($request->filled('month')) {
            $parts = explode('-', $request->month);
            if (count($parts) == 2) {
                $query->whereYear('created_at', $parts[0])
                      ->whereMonth('created_at', $parts[1]);
            }
        }

        // Filter berdasarkan remark langsung dari tabel inventory
        if ($request->filled('remark')) {
            $query->where('remark', $request->remark);
        }

        // Filter berdasarkan inv_id dari relasi part
        if ($request->filled('inv_id')) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inv_id . '%');
            });
        }

        // Ambil data berdasarkan pagination
        $perPage = $request->get('per_page', 25);
        $parts = $query->paginate($perPage);

        // Tampilkan view dengan data parts dan categories untuk filter
        return view('STO.index', compact('parts', 'categories'));
    }
    public function create()
    {
        $parts = Part::all();
        $categories = Category::all();
        return view('STO.create', compact('parts', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_part' => 'required|exists:tbl_part,id',
            'plan_stock' => 'required|integer|min:0',
        ]);

        // Hitung total qty jika sudah ada DailyStockLog yang berkaitan
        $logTotal = DailyStockLog::whereHas('part', function ($q) use ($request) {
            $q->where('id_inventory', $request->id_part);
        })->sum('Total_qty');

        // Determine remark and note
        $gap = $logTotal - $request->plan_stock;

        if ($logTotal == 0 || $logTotal != $request->plan_stock) {
            $remark = 'abnormal';
            $note_remark = 'gap: ' . $gap;
        } else {
            $remark = 'normal';
            $note_remark = null;
        }

        // Buat inventory baru
        $inventory = Inventory::create([
            'id_part' => $request->id_part,
            'plan_stock' => $request->plan_stock,
            'act_stock' => $logTotal,
            'remark' => $remark,
            'note_remark' => $note_remark,
        ]);

        // Catat plan stock awal
        PlanStock::create([
            'id_inventory' => $inventory->id,
            'plan_stock_before' => $request->plan_stock,
            'plan_stock_after' => $request->plan_stock,
        ]);

        return redirect()->route('sto.index')
            ->with('success', 'Data STO berhasil ditambahkan.');
    }

    // import
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:51200', // Maksimal 50MB
            'duplicate_action' => 'nullable|in:skip,update', // Tambahkan validasi untuk aksi duplikat
        ]);

        // Tingkatkan limit waktu dan memory
        set_time_limit(300); // 5 menit
        ini_set('memory_limit', '512M'); // 512MB memory

        try {
            $file = $request->file('file');
            $duplicateAction = $request->input('duplicate_action', 'update'); // Default 'update'

            $importer = new StoImport(null, $duplicateAction);
            Excel::import($importer, $file);

            $logs = $importer->getLogs();
            $stats = [
                'total' => $importer->getTotalCount(),
                'success' => $importer->getSuccessCount(),
                'failed' => $importer->getFailedCount(),
                'duplicate' => $importer->getDuplicateCount(),
                'total_qty' => $importer->getTotalQty(),
            ];

            // Siapkan pesan sukses berdasarkan hasil
            $successMessage = 'Import selesai. ' . $stats['success'] . ' data berhasil diproses.';
            
            // Tambahkan info duplikat jika ada
            if ($stats['duplicate'] > 0) {
                if ($duplicateAction === 'skip') {
                    $successMessage .= ' ' . $stats['duplicate'] . ' data duplikat ditemukan (tidak diimport).';
                } else {
                    $successMessage .= ' ' . $stats['duplicate'] . ' data duplikat diupdate.';
                }
            }
            
            // Tambahkan info error jika ada
            if ($stats['failed'] > 0) {
                $successMessage .= ' ' . $stats['failed'] . ' data gagal diproses.';
            }
            
            // Tambahkan informasi total quantity
            $successMessage .= ' Total quantity: ' . number_format($stats['total_qty'], 0, ',', '.');

            return redirect()->route('sto.index')->with([
                'success' => $successMessage,
                'stats' => $stats,
                'logs' => array_slice($logs, 0, 50) // Tampilkan maksimal 50 log
            ]);

        } catch (\Exception $e) {
            Log::error('Import Error: ' . $e->getMessage(), [
                'file' => $request->file('file')->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('sto.index')->with([
                'error' => 'Import gagal: ' . (str_contains($e->getMessage(), 'not found') ?
                    'Format file tidak sesuai. Pastikan ada kolom inv_id dan total_qty' :
                    $e->getMessage()),
            ]);
        }
    }

    // Helper untuk menyederhanakan pesan error
    private function simplifyErrorMessage($message)
    {
        if (str_contains($message, 'Maximum execution time')) {
            return 'Proses import terlalu lama. Coba pecah file menjadi bagian yang lebih kecil.';
        }

        if (str_contains($message, 'Allowed memory size')) {
            return 'File terlalu besar. Coba pecah file atau tingkatkan memory limit server.';
        }

        return $message;
    }
    // export
    // Fungsi untuk export ke Excel
    public function export(Request $request)
    {
        // Cek jumlah data sebelum export
        $query = \App\Models\Inventory::query();
        if ($request->category_id) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category_id);
            });
        }
        if ($request->remark) {
            $query->where('remark', $request->remark);
        }
        if ($request->inv_id) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inv_id . '%');
            });
        }
        if ($request->month) {
            $parts = explode('-', $request->month);
            if (count($parts) == 2) {
                $query->whereYear('created_at', $parts[0])
                      ->whereMonth('created_at', $parts[1]);
            }
        }
        $total = $query->count();
        if ($total > 5000) {
            return redirect()->route('sto.index')->with('error', 'Export gagal: Data terlalu banyak (' . $total . ' baris). Silakan gunakan filter untuk memperkecil data yang diexport (max 5000 baris).');
        }
        try {
            return Excel::download(new StoExport(
                $request->category_id,
                $request->remark,
                $request->inv_id,
                $request->month
            ), 'List_STO_Data.xlsx');
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Maximum execution time')) {
                return redirect()->route('sto.index')->with('error', 'Export gagal: Data terlalu banyak, proses terlalu lama. Silakan gunakan filter kategori, remark, atau bulan untuk memperkecil data yang diexport.');
            }
            return redirect()->route('sto.index')->with('error', 'Export gagal: ' . $message);
        }
    }

    // Fungsi untuk export history ke Excel
    public function exportHistory(Request $request)
    {
        return Excel::download(new StoHistoryExport($request->category_id), 'History_STO_Export.xlsx');
    }

    // delete
    public function deleteSto(Request $request)
    {
        $ids = explode(',', $request->ids);

        // Validasi bahwa user memiliki akses untuk menghapus
        if (empty($ids)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        // Hapus multiple records
        Inventory::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', 'Item yang dipilih telah berhasil dihapus.');
    }

}