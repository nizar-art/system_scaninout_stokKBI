<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bom;
use App\Models\Part;
use App\Imports\BomImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BomExport;

class BomController extends Controller
{
    public function index(Request $request)
    {
        $query = Bom::with(['product', 'component']);

        // Tambahkan pencarian jika ada parameter search
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('Inv_id', 'like', "%{$search}%")
                    ->orWhere('Part_name', 'like', "%{$search}%");
            })->orWhereHas('component', function ($q) use ($search) {
                $q->where('Inv_id', 'like', "%{$search}%")
                    ->orWhere('Part_name', 'like', "%{$search}%");
            });
        }

        // Urutkan berdasarkan product_id, lalu component_id untuk mengelompokkan item terkait
        $query->orderBy('product_id', 'asc')
              ->orderBy('component_id', 'asc')
              ->orderBy('id', 'asc');

        $perPage = $request->get('per_page', 25);
        $boms = $query->paginate($perPage);

        return view('bom.index', compact('boms'));
    }


    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $import = new BomImport();
        Excel::import($import, $request->file('file'));

        $success = $import->getSuccessCount();
        $updated = $import->getUpdatedCount();
        $errors = $import->getErrors();

        if (!empty($errors)) {
            $errorMessages = [];

            foreach ($errors as $error) {
                $errorMessages[] = "Baris {$error['row']}: " . implode('; ', $error['errors']);
                // Untuk debug tambahan:
                // $errorMessages[] = "Data: " . json_encode($error['data']);
            }

            return redirect()
                ->route('bom.index')
                ->with('error', "Import selesai dengan error. Berhasil: {$success}, Diupdate: {$updated}, Error: " . count($errors))
                ->with('error_details', $errorMessages);
        }

        return redirect()
            ->route('bom.index')
            ->with('success', "Import berhasil! Data baru: {$success}, Data diupdate: {$updated}");
    }

    // deleteMultiple method to handle deletion of multiple BOM records
    public function deleteMultiple(Request $request)
    {
        $ids = explode(',', $request->ids);

        // Validasi bahwa user memiliki akses untuk menghapus
        if (empty($ids)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        // Hapus multiple records
        Bom::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', 'Selected items have been deleted successfully.');
    }

    // export method to handle exporting BOM data to Excel
    public function export(Request $request)
    {
        $search = $request->input('search');
        $fileName = 'BOM_List_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new BomExport($search), $fileName);
    }
}
