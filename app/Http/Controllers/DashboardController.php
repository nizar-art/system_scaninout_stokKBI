<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyStockLog;
use App\Models\Inventory;
use App\Models\Part;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Models\PlanStock;
use Carbon\CarbonInterface;
use App\Models\Forecast;
use Illuminate\Support\Facades\Log;
use Exception;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->query('month');
        $selectedCustomer = $request->query('customer');
        $selectedCategory = $request->query('category');
        $selectedDate = $request->query('date'); // Tambah ambil date

        $categories = Category::all();
        $customers = Customer::all();

        $months = Inventory::selectRaw("DATE_FORMAT(date, '%Y-%m') as month")
            ->distinct()
            ->orderBy('month')
            ->pluck('month');

        $stoQuery = Inventory::with('part.customer');

        if ($selectedDate) {
            $stoQuery->whereDate('date', $selectedDate);
        } elseif ($selectedMonth) {
            $stoQuery->whereMonth('date', Carbon::parse($selectedMonth)->month)
                ->whereYear('date', Carbon::parse($selectedMonth)->year);
        }

        if ($selectedCustomer) {
            $stoQuery->whereHas('part.customer', function ($query) use ($selectedCustomer) {
                $query->where('username', $selectedCustomer);
            });
        }

        if ($selectedCategory) {
            $stoQuery->whereHas('part.category', function ($query) use ($selectedCategory) {
                $query->where('id', $selectedCategory);
            });
        }

        $stoData = $stoQuery->get();

        $stoChartData = $stoData->groupBy(function ($item) {
            return $item->part->customer->username ?? 'Unknown';
        })->map(function ($group) {
            return $group->sum('plan_stock');
        });

        $dailyStockQuery = DailyStockLog::with('part.customer');

        if ($selectedDate) {
            $dailyStockQuery->whereDate('date', $selectedDate);
        } elseif ($selectedMonth) {
            $dailyStockQuery->whereMonth('date', Carbon::parse($selectedMonth)->month)
                ->whereYear('date', Carbon::parse($selectedMonth)->year);
        }

        if ($selectedCustomer) {
            $dailyStockQuery->whereHas('part.customer', function ($query) use ($selectedCustomer) {
                $query->where('username', $selectedCustomer);
            });
        }

        if ($selectedCategory) {
            $dailyStockQuery->whereHas('part.category', function ($query) use ($selectedCategory) {
                $query->where('id', $selectedCategory);
            });
        }

        $dailyStockData = $dailyStockQuery->get();

        return view('Dashboard.index', compact(
            'months',
            'customers',
            'stoData',
            'dailyStockData',
            'selectedMonth',
            'selectedCustomer',
            'selectedCategory',
            'stoChartData',
            'categories'
        ));
    }

    // daily stock chart data
    public function getStoChartData(Request $request)
    {
        $month = $request->query('month');
        $date = $request->query('date');
        $customer = $request->query('customer');
        $category = $request->query('category');

        $monthDate = $month ? Carbon::parse($month) : now();

        // Define valid customers
        $validCustomers = [
            'HPM',
            'ADM',
            'MMKI',
            'ITSP',
            'TMMIN',
            'ASMO-DMIA',
            'DENSO'
        ];
        $densoGroup = ['DENSO', 'DNIA'];

        // Start with base query
        $partsQuery = Part::whereHas('forecasts', function ($q) use ($monthDate) {
            $q->whereMonth('forecast_month', $monthDate->month)
                ->whereYear('forecast_month', $monthDate->year);
        });

        // Apply customer filter or restrict to valid customers
        if ($customer) {
            // Special case for "Denso (DNIA+DENSO)" selection
            if ($customer === "Denso ") {
                $partsQuery->whereHas('customer', function ($q) {
                    $q->whereIn('username', ['DENSO', 'DNIA']);
                });
            }
            // Special case for MMKI (include ITSP)
            elseif ($customer === "MMKI") {
                $partsQuery->whereHas('customer', function ($q) {
                    $q->whereIn('username', ['MMKI', 'ITSP']);
                });
            } else {
                $partsQuery->whereHas('customer', function ($q) use ($customer) {
                    $q->where('username', $customer);
                });
            }
        } else {
            // If no customer specified, default to only valid customers
            // MODIFIKASI: Jika kategori Finished Good, filter customer sesuai permintaan
            if ($category && $category == 'Finished Good') {
                $partsQuery->whereHas('customer', function ($q) use ($validCustomers, $densoGroup) {
                    $q->whereIn('username', array_merge($validCustomers, $densoGroup));
                });
            } else {
                $partsQuery->whereHas('customer', function ($q) use ($validCustomers) {
                    $q->whereIn('username', $validCustomers);
                });
            }
        }

        if ($category) {
            $partsQuery->where('id_category', $category);
        }

        $partsWithForecast = $partsQuery->pluck('id');

        $query = DailyStockLog::with(['part.customer', 'part.category'])
            ->whereIn('id_inventory', $partsWithForecast);

        // Filter out non-OK/VIRGIN statuses for Raw Material
        $query->when($category === 'Raw Material', function ($q) {
            $q->whereIn('status', ['OK', 'VIRGIN']);
        });

        if ($partsWithForecast->isEmpty()) {
            return response()->json([
                'categories' => [],
                'series' => [],
                'fg_data' => [],
                'wip_data' => [],
                'packaging_data' => [],
                'chp_data' => [],
                'material_data' => [],
                'date_used' => 'No data available',
                'is_latest_data' => false,
                'month_display' => $monthDate->format('F Y')
            ]);
        }

        $today = now()->format('Y-m-d');
        $targetDate = $date ? $date : $today;
        $isLatestData = false;
        $actualDate = $targetDate;

        $hasDataForDate = $query->clone()
            ->whereDate('date', $targetDate)
            ->exists();

        // MODIFIKASI: Jika user memilih date dan tidak ada data, return kosong (tanpa fallback)
        if ($date) {
            if (!$hasDataForDate) {
                return response()->json([
                    'categories' => [],
                    'series' => [],
                    'fg_data' => [],
                    'wip_data' => [],
                    'packaging_data' => [],
                    'chp_data' => [],
                    'material_data' => [],
                    'date_used' => $targetDate,
                    'is_latest_data' => false,
                    'month_display' => $monthDate->format('F Y')
                ]);
            } else {
                $query->whereDate('date', $targetDate);
            }
        } else {
            // Fallback ke data terakhir jika tidak ada data di hari ini
            if (!$hasDataForDate) {
                $isLatestData = true;
                $latestLog = $query->clone()
                    ->latest('date')
                    ->first();

                if ($latestLog) {
                    $actualDate = Carbon::parse($latestLog->date)->format('Y-m-d');
                    $query->whereDate('date', $actualDate);
                } else {
                    return response()->json([
                        'categories' => [],
                        'series' => [],
                        'fg_data' => [],
                        'wip_data' => [],
                        'packaging_data' => [],
                        'chp_data' => [],
                        'material_data' => [],
                        'date_used' => 'No data available',
                        'is_latest_data' => false,
                        'month_display' => $monthDate->format('F Y')
                    ]);
                }
            } else {
                $query->whereDate('date', $targetDate);
            }
        }

        $logs = $query->get();

        // Jika ada parameter customer, group hanya untuk customer itu
        if ($customer) {
            // Special case for "Denso (DNIA+DENSO)" selection
            if ($customer === "Denso ") {
                $customerLogs = $logs->filter(function ($log) {
                    $username = $log->part->customer->username ?? '';
                    return $username === 'DENSO' || $username === 'DNIA';
                });
            }
            // Special case for MMKI (include ITSP)
            elseif ($customer === "MMKI") {
                $customerLogs = $logs->filter(function ($log) {
                    $username = $log->part->customer->username ?? '';
                    return $username === 'MMKI' || $username === 'ITSP';
                });
            } else {
                $customerLogs = $logs->filter(function ($log) use ($customer) {
                    return ($log->part->customer->username ?? '') === $customer;
                });
            }

            $allCategories = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP'];
            $series = [];
            foreach ($allCategories as $categoryName) {
                if ($categoryName === 'Raw Material') {
                    // Filter hanya status OK dan VIRGIN
                    $sum = $customerLogs->filter(function ($log) use ($categoryName) {
                        return ($log->part->category->name ?? '') === $categoryName
                            && in_array($log->status, ['OK', 'VIRGIN']);
                    })->sum('Total_qty');
                } else {
                    $sum = $customerLogs->filter(function ($log) use ($categoryName) {
                        return ($log->part->category->name ?? '') === $categoryName;
                    })->sum('Total_qty');
                }

                $displayName = $categoryName;
                if ($categoryName === 'Finished Good') $displayName = 'FG';
                if ($categoryName === 'ChildPart') $displayName = 'CHP';
                if ($categoryName === 'Raw Material') $displayName = 'Material';

                $series[] = [
                    'name' => $displayName,
                    'data' => [$sum]
                ];
            }

            $displayCustomer = $customer;
            if ($customer === "Denso (DNIA+DENSO)") {
                $displayCustomer = "DENSO";
            } else if ($customer === "ITSP") {
                $displayCustomer = "MMKI";
            }

            return response()->json([
                'categories' => [$displayCustomer],
                'series' => $series,
                'fg_data' => $series[0]['data'] ?? [], // Finished Good
                'material_data' => $series[1]['data'] ?? [], // Raw Material
                'chp_data' => $series[2]['data'] ?? [], // ChildPart
                'packaging_data' => $series[3]['data'] ?? [], // Packaging
                'wip_data' => $series[4]['data'] ?? [], // WIP
                'date_used' => $actualDate,
                'is_latest_data' => $isLatestData,
                'month_display' => $monthDate->format('F Y')
            ]);
        }

        // MODIFIKASI: Untuk kategori Finished Good, filter customer dan gabungkan DNIA ke DENSO
        if ($category && $category == 'Finished Good') {
            // Group by customer, then by category
            $groupedData = $logs->groupBy(function ($log) {
                $username = $log->part->customer->username ?? 'Unknown';
                // Map ITSP to MMKI
                if ($username === 'ITSP') return 'MMKI';
                // Gabungkan DNIA ke DENSO
                if ($username === 'DNIA') return 'DENSO';
                return $username;
            })->map(function ($customerGroup) {
                // Untuk setiap customer, group by kategori
                return $customerGroup->groupBy(function ($log) {
                    return $log->part->category->name ?? 'Unknown';
                })->map(function ($categoryGroup, $categoryName) {
                    if ($categoryName === 'Raw Material') {
                        return $categoryGroup->filter(function ($log) {
                            return in_array($log->status, ['OK', 'VIRGIN']);
                        })->sum('Total_qty');
                    }
                    return $categoryGroup->sum('Total_qty');
                });
            });

            // Filter hanya customer yang diinginkan
            $filteredData = $groupedData->filter(function ($value, $key) use ($validCustomers) {
                return in_array($key, $validCustomers);
            });

            // Jika tidak ada data, return kosong
            if ($filteredData->isEmpty()) {
                return response()->json([
                    'categories' => [],
                    'series' => [],
                    'fg_data' => [],
                    'wip_data' => [],
                    'packaging_data' => [],
                    'chp_data' => [],
                    'material_data' => [],
                    'date_used' => $actualDate,
                    'is_latest_data' => $isLatestData,
                    'month_display' => $monthDate->format('F Y')
                ]);
            }

            // show kategori
            $customers = $filteredData->keys()->values();
            $allCategories = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP']; 

            // Prepare series data for stacked chart
            $series = [];
            foreach ($allCategories as $cat) {
                $categoryData = [];
                foreach ($customers as $cust) {
                    $categoryData[] = $filteredData[$cust][$cat] ?? 0;
                }
                // Map category names for display
                $displayName = $cat;
                if ($cat === 'Finished Good') $displayName = 'FG';
                if ($cat === 'ChildPart') $displayName = 'CHP';
                if ($cat === 'Raw Material') $displayName = 'Material';

                $series[] = [
                    'name' => $displayName,
                    'data' => $categoryData
                ];
            }

            return response()->json([
                'categories' => $customers,
                'series' => $series,
                'fg_data' => $series[0]['data'] ?? [], // Finished Good
                'material_data' => $series[1]['data'] ?? [], // Raw Material
                'chp_data' => $series[2]['data'] ?? [], // ChildPart
                'packaging_data' => $series[3]['data'] ?? [], // Packaging
                'wip_data' => $series[4]['data'] ?? [], // WIP
                'date_used' => $actualDate,
                'is_latest_data' => $isLatestData,
                'month_display' => $monthDate->format('F Y'),
                'valid_customers' => $validCustomers
            ]);
        }

        // Group by customer, then by category
        $groupedData = $logs->groupBy(function ($log) {
            $username = $log->part->customer->username ?? 'Unknown';

            // Map ITSP to MMKI
            if ($username === 'ITSP') {
                return 'MMKI';
            }

            return $username;
        })->map(function ($customerGroup) {
            // Untuk setiap customer, group by kategori
            return $customerGroup->groupBy(function ($log) {
                return $log->part->category->name ?? 'Unknown';
            })->map(function ($categoryGroup, $categoryName) {
                if ($categoryName === 'Raw Material') {
                    return $categoryGroup->filter(function ($log) {
                        return in_array($log->status, ['OK', 'VIRGIN']);
                    })->sum('Total_qty');
                }
                return $categoryGroup->sum('Total_qty');
            });
        });

        // Now handle special case for ChildPart category for DENSO/DNIA
        // Check if both DENSO and DNIA exist in our data
        if (isset($groupedData['DENSO']) || isset($groupedData['DNIA'])) {
            // Create combined data for DENSO
            $densoChildPartValue = 0;

            // Add DENSO ChildPart data if exists
            if (isset($groupedData['DENSO']) && isset($groupedData['DENSO']['ChildPart'])) {
                $densoChildPartValue += $groupedData['DENSO']['ChildPart'];
            }

            // Add DNIA ChildPart data if exists
            if (isset($groupedData['DNIA']) && isset($groupedData['DNIA']['ChildPart'])) {
                $densoChildPartValue += $groupedData['DNIA']['ChildPart'];

                // If DENSO doesn't exist yet, create it
                if (!isset($groupedData['DENSO'])) {
                    $groupedData['DENSO'] = [];
                }

                // Save the combined value to DENSO
                $groupedData['DENSO']['ChildPart'] = $densoChildPartValue;

                // Remove DNIA from the dataset if it only had ChildPart
                if (count($groupedData['DNIA']) === 1 && isset($groupedData['DNIA']['ChildPart'])) {
                    unset($groupedData['DNIA']);
                } else {
                    // Otherwise just remove the ChildPart category from DNIA
                    unset($groupedData['DNIA']['ChildPart']);
                }
            }
        }

        // filter customer (including empty)
        $filteredData = $groupedData->filter(function ($value, $key) use ($validCustomers) {
            // Only include customers that are in our valid list or it's the combined DENSO
            return in_array($key, $validCustomers);
        });

        // If no customers remain after filtering, return empty data
        if ($filteredData->isEmpty()) {
            return response()->json([
                'categories' => [],
                'series' => [],
                'fg_data' => [],
                'wip_data' => [],
                'packaging_data' => [],
                'chp_data' => [],
                'material_data' => [],
                'date_used' => $actualDate,
                'is_latest_data' => $isLatestData,
                'month_display' => $monthDate->format('F Y')
            ]);
        }

        // show kategori
        $customers = $filteredData->keys()->values();
        $allCategories = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP'];

        // Prepare series data for stacked chart
        $series = [];
        foreach ($allCategories as $category) {
            $categoryData = [];
            foreach ($customers as $customer) {
                $categoryData[] = $filteredData[$customer][$category] ?? 0;
            }

            // Map category names for display
            $displayName = $category;
            if ($category === 'Finished Good')
                $displayName = 'FG';
            if ($category === 'ChildPart')
                $displayName = 'CHP';
            if ($category === 'Raw Material')
                $displayName = 'Material';

            $series[] = [
                'name' => $displayName,
                'data' => $categoryData
            ];
        }

        return response()->json([
            'categories' => $customers,
            'series' => $series,
            'fg_data' => $series[0]['data'] ?? [], // Finished Good
            'material_data' => $series[1]['data'] ?? [], // Raw Material
            'chp_data' => $series[2]['data'] ?? [], // ChildPart
            'packaging_data' => $series[3]['data'] ?? [], // Packaging
            'wip_data' => $series[4]['data'] ?? [], // WIP
            'date_used' => $actualDate,
            'is_latest_data' => $isLatestData,
            'month_display' => $monthDate->format('F Y'),
            'valid_customers' => $validCustomers
        ]);
    }

    // daily stock classification
    public function getDailyStockClassification(Request $request)
    {
        $customer = $request->query('customer');
        $category = $request->query('category');
        $date = $request->query('date');
        $month = $request->query('month');
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $yesterday = $today->copy()->subDay();

        // Daftar customer valid (sinkron dengan getStoChartData)
        $validCustomers = [
            'HPM',
            'ADM',
            'MMKI',
            'ITSP',
            'TMMIN',
            'ASMO-DMIA',
            'DENSO'
        ];
        $densoGroup = ['DENSO', 'DNIA'];
        $fgCustomers = [
            'ADM',
            'TMMIN',
            'HPM',
            'MMKI',
            'ITSP',
            'DENSO',
            'DNIA',
            'ASMO-DMIA'
        ];

        // Parse tanggal yang dipilih atau bulan atau default ke hari ini
        if ($date) {
            $targetDate = Carbon::parse($date)->startOfDay();
            $monthDate = $targetDate->copy();
        } elseif ($month) {
            $monthDate = Carbon::parse($month);
            $targetDate = $today;
        } else {
            $monthDate = $today->copy();
            $targetDate = $today;
        }

        // Filter part sesuai forecast dan stok bulan ini
        $partsQuery = Part::query()
            ->whereHas('forecasts', function ($q) use ($monthDate) {
                $q->whereMonth('forecast_month', $monthDate->month)
                    ->whereYear('forecast_month', $monthDate->year);
            });

        // Sinkronisasi logika customer:
        if ($category && $category == 'Finished Good') {
            // Jika kategori Finished Good, filter customer sesuai permintaan
            if ($customer) {
                // Denso (DNIA+DENSO)
                if ($customer === "Denso ") {
                    $partsQuery->whereHas('customer', function ($q) {
                        $q->whereIn('username', ['DENSO', 'DNIA']);
                    });
                }
                // MMKI (include ITSP)
                elseif ($customer === "MMKI") {
                    $partsQuery->whereHas('customer', function ($q) {
                        $q->whereIn('username', ['MMKI', 'ITSP']);
                    });
                } else {
                    $partsQuery->whereHas('customer', function ($q) use ($customer) {
                        $q->where('username', $customer);
                    });
                }
            } else {
                // Jika tidak ada customer, filter FG customers
                $partsQuery->whereHas('customer', function ($q) use ($fgCustomers) {
                    $q->whereIn('username', $fgCustomers);
                });
            }
        } else {
            // Untuk kategori selain FG
            if ($customer) {
                if ($customer === "Denso ") {
                    $partsQuery->whereHas('customer', function ($q) {
                        $q->whereIn('username', ['DENSO', 'DNIA']);
                    });
                } elseif ($customer === "MMKI") {
                    $partsQuery->whereHas('customer', function ($q) {
                        $q->whereIn('username', ['MMKI', 'ITSP']);
                    });
                } else {
                    $partsQuery->whereHas('customer', function ($q) use ($customer) {
                        $q->where('username', $customer);
                    });
                }
            } else {
                // Jika tidak ada customer, filter valid customers
                $partsQuery->whereHas('customer', function ($q) use ($validCustomers, $densoGroup, $category) {
                    // Untuk kategori Finished Good, gabungkan densoGroup
                    if ($category && $category == 'Finished Good') {
                        $q->whereIn('username', array_merge($validCustomers, $densoGroup));
                    } else {
                        $q->whereIn('username', $validCustomers);
                    }
                });
            }
        }

        if ($category) {
            $partsQuery->where('id_category', $category);
        }

        $parts = $partsQuery->get();

        $sortedKeys = ['>3', '3', '2.5', '2', '1.5', '1', '0.5', '0'];
        $initialData = array_fill_keys($sortedKeys, []);

        if ($parts->isEmpty()) {
            return response()->json([
                'series' => [
                    [
                        'name' => 'Stock per Day Classification',
                        'data' => array_map(fn($key) => ['x' => $key, 'y' => 0, 'meta' => '-'], $sortedKeys)
                    ]
                ],
                'last_update' => 'Tidak ada data tersedia',
                'data_source' => 'Tidak ada data',
                'month_display' => $monthDate->format('F Y')
            ]);
        }

        $partIds = $parts->pluck('id');

        // MODIFIKASI: Jika user memilih date dan tidak ada data, return kosong (tanpa fallback)
        if ($date) {
            $preferredDate = $targetDate->toDateString();
            $hasDataOnDate = DailyStockLog::whereIn('id_inventory', $partIds)
                ->whereDate('date', $preferredDate)
                ->exists();

            if (!$hasDataOnDate) {
                return response()->json([
                    'series' => [
                        [
                            'name' => 'Stock per Day Classification',
                            'data' => array_map(fn($key) => ['x' => $key, 'y' => 0, 'meta' => '-'], $sortedKeys)
                        ]
                    ],
                    'last_update' => 'Tidak ada data untuk tanggal ' . $targetDate->format('d M Y'),
                    'data_source' => 'Tidak ada data',
                    'month_display' => $monthDate->format('F Y')
                ]);
            }
        } else {
            // Fallback ke data terakhir jika tidak ada data di hari ini
            $preferredDate = DailyStockLog::whereIn('id_inventory', $partIds)
                ->whereDate('date', $today)
                ->first()
                ? $today->toDateString()
                : (DailyStockLog::whereIn('id_inventory', $partIds)
                    ->whereDate('date', $yesterday)
                    ->first()
                    ? $yesterday->toDateString()
                    : (function () use ($partIds) {
                        $dateValue = DailyStockLog::whereIn('id_inventory', $partIds)
                            ->orderByDesc('date')
                            ->value('date');
                        return $dateValue ? Carbon::parse($dateValue)->toDateString() : null;
                    })()
                );
            if (!$preferredDate) {
                return response()->json([
                    'series' => [
                        [
                            'name' => 'Stock per Day Classification',
                            'data' => array_map(fn($key) => ['x' => $key, 'y' => 0, 'meta' => '-'], $sortedKeys)
                        ]
                    ],
                    'last_update' => 'Tidak ada data tersedia',
                    'data_source' => 'Tidak ada data',
                    'month_display' => $monthDate->format('F Y')
                ]);
            }
        }

        // Ambil semua log yang ada di tanggal tersebut
        $logsQuery = DailyStockLog::whereIn('id_inventory', $partIds)
            ->whereDate('date', $preferredDate);

        // Filter out non-OK/VIRGIN statuses for Raw Material
        $logsQuery->when($category === 'Raw Material', function ($q) {
            $q->whereIn('status', ['OK', 'VIRGIN']);
        });

        // Jika ada filter area (khusus direct dari dashboard, hidden)
        if ($request->filled('area')) {
            $logsQuery->whereHas('areaHead', function ($q) use ($request) {
                $q->where('id', $request->area);
            });
        }
        $logs = $logsQuery->get();

        // Forecast data
        $forecasts = Forecast::whereIn('id_part', $partIds)
            ->whereMonth('forecast_month', $monthDate->month)
            ->whereYear('forecast_month', $monthDate->year)
            ->get()
            ->groupBy('id_part');

        $groupData = $initialData;

        $uniqueCombinations = [];
        foreach ($logs as $log) {
            $part = $log->part;
            if (!$part) continue;
            $partId = $part->id;
            $areaId = $log->areaHead->id ?? null;
            $plantName = $log->areaHead->plant->id ?? '-';
            if (!isset($forecasts[$partId])) continue;
            $forecast = $forecasts[$partId][0] ?? null;
            if ($forecast && $forecast->min == 0 && $forecast->max == 0) continue;

            // Filter kategori Raw Material hanya status OK dan VIRGIN
            if (($part->category->name ?? '') === 'Raw Material' && !in_array($log->status, ['OK', 'VIRGIN'])) {
                continue;
            }

            // Untuk kategori Finished Good, hanya tampilkan customer FG
            if ($category && $category == 'Finished Good') {
                $cust = $part->customer->username ?? '';
                if (!in_array($cust, $fgCustomers)) continue; // Hanya FG customer, selain itu diabaikan
            }

            $sumStock = $log->stock_per_day;
            $roundedStock = round($sumStock, 1);
            $categoryKey = match (true) {
                $roundedStock > 3 => '>3',
                $roundedStock >= 2.6 && $roundedStock <= 3 => '3',
                $roundedStock >= 2.1 && $roundedStock <= 2.5 => '2.5',
                $roundedStock >= 1.6 && $roundedStock <= 2 => '2',
                $roundedStock >= 1.3 && $roundedStock <= 1.6 => '1.5',
                $roundedStock >= 0.6 && $roundedStock <= 1.2 => '1',
                $roundedStock >= 0.3 && $roundedStock <= 0.5 => '0.5',
                $roundedStock >= 0 && $roundedStock <= 0.2 => '0',
                default => '0',
            };
            $uniqueKey = $part->Inv_id . '|' . ($areaId ?? '-') . '|' . $plantName;
            if (!isset($uniqueCombinations[$uniqueKey])) {
                $groupData[$categoryKey][] = [
                    'inv_id' => $part->Inv_id,
                    'area' => $areaId ?? '-',
                    'plant' => $plantName,
                    'stock' => $sumStock,
                    'customer' => $part->customer->username ?? '-',
                ];
                $uniqueCombinations[$uniqueKey] = $categoryKey;
            }
        }

        $data = [];
        foreach ($sortedKeys as $key) {
            $items = $groupData[$key];
            $data[] = [
                'x' => $key,
                'y' => count($items),
                'meta' => !empty($items)
                    ? collect($items)->pluck('inv_id')->implode(', ')
                    : '-',
                'area_meta' => !empty($items)
                    ? collect($items)->pluck('area')->implode(', ')
                    : '-',
                'plant_meta' => !empty($items)
                    ? collect($items)->pluck('plant')->unique()->implode(', ')
                    : '-',
                'details' => array_values($items),
            ];
        }

        return response()->json([
            'series' => [
                [
                    'name' => 'Klasifikasi Stok Harian',
                    'data' => $data
                ]
            ],
            'last_update' => Carbon::parse($preferredDate)->format('d M, Y'),
            'data_source' => 'Log tanggal ' . Carbon::parse($preferredDate)->format('d M Y'),
            'month_display' => $monthDate->format('F Y'),
            'total_parts' => $parts->count(),
            'parts_with_data' => array_sum(array_map('count', $groupData)),
            'target_month' => $monthDate->format('Y-m')
        ]);
    }

    // Get categories for dashboard
    public function getCategories()
    {
        $desiredOrder = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP'];

        $allCategories = Category::select('id', 'name')->get();

        $orderedCategories = collect();

        // Add categories in the desired order
        foreach ($desiredOrder as $categoryName) {
            $category = $allCategories->firstWhere('name', $categoryName);
            if ($category) {
                $orderedCategories->push($category);
            }
        }

        $remainingCategories = $allCategories->whereNotIn('name', $desiredOrder);
        foreach ($remainingCategories as $category) {
            $orderedCategories->push($category);
        }

        return response()->json($orderedCategories->values());
    }
}