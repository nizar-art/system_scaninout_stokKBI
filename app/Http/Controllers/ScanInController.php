<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\DailyStockLog;
use App\Models\RakStock;
use App\Models\StockScanHistory;
use Illuminate\Http\Request;

class ScanInController extends Controller
{
    public function index()
    {
        return view('scanin_stok.index');
    }

    public function scan(Request $request)
    {
        $request->validate([
            'inventory_id' => 'required|string'
        ]);

        // Contoh barcode baru: MSN;INV00123:20;2501030001
        $barcode = $request->inventory_id;
        $parts = explode(';', $barcode);

        if (count($parts) < 3) {
            return back()->with('error', 'Format barcode tidak valid.');
        }

        [$prefix, $middle, $extra] = $parts;

        // middle = INV00123:20 â†’ pisahkan id part dan qty
        $middleParts = explode(':', $middle);

        if (count($middleParts) < 2) {
            return back()->with('error', 'Format bagian tengah barcode tidak valid. Harus seperti INV00123:20');
        }

        [$invId, $qty] = $middleParts;

        // Cari part berdasarkan Inv_id
        $inventoryPart = Part::where('Inv_id', $invId)->first();
        if (!$inventoryPart) {
            return back()->with('error', "Inventory ID {$invId} tidak ditemukan .");
        }

        // Simpan data scan di session sementara
        session([
            'scan_data' => [
                'qrcode_raw' => $extra,     // sekarang ambil dari bagian ketiga barcode
                'stok_inout' => (int) $qty,
            ]
        ]);

        // Redirect ke halaman konfirmasi / edit laporan
        return redirect()->route('scan.edit.report.in', ['inventory_id' => $inventoryPart->id]);
    }
 

    public function editReportin($inventory_id)
    {
        $part = Part::with('plant')->findOrFail($inventory_id);

        // 🔹 Jumlahkan semua total_qty dari inventory_id yang sama
        $stok_saat_ini = DailyStockLog::where('id_inventory', $part->id)
            ->sum('Total_qty');

        $qrcode_raw = session('scan_data.qrcode_raw');
        $stok_inout = session('scan_data.stok_inout');

        // 🔹 Ambil daftar rak
        $raks = RakStock::where('id_inventory', $part->id)->get();

        // Kalau belum ada rak, buat otomatis Rak 1–5
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

        return view('scanin_stok.tambahstock', compact(
            'part',
            'stok_saat_ini',
            'qrcode_raw',
            'stok_inout',
            'raks',
            'areas'
        ));
    }

    public function storeHistoryin(Request $request, $inventory_id)
    {
        $validated = $request->validate([
            'status' => 'required|in:IN,OUT',
            'stok_inout' => 'required|integer|min:1',
            'rak_id' => 'required|integer|exists:tbl_rak_stock,id',
            'area_id' => 'required|integer|exists:tbl_head_area,id', // pastikan nama tabel benar
        ]);

        $part = Part::findOrFail($inventory_id);
        $rak = RakStock::findOrFail($request->rak_id);

        $totalStokSesudah = 0; // buat variabel untuk simpan total stok akhir

        \DB::transaction(function () use ($request, $part, $rak, &$totalStokSesudah) {

            // 🔹 Ambil log sesuai inventory & area yang dipilih
            $logs = DailyStockLog::where('id_inventory', $part->id)
                ->where('id_area_head', $request->area_id)
                ->get();

            // 🔸 Jika belum ada log untuk area ini → buat baru
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

            // 🔹 Update stok untuk semua log di area tersebut
            foreach ($logs as $log) {
                $stok_baru = ($request->status === 'IN')
                    ? $log->Total_qty + $request->stok_inout
                    : max(0, $log->Total_qty - $request->stok_inout);

                $log->update([
                    'Total_qty' => $stok_baru,
                    'prepared_by' => auth()->id(),
                ]);
            }
 
            // 🔹 Update stok di rak
            if ($request->status === 'IN') {
                $rak->stok += $request->stok_inout;
            } else {
                $rak->stok = max(0, $rak->stok - $request->stok_inout);
            }
            $rak->save();

            // 🔹 Simpan ke history
            StockScanHistory::create([
                'id_inventory' => $part->id,
                'id_daily_stock_log' => $logs->first()->id ?? null,
                'id_rak_stock' => $rak->id,
                'user_id' => auth()->id(),
                'qrcode_raw' => $request->qrcode_raw,
                'stok_inout' => $request->stok_inout,
                'status' => $request->status,
                'scanned_at' => now(),
            ]);

            // 🔹 Hitung total semua area setelah update
            $totalStokSesudah = DailyStockLog::where('id_inventory', $part->id)->sum('Total_qty');
        });

        return redirect()->route('scanInStok.index')
            ->with('success', "Stok berhasil ditambahkan! Total keseluruhan sekarang: {$totalStokSesudah}");
    }

    // cari by partname atau part number
    public function searchin(Request $request)
    {
        $query = trim($request->input('query'));
        $perPage = (int) $request->input('per_page', 10);

        // Jika query kosong, jangan langsung ambil semua data
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

            return view('scanin_stok.search', ['results' => collect()]);
        }

        // Ambil data Part beserta relasi customer
        $results = Part::with(['customer:id,Customer_name'])
            ->where(function ($q) use ($query) {
                $q->where('Part_name', 'like', '%' . $query . '%')
                ->orWhere('Part_number', 'like', '%' . $query . '%')
                ->orWhere('Inv_id', 'like', '%' . $query . '%');
            })
            ->select('id', 'Inv_id', 'Part_name', 'Part_number')
            ->paginate($perPage);

        // Jika request dari AJAX (misalnya untuk live search)
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

        // View hasil pencarian
        return view('scanin_stok.search', compact('results'));
    }


}
