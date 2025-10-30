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

        // 🗓️ Filter berdasarkan rentang tanggal scan (scanned_at)
        if ($request->filled('date_range')) {
            $dates = explode(" to ", $request->date_range);

            // Kalau hanya 1 tanggal
            if (count($dates) === 1) {
                $query->whereDate('scanned_at', $dates[0]);
            }

            // Kalau rentang 2 tanggal
            if (count($dates) === 2) {
                $start = $dates[0];
                $end = $dates[1];

                $query->whereBetween('scanned_at', [
                    $start . ' 00:00:00',
                    $end . ' 23:59:59',
                ]);
            }
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
