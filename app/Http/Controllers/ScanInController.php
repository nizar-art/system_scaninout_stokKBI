<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\DailyStockLog;
use App\Models\HeadRak;
use App\Models\RakStock;
use App\Models\StockScanHistory;
use App\models\Plant;
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

        $barcode = trim($request->inventory_id);
        $qrcode_raw = $barcode; // Simpan seluruh kode mentah

        $parts = array_filter(explode(';', $barcode)); // buang kosong
        $invId = null;
        $qty = null;

        // 🔹 1. Cari bagian yang mengandung tanda ":"
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$invId, $qtyPart] = explode(':', $part, 2);
                $qty = is_numeric($qtyPart) ? (int) $qtyPart : null;
                break;
            }
        }

        // 🔹 2. Kalau gak ketemu tanda ":" → ambil bagian kedua, atau pertama
        if (!$invId) {
            if (isset($parts[1])) {
                $invId = $parts[1];
            } else {
                $invId = $parts[0] ?? null;
            }
        }

        if (!$invId) {
            return back()->with('error', 'Format barcode tidak bisa dibaca. Pastikan mengandung kode part.');
        }

        // 🔍 3. Cari part di database
        $inventoryPart = Part::where('Inv_id', $invId)->first();
        if (!$inventoryPart) {
            return back()->with('error', "Inventory ID {$invId} tidak ditemukan.");
        }

        // 💾 4. Simpan ke session
        session([
            'scan_data' => [
                'qrcode_raw' => $qrcode_raw,
                'stok_inout' => $qty ?: null,
            ]
        ]);

        // 🚀 5. Arahkan ke halaman edit laporan
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

        // 🔹 Ambil semua HeadRak aktif (ubah filter jika mau ambil semua)
        $headRaks = \App\Models\HeadRak::where('status', 'aktif')->get();

        // 🔹 Pastikan setiap head_rak punya record di tbl_rak_stock untuk inventory ini
        foreach ($headRaks as $head) {
            $exists = RakStock::where('id_inventory', $part->id)
                ->where('id_rak_head', $head->id)
                ->exists();

            if (! $exists) {
                RakStock::create([
                    'id_inventory' => $part->id,
                    'id_rak_head' => $head->id,
                    'stok' => 0,
                ]);
            }
        }

        // 🔹 Ambil rak (dengan relasi headRak) untuk inventory ini
        $raks = RakStock::with('headRak')
            ->where('id_inventory', $part->id)
            ->get();

        // 🔹 Ambil area berdasarkan plan yang login
        $selectedPlanId = session('selected_plan_id');
        $areas = \App\Models\HeadArea::where('id_plan', $selectedPlanId)->get();

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

            // 🔹 Ambil log terakhir untuk inventory & area yang dipilih
            $log = DailyStockLog::where('id_inventory', $part->id)
                ->where('id_area_head', $request->area_id)
                ->latest('id')
                ->first();

            // 🔸 Jika belum ada log untuk area ini → buat baru
            if (!$log) {
                $log = DailyStockLog::create([
                    'id_inventory' => $part->id,
                    'id_area_head' => $request->area_id,
                    'prepared_by' => auth()->id(),
                    'Total_qty' => 0,
                    'status' => 'OK',
                    'date' => now()->toDateString(),
                ]);
            }

            // 🔹 Update stok log terakhir
            $stok_baru = ($request->status === 'IN')
                ? $log->Total_qty + $request->stok_inout
                : max(0, $log->Total_qty - $request->stok_inout);

            $log->update([
                'Total_qty' => $stok_baru,
                'prepared_by' => auth()->id(),
            ]);

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
                'id_daily_stock_log' => $log->id,
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

        // Jika query kosong
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

        // Ambil data Part beserta relasi plant
        $results = Part::with(['plant'])
            ->where(function ($q) use ($query) {
                $q->where('Part_name', 'like', '%' . $query . '%')
                ->orWhere('Part_number', 'like', '%' . $query . '%')
                ->orWhere('Inv_id', 'like', '%' . $query . '%');
            })
            ->select('id', 'Inv_id', 'Part_name', 'Part_number', 'id_plan')
            ->paginate($perPage);

        // Jika request dari AJAX
        if ($request->ajax()) {
            return response()->json([
                'results' => $results->map(function ($item) {
                    // Ambil total stok dari DailyStockLog
                    $totalQty = \App\Models\DailyStockLog::where('id_inventory', $item->id)
                        ->sum('Total_qty');

                    return [
                        'id' => $item->id,
                        'inventory_id' => $item->Inv_id,
                        'part_name' => $item->Part_name,
                        'part_number' => $item->Part_number,
                        'plant_name' => optional($item->plant)->name ?? '-',
                        'total_qty' => $totalQty,
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