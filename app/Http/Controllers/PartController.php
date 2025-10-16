<?php

namespace App\Http\Controllers;

use App\Exports\PartsExport;
use App\Imports\PartsImport;
use App\Models\Area;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Part;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PartController extends Controller
{
     public function indexAll(Request $request)
    {
        // Ambil ketiga kategori
        $categories = Category::whereIn('name', ['Raw Material', 'ChildPart', 'Packaging', 'WIP', 'Finished Good'])
            ->select('id', 'name')
            ->get();

        $customers = Customer::select('id', 'username')->get();

        // Ambil supplier unik dari parts (termasuk yang belum ada supplier)
        $suppliers = Part::whereHas('category', function ($q) {
            $q->whereIn('name', ['Raw Material', 'ChildPart', 'Packaging', 'WIP', 'Finished Good']);
        })
            ->where(function ($query) {
                $query->whereNotNull('supplier')
                    ->where('supplier', '<>', '');
            })
            ->select('supplier')
            ->distinct()
            ->orderBy('supplier')
            ->get();

        $query = Part::with(['customer', 'package', 'plant', 'area', 'category'])
            ->whereHas('category', function ($q) {
                $q->whereIn('name', ['Raw Material', 'ChildPart', 'Packaging', 'WIP', 'Finished Good']);
            })
            ->orderBy('id', 'asc');

        // Filter berdasarkan customer
        if ($request->filled('customer_id')) {
            $query->where('id_customer', $request->customer_id);
        }

        // Filter tambahan berdasarkan kategori
        if ($request->filled('category_id')) {
            $query->where('id_category', $request->category_id);
        }

        // Filter berdasarkan Inv_id
        if ($request->filled('inv_id')) {
            $query->where('Inv_id', 'like', '%' . $request->inv_id . '%');
        }

        // Filter berdasarkan supplier
        if ($request->filled('supplier')) {
            if ($request->supplier === 'NULL') {
                $query->whereNull('supplier');
            } else {
                $query->where('supplier', $request->supplier);
            }
        }

        // Filter khusus untuk menampilkan yang belum ada supplier
        if ($request->filled('show_null_supplier') && $request->show_null_supplier) {
            $query->whereNull('supplier');
        }

        $perPage = $request->get('per_page', 10);
        $parts = $query->paginate($perPage);

        return view('Part.index_all', compact('parts', 'categories', 'suppliers', 'customers'));
    }

    public function indexCustomer(Request $request)
    {
        // Hanya ambil kategori Finish Good dan WIP
        $categories = Category::whereIn('name', ['Finished Good', 'WIP'])
            ->select('id', 'name')
            ->get();

        $customers = Customer::select('id', 'username')->get();

        $query = Part::with(['customer', 'package', 'plant', 'area', 'category'])
            ->whereNull('supplier') // Hanya ambil yang supplier-nya NULL
            ->whereHas('category', function ($q) {
                $q->whereIn('name', ['WIP', 'Finished Good']); // Filter kategori
            })
            ->orderBy('id', 'asc');

        // Filter tambahan berdasarkan kategori (jika dipilih)
        if ($request->filled('category_id')) {
            $query->where('id_category', $request->category_id);
        }

        // Filter berdasarkan Inv_id
        if ($request->filled('inv_id')) {
            $query->where('Inv_id', 'like', '%' . $request->inv_id . '%');
        }

        // Filter berdasarkan customer
        if ($request->filled('customer_id')) {
            $query->where('id_customer', $request->customer_id);
        }

        $perPage = $request->get('per_page', 10);
        $parts = $query->paginate($perPage);

        return view('Part.index_customer', compact('parts', 'categories', 'customers'));
    }

    public function indexSupplier(Request $request)
    {
        // Ambil ketiga kategori
        $categories = Category::whereIn('name', ['Raw Material', 'ChildPart', 'Packaging'])
            ->select('id', 'name')
            ->get();

        // Ambil supplier unik dari parts (termasuk yang belum ada supplier)
        $suppliers = Part::whereHas('category', function ($q) {
            $q->whereIn('name', ['Raw Material', 'ChildPart', 'Packaging']);
        })
            ->where(function ($query) {
                $query->whereNotNull('supplier')
                    ->where('supplier', '<>', '');
            })
            ->select('supplier')
            ->distinct()
            ->orderBy('supplier')
            ->get();

        $query = Part::with(['customer', 'package', 'plant', 'area', 'category'])
            ->whereHas('category', function ($q) {
                $q->whereIn('name', ['Raw Material', 'ChildPart', 'Packaging']);
            })
            ->orderBy('id', 'asc');

        // Filter tambahan berdasarkan kategori
        if ($request->filled('category_id')) {
            $query->where('id_category', $request->category_id);
        }

        // Filter berdasarkan Inv_id
        if ($request->filled('inv_id')) {
            $query->where('Inv_id', 'like', '%' . $request->inv_id . '%');
        }

        // Filter berdasarkan supplier
        if ($request->filled('supplier')) {
            if ($request->supplier === 'NULL') {
                $query->whereNull('supplier');
            } else {
                $query->where('supplier', $request->supplier);
            }
        }

        // Filter khusus untuk menampilkan yang belum ada supplier
        if ($request->filled('show_null_supplier') && $request->show_null_supplier) {
            $query->whereNull('supplier');
        }

        $perPage = $request->get('per_page', 10);
        $parts = $query->paginate($perPage);

        return view('Part.index_supplier', compact('parts', 'categories', 'suppliers'));
    }


    public function create()
    {
        return view('Part.create', [
            'customers' => Customer::all(),
            'plants' => Plant::all(),
            'areas' => Area::all(),
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request)
    {

        // Validasi awal untuk field umum (tanpa id_area karena akan dicari/dibuat manual)
        $validated = $request->validate([
            'Inv_id' => 'required',
            'Part_name' => 'required',
            'Part_number' => 'required',
            'id_customer' => 'required|exists:tbl_customer,id',
            'id_category' => 'required|exists:tbl_category,id',
            'id_plan' => 'required|exists:tbl_plan,id',
            'nama_area' => 'required|string',
            'type_pkg' => 'required',
            'qty' => 'required|integer',
        ]);
        $duplicate = Part::where('Inv_id', $validated['Inv_id'])
            ->where('id_customer', $validated['id_customer'])
            ->first();

        if ($duplicate) {
            return redirect()->back()
                ->withErrors(['Inv_id' => 'Part dengan INV ID dan Customer ini sudah ada.'])
                ->withInput();
        }

        // Cari atau buat Area
        $area = Area::firstOrCreate(
            ['id_plan' => $validated['id_plan'], 'nama_area' => $validated['nama_area']]
        );

        // Buat part baru
        $part = Part::create([
            'Inv_id' => $validated['Inv_id'],
            'Part_name' => $validated['Part_name'],
            'Part_number' => $validated['Part_number'],
            'id_customer' => $validated['id_customer'],
            'id_category' => $validated['id_category'],
            'id_plan' => $validated['id_plan'],
            'id_area' => $area->id,
        ]);

        // Buat package-nya
        Package::create([
            'type_pkg' => $validated['type_pkg'],
            'qty' => $validated['qty'],
            'id_part' => $part->id,
        ]);

        return redirect()->route('parts.index')->with('success', 'Part created successfully.');
    }

    public function edit(Part $part)
    {
        return view('Part.edit', [
            'part' => $part->load('package'),
            'customers' => Customer::all(),
            'plants' => Plant::all(),
            'areas' => Area::all(),
        ]);
    }

    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'Part_name' => 'sometimes',
            'Part_number' => 'sometimes',
            'id_customer' => 'sometimes|exists:tbl_customer,id',
            'id_plan' => 'sometimes',
            'id_area' => 'sometimes',
            'type_pkg' => 'sometimes',
            'qty' => 'sometimes',
        ]);

        // Update data utama Part
        $part->update([
            'Part_number' => $validated['Part_number'] ?? $part->Part_number,
            'Part_name' => $validated['Part_name'] ?? $part->Part_name,
            'id_customer' => $validated['id_customer'] ?? $part->id_customer,
            'id_plan' => $validated['id_plan'] ?? $part->id_plan,
            'id_area' => $validated['id_area'] ?? $part->id_area,
        ]);

        // Cek apakah relasi package sudah ada
        if ($part->package) {
            // Update existing package
            $part->package->update([
                'type_pkg' => $validated['type_pkg'] ?? $part->package->type_pkg,
                'qty' => $validated['qty'] ?? $part->package->qty,
            ]);
        } else {
            //  Create new package if not exists
            $part->package()->create([
                'type_pkg' => $validated['type_pkg'] ?? null,
                'qty' => $validated['qty'] ?? 0,
            ]);
        }

        return redirect()->route('parts.all')->with('success', 'Part updated successfully.');
    }

    // select part area
    public function getAreas($plantId)
    {
        $areas = Area::where('id_plan', $plantId)->get();

        return response()->json($areas);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $importer = new PartsImport();
            Excel::import($importer, $request->file('file'));

            $logs = $importer->getLogs();
            $successCount = $importer->getSuccessCount();
            $errorCount = count($logs);

            // Tentukan redirect berdasarkan parameter atau kondisi tertentu
            if ($request->has('redirect_to') && $request->redirect_to === 'supplier') {
                return redirect()
                    ->route('parts.index.supplier')
                    ->with([
                        'success' => "Import berhasil! $successCount data berhasil diimport. $errorCount baris bermasalah.",
                        'import_logs' => $logs,
                    ]);
            }elseif ($request->has('redirect_to') && $request->redirect_to === 'customer') {
                return redirect()
                    ->route('parts.index.customer')
                    ->with([
                        'success' => "Import berhasil! $successCount data berhasil diimport. $errorCount baris bermasalah.",
                        'import_logs' => $logs,
                    ]);
            }elseif ($request->has('redirect_to') && $request->redirect_to === 'all') {
                return redirect()
                    ->route('parts.index')
                    ->with([
                        'success' => "Import berhasil! $successCount data berhasil diimport. $errorCount baris bermasalah.",
                        'import_logs' => $logs,
                    ]);
            }



        } catch (\Exception $e) {
            Log::error('Import gagal: ' . $e->getMessage(), ['exception' => $e]);
            $errorMessage = 'Terjadi kesalahan saat melakukan import: ' . $e->getMessage();

            if ($request->has('redirect_to') && $request->redirect_to === 'supplier') {
                return redirect()->route('parts.index.supplier')->with('error', $errorMessage);
            }
            if ($request->has('redirect_to') && $request->redirect_to === 'customer') {
                return redirect()->route('parts.index.customer')->with('error', $errorMessage);
            }

            return redirect()->route('parts.index')->with('error', $errorMessage);
        }
    }

    // export
    protected function generateExportFilename($export_type, $category_id = null)
    {
        $baseName = 'parts_export_';

        $typeMapping = [
            'customer' => 'customer',
            'supplier' => 'supplier',
            'all' => 'all_data'
        ];

        $filename = $baseName . $typeMapping[$export_type];

        if ($category_id) {
            $filename .= '_category_' . $category_id;
        }

        return $filename . '_' . now()->format('Ymd_His') . '.xlsx';
    }
    public function export(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer',
            'export_type' => 'nullable|in:customer,supplier,all'
        ]);

        $category_id = $validated['category_id'] ?? null;
        $export_type = $validated['export_type'] ?? 'all';

        $filename = $this->generateExportFilename($export_type, $category_id);

        return Excel::download(
            new PartsExport($category_id, $export_type),
            $filename
        );
    }

    public function deleteMultiple(Request $request)
    {
        $ids = explode(',', $request->ids);

        // Validasi bahwa user memiliki akses untuk menghapus
        if (empty($ids)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        // Hapus multiple records
        Part::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', 'Item yang dipilih telah berhasil dihapus.');
    }
}
