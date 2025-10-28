<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Part;
use App\Models\DailyStockLog;
use App\Models\RakStock;
use App\Models\StockScanHistory;
use App\Models\HeadArea;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date; // 🔹 Tambahkan ini untuk konversi tanggal Excel

class ImportInStokController extends Controller
{
    public function index()
    {
        return view('dashboard_inout.importinstok');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv'
        ]);

        $rows = Excel::toArray([], $request->file('file'))[0];
        $header = array_map('strtolower', $rows[0]);
        unset($rows[0]);

        $data = [];
        foreach ($rows as $r) {
            if (!isset($r[0]) || empty($r[0])) continue;

            // 🔹 Konversi tanggal dari Excel numeric ke format Y-m-d
            $rawTanggal = $r[5] ?? null;
            $tanggal = null;

            if (!empty($rawTanggal)) {
                if (is_numeric($rawTanggal)) {
                    // Jika numeric (misal 45958), ubah ke tanggal Excel
                    $tanggal = Date::excelToDateTimeObject($rawTanggal)->format('Y-m-d');
                } else {
                    // Jika teks, coba parse dengan Carbon
                    try {
                        $tanggal = Carbon::parse($rawTanggal)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $tanggal = null;
                    }
                }
            }

            $data[] = [
                'inventory_id' => trim($r[0]),
                'status'       => in_array(strtoupper(trim($r[1] ?? '')), ['IN', 'OUT'])
                    ? strtoupper(trim($r[1]))
                    : null,
                'jumlah'       => (int)($r[2] ?? 0),
                'nama_area'    => trim($r[3] ?? ''),
                'rak_name'     => trim($r[4] ?? ''),
                'tanggal_scan' => $tanggal,
            ];
        }

        session(['preview_instok' => $data]);

        return view('dashboard_inout.importinstok', [
            'previewData' => $data,
        ]);
    }

    public function cancel()
    {
        session()->forget('preview_instok');
        return redirect()->route('ImportIn.index')->with('info', 'Preview import telah dibatalkan.');
    }

    public function store()
    {
        $data = session('preview_instok', []);
        if (empty($data)) {
            return back()->with('error', 'Tidak ada data untuk diimport.');
        }

        $successCount = 0;
        $failedCount = 0;
        $failedRows = [];

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                // 🔹 Validasi kolom wajib
                if (
                    empty($row['inventory_id']) ||
                    empty($row['status']) ||
                    empty($row['nama_area']) ||
                    empty($row['rak_name']) ||
                    empty($row['tanggal_scan'])
                ) {
                    $failedCount++;
                    $failedRows[] = "Baris #" . ($index + 2) . ": Kolom tidak lengkap atau tanggal kosong";
                    continue;
                }

                // 🔹 Validasi format tanggal
                try {
                    $tanggal = Carbon::parse($row['tanggal_scan'])->format('Y-m-d');
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedRows[] = "Baris #" . ($index + 2) . ": Format tanggal tidak valid";
                    continue;
                }

                // 🔹 Cek Part
                $part = Part::where('Inv_id', $row['inventory_id'])->first();
                if (!$part) {
                    $failedCount++;
                    $failedRows[] = "Baris #" . ($index + 2) . ": Inventory ID tidak ditemukan";
                    continue;
                }

                // 🔹 Cek Area
                $area = HeadArea::where('nama_area', 'like', '%' . $row['nama_area'] . '%')->first();
                if (!$area) {
                    $failedCount++;
                    $failedRows[] = "Baris #" . ($index + 2) . ": Nama area tidak ditemukan";
                    continue;
                }

                // 🔹 Update stok rak
                $rak = RakStock::firstOrNew([
                    'id_inventory' => $part->id,
                    'rak_name'     => $row['rak_name'],
                ]);

                $currentStock = $rak->stok ?? 0;
                $change = strtoupper($row['status']) === 'IN' ? $row['jumlah'] : -$row['jumlah'];
                $rak->stok = max(0, $currentStock + $change);
                $rak->save();

                // 🔹 Hitung total stok
                $totalQty = RakStock::where('id_inventory', $part->id)->sum('stok');

                // 🔹 Simpan atau update log stok harian
                $log = DailyStockLog::firstOrNew([
                    'id_inventory' => $part->id,
                    'id_area_head' => $area->id,
                    'date'         => $tanggal,
                ]);

                $log->prepared_by = auth()->id();
                $log->Total_qty = $totalQty;
                $log->stock_per_day = ($log->stock_per_day ?? 0) + $row['jumlah'];
                $log->status = 'OK';
                $log->save();

                // 🔹 Catat riwayat scan
                StockScanHistory::create([
                    'id_inventory'       => $part->id,
                    'id_daily_stock_log' => $log->id,
                    'user_id'            => auth()->id(),
                    'qrcode_raw'         => null,
                    'stok_inout'         => $row['jumlah'],
                    'status'             => strtoupper($row['status']),
                    'scanned_at'         => $tanggal,
                ]);

                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }

        session()->forget('preview_instok');

        // 🔹 Buat pesan hasil
        $message = "Import selesai! {$successCount} baris berhasil, {$failedCount} baris gagal.";
        if ($failedCount > 0) {
            session()->flash('import_fail_details', $failedRows);
        }

        return redirect()
            ->route('ImportIn.index')
            ->with('success', $message);
    }

    public function downloadTemplate()
    {
        $path = public_path('template_import_instok.xlsx');

        if (!file_exists($path)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray([
                ['inventory_id', 'status', 'jumlah', 'nama_area', 'rak_name', 'tanggal_scan']
            ]);

            $sheet->fromArray([
                ['INV001', 'IN', 50, 'Material Transit', 'Rak 1', '2025-10-27'],
                ['INV001', 'OUT', 10, 'Material Transit', 'Rak 1', '2025-10-27']
            ], null, 'A2');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($path);
        }

        return response()->download($path);
    }
}
