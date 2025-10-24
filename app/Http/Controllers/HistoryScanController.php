<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockScanHistory;
use App\Models\User;
use App\Models\Part;
use App\Exports\StockScanHistoryExport;
use Maatwebsite\Excel\Facades\Excel;

class HistoryScanController extends Controller
{
    public function index(Request $request)
    {
        // Query utama + relasi
        $query = StockScanHistory::with([
            'user:id,username',
            'part:id,Inv_id',
        ]);

        // 🔍 Filter berdasarkan Inventory ID
        if ($request->filled('inventory_id')) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inventory_id . '%');
            });
        }

        // 🔍 Filter berdasarkan User (Prepared By)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // 🔍 Filter berdasarkan Status (in / out)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 🗓️ Filter berdasarkan satu tanggal scan (scanned_at)
        if ($request->filled('scan_date')) {
            $query->whereDate('scanned_at', $request->scan_date);
        }

        // 🔽 Urutkan berdasarkan waktu scan terbaru
        $histories = $query->orderBy('scanned_at', 'desc')->get();

        // 🔄 Data untuk dropdown filter (user list)
        $users = User::select('id', 'username')->orderBy('username')->get();

        // ⏩ Kirim data ke view
        return view('dashboard_inout.historyscan', compact('histories', 'users'));
    }
    public function export(Request $request)
    {
        $filters = $request->all();
        $filename = 'HistoryScan_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new StockScanHistoryExport($filters), $filename);
    }
}
