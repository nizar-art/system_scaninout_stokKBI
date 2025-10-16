<?php

namespace App\Http\Controllers;

use App\Models\RakStock;
use App\Models\Part;
use Illuminate\Http\Request;

class KurangStokController extends Controller
{
    public function kurangiStok(Request $request, $id)
    {
        $part = Part::findOrFail($id);
        $jumlah = (int) $request->jumlah_dikurangi;
        $rak = $request->rak_name;

        // 🔹 Cek rak yang dipilih
        $rakStock = RakStock::where('id_inventory', $part->id)
                            ->where('rak_name', $rak)
                            ->first();

        if (!$rakStock) {
            return back()->with('error', 'Rak tidak ditemukan!');
        }

        // 🔹 Validasi stok rak cukup
        if ($rakStock->stok < $jumlah) {
            return back()->with('error', 'Stok di rak ini tidak cukup!');
        }

        // 🔹 Kurangi stok di rak
        $rakStock->stok -= $jumlah;
        $rakStock->save();

        // 🔹 Kurangi stok total part juga (jika ada kolom total stok di part)
        if ($part->stok_saat_ini >= $jumlah) {
            $part->stok_saat_ini -= $jumlah;
            $part->save();
        }

        // 🔹 Simpan ke history
        \App\Models\StockScanHistory::create([
            'id_inventory' => $part->id,
            'user_id' => auth()->id(),
            'stok_inout' => $jumlah,
            'status' => 'OUT',
        ]);

        return back()->with('success', "Stok di {$rak} berhasil dikurangi {$jumlah} unit!");
    }

}
