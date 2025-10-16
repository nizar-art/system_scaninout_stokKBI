<?php

namespace App\Http\Controllers;

use App\Models\WorkDays;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Imports\WorkDaysImport;

class WorkDaysController extends Controller
{
    //
    public function index(Request $request)
    {
        // Ambil semua data hari kerja
        $workDays = WorkDays::whereYear('month', now()->year)->get();


        // Kembalikan view dengan data hari kerja
        return view('work.index', compact('workDays'));
    }
    public function store(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'month' => 'required|date',
            'hari_kerja' => 'required|integer|min:0',
        ]);

        // Simpan data hari kerja baru
        WorkDays::create($validated);

        // Redirect ke halaman index dengan pesan sukses
        return redirect()->route('workdays.index')->with('success', 'Hari kerja berhasil ditambahkan.');
    }

    
    public function edit($id)
    {
        // Ambil data hari kerja berdasarkan ID
        $workDay = WorkDays::findOrFail($id);

        // Tampilkan form edit dengan data hari kerja
        return view('work.edit', compact('workDay'));
    }


    public function update(Request $request, $id)
    {
        // Validasi input
        $validated = $request->validate([
            'hari_kerja' => 'required|integer|min:0',
            'month' => 'nullable|date_format:Y-m'
        ]);

        $workDay = WorkDays::findOrFail($id);

        // Jika field month ada dan valid, update jadi tanggal awal bulan
        if ($request->filled('month')) {
            $validated['month'] = $request->month . '-01';
        }

        // Update seluruh field, dan paksa simpan ulang untuk update updated_at
        $workDay->fill($validated);
        $workDay->touch();
        $workDay->save();

        return redirect()->route('workdays.index')->with('success', 'Hari kerja berhasil diperbarui.');
    }


    public function import(Request $request)
    {
        // Validasi file yang diupload
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        try {
            // Proses impor menggunakan Maatwebsite Excel
            Excel::import(new WorkDaysImport, $request->file('file'));

            return redirect()->route('workdays.index')->with('success', 'Data hari kerja berhasil diimpor.');
        } catch (\Exception $e) {
            Log::error('Error importing work days: ' . $e->getMessage());
            return redirect()->route('workdays.index')->with('error', 'Terjadi kesalahan saat mengimpor data hari kerja.');
        }
    }

}
