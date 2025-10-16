<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Forecast;
use App\Models\Inventory;
use App\Models\Part;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Imports\ForecastImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\DailyStockLog;
use App\Models\WorkDays;
use App\Models\Bom;
use App\Exports\ForecastExport;
use App\Models\Category;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $query = Forecast::with(['part.customer', 'part.category'])
            ->join('tbl_part', 'tbl_forecast.id_part', '=', 'tbl_part.id')
            ->join('tbl_category', 'tbl_part.id_category', '=', 'tbl_category.id')
            ->select('tbl_forecast.*')
            // Urutkan berdasarkan prioritas kategori sesuai dengan CategorySeeder
            ->orderByRaw("
                CASE
                    WHEN tbl_category.name = 'Finished Good' THEN 1
                    WHEN tbl_category.name = 'Raw Material' THEN 2
                    WHEN tbl_category.name = 'WIP' THEN 3
                    WHEN tbl_category.name = 'ChildPart' THEN 4
                    WHEN tbl_category.name = 'Packaging' THEN 5
                    ELSE 6
                END ASC
            ")
            // ->orderBy('tbl_part.Inv_id', 'asc')
            ->orderBy('tbl_forecast.updated_at', 'desc');

        // Filter berdasarkan customer (username)
        if ($request->filled('customer')) {
            $query->whereHas('part.customer', function ($q) use ($request) {
                $q->where('username', $request->customer);
            });
        }

        if ($request->category) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category);
            });
        }

        // Filter berdasarkan forecast_month
        if ($request->filled('forecast_month')) {
            $month = Carbon::createFromFormat('Y-m', $request->forecast_month)->startOfMonth()->format('Y-m-d');
            $query->where('forecast_month', $month);
        }

        // Filter berdasarkan inv_id
        if ($request->filled('inv_id')) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inv_id . '%');
            });
        }

        $perPage = $request->get('per_page', 25);
        $forecasts = $query->paginate($perPage);
        $categories = Category::all();
        // Ambil list customer untuk select option
        $customers = Part::with('customer')
            ->get()
            ->pluck('customer')
            ->unique('id')
            ->filter()
            ->values();

        return view('Forecast.index', compact(
            'forecasts',
            'customers',
            'categories'
        ));
    }

    public function create()
    {
        $parts = Part::with('customer')->get();
        return view('Forecast.create', compact('parts'));
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:tbl_part,id',
            'forecast_month' => 'required|date_format:Y-m',
            'po_pcs' => 'required|integer|min:1',
            'frequensi_delivery' => 'nullable|integer|min:1', // Tambahan field frequensi_delivery
        ]);

        $part = Part::findOrFail($validated['id']);
        $forecastMonth = Carbon::createFromFormat('Y-m', $validated['forecast_month'])->startOfMonth();

        // Cek duplikat forecast
        $exists = Forecast::where('id_part', $validated['id'])
            ->where('forecast_month', $forecastMonth)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Forecast untuk bulan tersebut sudah ada. Silakan edit atau pilih bulan lain.');
        }

        // Cari hari kerja
        $workDay = WorkDays::whereDate('month', $forecastMonth)->first();

        if (!$workDay || $workDay->hari_kerja == 0) {
            return redirect()->back()->with('error', 'Hari kerja untuk bulan ' . $forecastMonth->format('F Y') . ' belum tersedia.');
        }

        $poPcs = (int) $validated['po_pcs'];
        $hariKerja = (int) $workDay->hari_kerja;
        $frequensiDelivery = isset($validated['frequensi_delivery']) ? (int) $validated['frequensi_delivery'] : null;

        // Hitung min dan max untuk produk utama - gunakan frequensi_delivery jika tersedia, fallback ke hari_kerja
        $divisor = $frequensiDelivery && $frequensiDelivery > 0 ? $frequensiDelivery : $hariKerja;
        $min = (int) ceil($poPcs / $divisor);

        // Special rule: if min starts with 5, multiply by 3
        if (substr((string) $min, 0, 1) === '5') {
            $min = $min * 3;
        }

        $max = $min * 3;

        // Cek apakah ini product (ada di BOM sebagai product_id)
        $isProduct = Bom::where('product_id', $part->id)->exists();

        // Simpan forecast utama
        $forecast = Forecast::updateOrCreate(
            ['id_part' => $part->id, 'forecast_month' => $forecastMonth],
            [
                'id_work' => $workDay->id,
                'hari_kerja' => $hariKerja,
                'frequensi_delivery' => $frequensiDelivery ?: $hariKerja,
                'PO_pcs' => $poPcs,
                'min' => $min,
                'max' => $max,
                'is_product' => $isProduct,
                'is_component' => !$isProduct // Jika bukan product, maka adalah komponen
            ]
        );

        // Proses komponen BOM jika ini product
        if ($isProduct) {
            $this->handleBomComponentsStore($part, $forecast);
        }

        // Update daily stock log
        $dailyLogs = DailyStockLog::where('id_inventory', $part->id)->get();
        foreach ($dailyLogs as $log) {
            $log->stock_per_day = ($min > 0) ? floor($log->Total_qty / $min) : 0;
            $log->save();
        }

        return redirect()->route('forecast.index')
            ->with('success', 'Forecast berhasil disimpan. ' . ($isProduct ? 'Komponen BOM juga telah diupdate.' : ''));
    }

    protected function handleBomComponentsStore(Part $product, Forecast $forecast)
    {
        $bomComponents = Bom::with('component')
            ->where('product_id', $product->id)
            ->get();

        foreach ($bomComponents as $bom) {
            if (!$bom->component) {
                continue;
            }

            // Hitung nilai komponen berdasarkan produk utama dan quantity BOM
            $componentPoPcs = (int) ceil($forecast->PO_pcs * (float) $bom->quantity);
            $componentMin = (int) ceil($forecast->min * (float) $bom->quantity);
            $componentMax = (int) ceil($componentMin * (float) $bom->quantity);

            // Simpan forecast komponen
            Forecast::updateOrCreate(
                [
                    'id_part' => $bom->component_id,
                    'forecast_month' => $forecast->forecast_month
                ],
                [
                    'id_work' => $forecast->id_work,
                    'hari_kerja' => $forecast->hari_kerja,
                    'frequensi_delivery' => $forecast->frequensi_delivery ?? $forecast->hari_kerja,
                    'PO_pcs' => $componentPoPcs,
                    'min' => $componentMin,
                    'max' => $componentMax,
                    'is_component' => true,
                    'parent_forecast_id' => $forecast->id
                ]
            );
        }
    }

    protected function handleBomComponents(Part $product, Forecast $forecast, int $minProduk, int $maxProduk)
    {
        $komponenBom = Bom::with('component')
            ->where('product_id', $product->id)
            ->get();

        foreach ($komponenBom as $bom) {
            if (!$bom->component) {
                continue;
            }

            // MIN: Gunakan min produk tanpa dikalikan quantity BOM
            $minKomponen = $minProduk;

            // MAX: Kalikan max produk dengan quantity BOM
            $maxKomponen = (int) ceil($maxProduk * (float) $bom->quantity);

            // Simpan forecast komponen
            Forecast::updateOrCreate(
                [
                    'id_part' => $bom->component_id,
                    'forecast_month' => $forecast->forecast_month
                ],
                [
                    'id_work' => $forecast->id_work,
                    'hari_kerja' => $forecast->hari_kerja,
                    'frequensi_delivery' => $forecast->frequensi_delivery ?? $forecast->hari_kerja,
                    'PO_pcs' => $minKomponen * $forecast->hari_kerja,
                    'min' => $minKomponen,
                    'max' => $maxKomponen,
                    'issued_at' => $forecast->issued_at,
                    'is_component' => true,
                    'parent_forecast_id' => $forecast->id
                ]
            );
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
        ]);

        try {
            $importer = new ForecastImport();
            Excel::import($importer, $request->file('file'));

            $logs = $importer->getLogs();

            if (count($logs) > 0) {
                Session::flash('import_logs', $logs);
            }

            return redirect()->route('forecast.index')->with('success', 'Import selesai.');
        } catch (\Exception $e) {
            Log::error('Import forecast gagal', ['error' => $e->getMessage()]);

            return redirect()->route('forecast.index')->with([
                'error' => 'Terjadi kesalahan saat import: ' . $e->getMessage(),
            ]);
        }
    }

    // Fungsi untuk menghapus forecast
    public function deleteforecast(Request $request)
    {
        $ids = explode(',', $request->ids);

        // Validasi bahwa user memiliki akses untuk menghapus
        if (empty($ids)) {
            return redirect()->back()->with('error', 'No items selected.');
        }

        // Hapus multiple records
        Forecast::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', 'Item yang dipilih telah berhasil dihapus.');
    }

    public function export(Request $request)
    {
        // Cek jumlah data sebelum export
        $query = \App\Models\Forecast::query();
        if ($request->filled('customer')) {
            $query->whereHas('part.customer', function ($q) use ($request) {
                $q->where('username', $request->customer);
            });
        }
        if ($request->category) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('id_category', $request->category);
            });
        }
        if ($request->filled('forecast_month')) {
            $month = \Carbon\Carbon::createFromFormat('Y-m', $request->forecast_month)->startOfMonth()->format('Y-m-d');
            $query->where('forecast_month', $month);
        }
        if ($request->filled('inv_id')) {
            $query->whereHas('part', function ($q) use ($request) {
                $q->where('Inv_id', 'like', '%' . $request->inv_id . '%');
            });
        }
        $total = $query->count();
        if ($total > 5000) {
            return redirect()->route('forecast.index')->with('error', 'Export gagal: Data terlalu banyak (' . $total . ' baris). Silakan gunakan filter untuk memperkecil data yang diexport (max 5000  baris).');
        }
        $fileName = 'forecast_data_Export_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(
            new ForecastExport(
                $request->customer,
                $request->forecast_month,
                $request->category,
                $request->inv_id
            ),
            $fileName
        );
    }
}