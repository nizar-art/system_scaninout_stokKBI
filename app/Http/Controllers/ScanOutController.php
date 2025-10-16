<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\DailyStockLog;
use App\Models\RakStock;
use App\Models\StockScanHistory;
use Illuminate\Http\Request;

class ScanOutController extends Controller
{
    public function index()
    {
        return view('scanout_stok.index');
    }

    public function scan(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|string'
        ]);

        $invId = trim($request->inventory_id);

        // Cari part berdasarkan Inv_id
        $inventoryPart = Part::where('Inv_id', $invId)->first();
        if (!$inventoryPart) {
            return back()->with('error', "Inventory ID {$invId} tidak ditemukan.");
        }

        // Simpan barcode ke session sementara
        session([
            'scan_data_out' => [
                'qrcode_raw' => $invId,
            ]
        ]);

        return redirect()->route('scan.edit.report.out', ['inventory_id' => $inventoryPart->id]);
    }

    public function editReportOut($inventory_id)
    {
        $part = Part::with('plant')->findOrFail($inventory_id);

        // 🔹 Jumlahkan total stok dari semua area
        $stok_saat_ini = DailyStockLog::where('id_inventory', $part->id)->sum('Total_qty');

        $qrcode_raw = session('scan_data_out.qrcode_raw');

        // 🔹 Ambil daftar rak
        $raks = RakStock::where('id_inventory', $part->id)->get();

        if ($raks->isEmpty()) {
            foreach (['Rak 1', 'Rak 2', 'Rak 3', 'Rak 4', 'Rak 5'] as $rakName) {
                RakStock::create([
                    'id_inventory' => $part->id,
                    'rak_name' => $rakName,
                    'stok' => 0,
                ]);
            }
            $raks = RakStock::where('id_inventory', $part->id)->get();
        }

        // 🔹 Ambil area yang muncul di DailyStockLog untuk inventory ini
        $areas = \App\Models\HeadArea::whereIn('id', function ($query) use ($part) {
            $query->select('id_area_head')
                ->from('tbl_daily_stock_logs')
                ->where('id_inventory', $part->id)
                ->whereNotNull('id_area_head');
        })->get();

        return view('scanout_stok.kurangistok', compact(
            'part',
            'stok_saat_ini',
            'qrcode_raw',
            'raks',
            'areas'
        ));
    }

    public function storeHistoryOut(Request $request, $inventory_id)
    {
        $validated = $request->validate([
            'stok_inout' => 'required|integer|min:1',
            'rak_id' => 'required|integer|exists:tbl_rak_stock,id',
            'area_id' => 'required|integer|exists:tbl_head_area,id',
        ]);

        $part = Part::findOrFail($inventory_id);
        $rak = RakStock::findOrFail($request->rak_id);

        $totalStokSesudah = 0;

        \DB::transaction(function () use ($request, $part, $rak, &$totalStokSesudah) {
            // 🔹 Ambil log untuk inventory & area yang dipilih
            $logs = DailyStockLog::where('id_inventory', $part->id)
                ->where('id_area_head', $request->area_id)
                ->get();

            // Jika belum ada log, buat baru dengan stok 0
            if ($logs->isEmpty()) {
                $newLog = DailyStockLog::create([
                    'id_inventory' => $part->id,
                    'id_area_head' => $request->area_id,
                    'prepared_by' => auth()->id(),
                    'Total_qty' => 0,
                    'status' => 'OK',
                    'date' => now()->toDateString(),
                ]);

                $logs = collect([$newLog]);
            }

            // 🔹 Update semua log area dengan stok berkurang
            foreach ($logs as $log) {
                $stok_baru = max(0, $log->Total_qty - $request->stok_inout);

                $log->update([
                    'Total_qty' => $stok_baru,
                    'prepared_by' => auth()->id(),
                ]);
            }

            // 🔹 Update stok di rak
            if ($rak->stok < $request->stok_inout) {
                throw new \Exception("Stok di rak {$rak->rak_name} tidak cukup!");
            }

            $rak->stok = max(0, $rak->stok - $request->stok_inout);
            $rak->save();

            // 🔹 Simpan history OUT
            StockScanHistory::create([
                'id_inventory' => $part->id,
                'id_daily_stock_log' => $logs->first()->id ?? null,
                'id_rak_stock' => $rak->id,
                'user_id' => auth()->id(),
                'qrcode_raw' => $request->qrcode_raw ?? session('scan_data_out.qrcode_raw'),
                'stok_inout' => $request->stok_inout,
                'status' => 'OUT',
                'scanned_at' => now(),
            ]);

            // 🔹 Hitung ulang total semua stok
            $totalStokSesudah = DailyStockLog::where('id_inventory', $part->id)->sum('Total_qty');
        });

        return redirect()->route('scanOutStok.index')
            ->with('success', "Stok berhasil dikurangi! Total keseluruhan sekarang: {$totalStokSesudah}");
    }

    public function searchout(Request $request)
    {
        $query = trim($request->input('query'));
        $perPage = (int) $request->input('per_page', 10);

        if (empty($query)) {
            if ($request->ajax()) {
                return response()->json([
                    'results' => [],
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => 1,
                        'last_page' => 1
                    ]
                ]);
            }

            return view('scanout_stok.search', ['results' => collect()]);
        }

        $results = Part::with(['customer:id,Customer_name'])
            ->where(function ($q) use ($query) {
                $q->where('Part_name', 'like', "%{$query}%")
                    ->orWhere('Part_number', 'like', "%{$query}%")
                    ->orWhere('Inv_id', 'like', "%{$query}%");
            })
            ->select('id', 'Inv_id', 'Part_name', 'Part_number')
            ->paginate($perPage);

        if ($request->ajax()) {
            return response()->json([
                'results' => $results->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'inventory_id' => $item->Inv_id,
                        'part_name' => $item->Part_name,
                        'part_number' => $item->Part_number,
                        'customer_name' => optional($item->customer)->Customer_name ?? '-',
                    ];
                }),
                'pagination' => [
                    'total' => $results->total(),
                    'per_page' => $results->perPage(),
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                ],
            ]);
        }

        return view('scanout_stok.search', compact('results'));
    }
}
