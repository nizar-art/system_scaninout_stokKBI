<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\DailyStockLog;
use App\Models\Part;
use App\Models\Category;
use App\Models\Forecast;
use App\Models\Customer;

class PublicDashboardController extends Controller
{
    // Define valid customers for public display
    protected $validPublicCustomers = [
        'HPM', 'ADM', 'MMKI', 'ITSP', 'TMMIN'
    ];

    public function publicDashboard()
    {
        return view('Dashboard.public');
    }

    // Public Customer data API
    public function getPublicCustomers()
    {
        try {
            // Return only our specific customers including combined Denso option
            $validCustomers = [
                'HPM', 'ADM', 'MMKI', 'ITSP', 'TMMIN'
            ];
            $customers = [];
            foreach ($validCustomers as $cust) {
                $customers[] = ['username' => $cust];
            }
            return response()->json($customers);
        } catch (Exception $e) {
            Log::error('Error getting customers: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    // Public Categories API
    public function getPublicCategories()
    {
        $categories = Category::select('id', 'name')->get();
        return response()->json($categories);
    }

    // Public dashboard statistics API
    public function getPublicStats(Request $request)
    {
        try {
            $date = $request->query('date');
            $customer = $request->query('customer');
            $category = $request->query('category');
            $validCustomers = $this->validPublicCustomers;

            $query = DailyStockLog::with('part.category', 'part.customer');

            // Apply date filter
            if ($date) {
                $query->whereDate('date', $date);
            }

            // Apply customer filter or restrict to valid customers
            if ($customer) {
                // Handle special case for combined customers
                if ($customer === "Denso (DNIA+DENSO)") {
                    $query->whereHas('part.customer', function ($q) {
                        $q->whereIn('username', ['DENSO', 'DNIA']);
                    });
                } else if ($customer === "MMKI") {
                    $query->whereHas('part.customer', function ($q) {
                        $q->whereIn('username', ['MMKI', 'ITSP']);
                    });
                } else {
                    $query->whereHas('part.customer', function ($q) use ($customer) {
                        $q->where('username', $customer);
                    });
                }
            } else {
                // Default: only valid customers
                $query->whereHas('part.customer', function ($q) use ($validCustomers) {
                    $q->whereIn('username', $validCustomers);
                });
            }

            // Apply category filter
            if ($category) {
                $query->whereHas('part.category', function ($q) use ($category) {
                    $q->where('id', $category);
                });
            }

            $totalStock = $query->sum('Total_qty');
            $totalCustomers = count($validCustomers);
            $totalCategories = Category::count();

            // Get stock by category with filters applied
            $stockByCategory = $query->get()
                ->groupBy(function ($log) {
                    return $log->part->category->name ?? 'Unknown';
                })
                ->map(function ($group) {
                    return $group->sum('Total_qty');
                });

            $finishedGoodStock = $stockByCategory['Finished Good'] ?? 0;
            $wipStock = $stockByCategory['WIP'] ?? 0;
            $packagingStock = $stockByCategory['Packaging'] ?? 0;
            $childPartStock = $stockByCategory['ChildPart'] ?? 0;
            $rawMaterialStock = $stockByCategory['Raw Material'] ?? 0;

            $lastUpdate = DailyStockLog::latest('date')->first();
            $lastUpdateFormatted = $lastUpdate ?
                $lastUpdate->date->format('d M, Y H:i') :
                'No data';

            return response()->json([
                'totalStock' => $totalStock,
                'finishedGoodStock' => $finishedGoodStock,
                'wipStock' => $wipStock,
                'packagingStock' => $packagingStock,
                'childPartStock' => $childPartStock,
                'rawMaterialStock' => $rawMaterialStock,
                'totalCustomers' => $totalCustomers,
                'totalCategories' => $totalCategories,
                'lastUpdate' => $lastUpdateFormatted
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getPublicStats: ' . $e->getMessage());
            return response()->json([
                'totalStock' => 0,
                'finishedGoodStock' => 0,
                'wipStock' => 0,
                'packagingStock' => 0,
                'childPartStock' => 0,
                'rawMaterialStock' => 0,
                'totalCustomers' => 0,
                'totalCategories' => 0,
                'lastUpdate' => 'Error loading data'
            ], 500);
        }
    }

    // Get public STO Chart data
    public function getPublicStoChartData(Request $request)
    {
        $month = $request->query('month');
        $date = $request->query('date', now()->toDateString());
        $customer = $request->query('customer');
        $category = $request->query('category');
        $validCustomers = $this->validPublicCustomers;

        $monthDate = $month ? Carbon::parse($month) : now();

        // --- START: Improved fallback logic ---
        // Build base query for available dates with customer/category filter
        $dateQuery = DailyStockLog::select('date')->distinct();

        if ($customer) {
            $dateQuery->whereHas('part.customer', function ($q) use ($customer) {
                $q->where('username', $customer);
            });
        } else {
            $dateQuery->whereHas('part.customer', function ($q) use ($validCustomers) {
                $q->whereIn('username', $validCustomers);
            });
        }
        if ($category) {
            $dateQuery->whereHas('part', function ($q) use ($category) {
                $q->where('id_category', $category);
            });
        }

        $availableDates = $dateQuery->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Fallback logic: today -> yesterday -> latest available
        if (in_array($date, $availableDates)) {
            $actualDate = $date;
            $isFallback = false;
            $isCurrentData = ($date === $today);
        } elseif (in_array($today, $availableDates)) {
            $actualDate = $today;
            $isFallback = true;
            $isCurrentData = true;
        } elseif (in_array($yesterday, $availableDates)) {
            $actualDate = $yesterday;
            $isFallback = true;
            $isCurrentData = false;
        } elseif (!empty($availableDates)) {
            $actualDate = $availableDates[0]; // Most recent available
            $isFallback = true;
            $isCurrentData = false;
        } else {
            $actualDate = $date;
            $isFallback = false;
            $isCurrentData = ($date === $today);
        }
        $isLatestData = $isFallback;
        // --- END: Improved fallback logic ---

        // Start building the main query for chart data
        $partsQuery = Part::whereHas('forecasts', function ($q) use ($monthDate) {
            $q->whereMonth('forecast_month', $monthDate->month)
                ->whereYear('forecast_month', $monthDate->year);
        });

        // Apply customer filter - no combining, simple filter
        if ($customer) {
            $partsQuery->whereHas('customer', function ($q) use ($customer) {
                $q->where('username', $customer);
            });
        } else {
            // Default to valid customers only
            $partsQuery->whereHas('customer', function ($q) use ($validCustomers) {
                $q->whereIn('username', $validCustomers);
            });
        }

        // Apply category filter
        if ($category) {
            $partsQuery->where('id_category', $category);
        }

        $partsWithForecast = $partsQuery->pluck('id');

        // Return empty result if no parts found
        if ($partsWithForecast->isEmpty()) {
            return $this->getEmptyChartResponse($monthDate);
        }

        $query = DailyStockLog::with(['part.customer', 'part.category'])
            ->whereIn('id_inventory', $partsWithForecast)
            ->whereDate('date', $actualDate);

        // Filter for Raw Material status
        $query->when($category === 'Raw Material', function ($q) {
            $q->whereIn('status', ['OK', 'VIRGIN']);
        });

        $logs = $query->get();

        // If no data found
        if ($logs->isEmpty()) {
            return $this->getEmptyChartResponse($monthDate);
        }

        // Handle customer filter for specific customer view
        if ($customer) {
            $customerLogs = $logs->filter(function ($log) use ($customer) {
                return ($log->part->customer->username ?? '') === $customer;
            });
            
            $allCategories = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP'];
            $series = [];
            
            foreach ($allCategories as $categoryName) {
                if ($categoryName === 'Raw Material') {
                    // Filter only OK and VIRGIN status
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
            
            $chartData = [
                'categories' => [$customer],
                'series' => $series,
                'fg_data' => $series[0]['data'] ?? [], // Finished Good
                'material_data' => $series[1]['data'] ?? [], // Raw Material
                'chp_data' => $series[2]['data'] ?? [], // ChildPart
                'packaging_data' => $series[3]['data'] ?? [], // Packaging
                'wip_data' => $series[4]['data'] ?? [], // WIP
                'date_used' => $actualDate,
                'is_latest_data' => $isLatestData,
                'month_display' => $monthDate->format('F Y')
            ];
            
            // Add meta information
            $chartData['meta'] = [
                'requested_date' => $date,
                'actual_date' => $actualDate,
                'formatted_date' => Carbon::parse($actualDate)->format('d M Y'),
                'is_fallback' => $isFallback,
                'is_current' => $isCurrentData,
                'available_dates' => $availableDates,
                'data_status' => $isCurrentData ? 'current' : 'historical',
                'valid_customers' => $validCustomers
            ];
            
            return response()->json($chartData);
        }

        // Group by customer, then by category - no mapping of ITSP to MMKI
        $groupedData = $logs->groupBy(function ($log) {
            return $log->part->customer->username ?? 'Unknown';
        })->map(function ($customerGroup) {
            // For each customer, group by category
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

        // Filter to only include valid customers
        $filteredData = $groupedData->filter(function ($value, $key) use ($validCustomers) {
            return in_array($key, $validCustomers);
        });

        // If no customers remain after filtering, return empty data
        if ($filteredData->isEmpty()) {
            return $this->getEmptyChartResponse($monthDate);
        }

        // Get customer list and prepare categories array
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
            if ($category === 'Finished Good') $displayName = 'FG';
            if ($category === 'ChildPart') $displayName = 'CHP';
            if ($category === 'Raw Material') $displayName = 'Material';

            $series[] = [
                'name' => $displayName,
                'data' => $categoryData
            ];
        }

        $chartData = [
            'categories' => $customers,
            'series' => $series,
            'fg_data' => $series[0]['data'] ?? [], // Finished Good
            'material_data' => $series[1]['data'] ?? [], // Raw Material
            'chp_data' => $series[2]['data'] ?? [], // ChildPart
            'packaging_data' => $series[3]['data'] ?? [], // Packaging
            'wip_data' => $series[4]['data'] ?? [], // WIP
            'date_used' => $actualDate,
            'is_latest_data' => $isLatestData,
            'month_display' => $monthDate->format('F Y')
        ];
        
        // Add meta information
        $chartData['meta'] = [
            'requested_date' => $date,
            'actual_date' => $actualDate,
            'formatted_date' => Carbon::parse($actualDate)->format('d M Y'),
            'is_fallback' => $isFallback,
            'is_current' => $isCurrentData,
            'available_dates' => $availableDates,
            'data_status' => $isCurrentData ? 'current' : 'historical',
            'valid_customers' => $validCustomers
        ];
        
        return response()->json($chartData);
    }

    // Helper method for empty chart response
    protected function getEmptyChartResponse($monthDate)
    {
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
            'month_display' => $monthDate->format('F Y'),
            'meta' => [
                'data_status' => 'nodata'
            ]
        ]);
    }

    // Helper method to process data for a specific customer
    protected function processCustomerSpecificData($logs, $customer, $actualDate, $isLatestData, $monthDate, $combineCustomers = false)
    {
        // Filter logs for the specific customer
        if ($customer === "Denso (DNIA+DENSO)") {
            $customerLogs = $logs->filter(function ($log) {
                $username = $log->part->customer->username ?? '';
                return $username === 'DENSO' || $username === 'DNIA';
            });
            $displayCustomer = "DENSO";
        } else if (($customer === "MMKI" || $customer === "ITSP") && $combineCustomers) {
            // Only combine MMKI+ITSP when explicitly requested
            $customerLogs = $logs->filter(function ($log) {
                $username = $log->part->customer->username ?? '';
                return $username === 'MMKI' || $username === 'ITSP';
            });
            $displayCustomer = "MMKI";
        } else {
            $customerLogs = $logs->filter(function ($log) use ($customer) {
                return ($log->part->customer->username ?? '') === $customer;
            });
            $displayCustomer = $customer;
        }

        $allCategories = ['Finished Good', 'Raw Material', 'ChildPart', 'Packaging', 'WIP'];
        $series = [];
        
        foreach ($allCategories as $categoryName) {
            if ($categoryName === 'Raw Material') {
                // Filter only OK and VIRGIN status
                $sum = $customerLogs->filter(function ($log) use ($categoryName) {
                    return ($log->part->category->name ?? '') === $categoryName
                        && in_array($log->status, ['OK', 'VIRGIN']);
                })->sum('Total_qty');
            } else if ($categoryName === 'ChildPart' && $customer === "Denso (DNIA+DENSO)") {
                // Special handling for ChildPart category with DENSO+DNIA
                $sum = $customerLogs->filter(function ($log) {
                    return ($log->part->category->name ?? '') === 'ChildPart';
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

        return [
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
        ];
    }

    // Helper method to process data for all customers
    protected function processAllCustomersData($logs, $actualDate, $isLatestData, $monthDate, $validCustomers, $combineCustomers = false)
    {
        // Group by customer, then by category
        $groupedData = $logs->groupBy(function ($log) use ($combineCustomers) {
            $username = $log->part->customer->username ?? 'Unknown';
            
            // Map ITSP to MMKI only when combining is enabled
            if ($username === 'ITSP' && $combineCustomers) {
                return 'MMKI';
            }
            
            return $username;
        })->map(function ($customerGroup) {
            // For each customer, group by category
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
        
        // Handle special case for ChildPart category with DENSO/DNIA
        if (isset($groupedData['DENSO']) || isset($groupedData['DNIA'])) {
            // Create combined data for DENSO ChildPart
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

        // Filter to only include valid customers
        $filteredData = $groupedData->filter(function ($value, $key) use ($validCustomers) {
            return in_array($key, $validCustomers);
        });

        // If no customers remain after filtering, return empty data
        if ($filteredData->isEmpty()) {
            return [
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
            ];
        }

        // Get customer list and prepare categories array
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
            if ($category === 'Finished Good') $displayName = 'FG';
            if ($category === 'ChildPart') $displayName = 'CHP';
            if ($category === 'Raw Material') $displayName = 'Material';

            $series[] = [
                'name' => $displayName,
                'data' => $categoryData
            ];
        }

        return [
            'categories' => $customers,
            'series' => $series,
            'fg_data' => $series[0]['data'] ?? [], // Finished Good
            'material_data' => $series[1]['data'] ?? [], // Raw Material
            'chp_data' => $series[2]['data'] ?? [], // ChildPart
            'packaging_data' => $series[3]['data'] ?? [], // Packaging
            'wip_data' => $series[4]['data'] ?? [], // WIP
            'date_used' => $actualDate,
            'is_latest_data' => $isLatestData,
            'month_display' => $monthDate->format('F Y')
        ];
    }

    // Get daily stock classification for public dashboard
    public function getPublicDailyStockClassification(Request $request)
    {
        $customer = $request->query('customer');
        $category = $request->query('category');
        $date = $request->query('date');
        $month = $request->query('month');
        $validCustomers = $this->validPublicCustomers;
        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $yesterday = $today->copy()->subDay();

        // Parse selected date or month or default to today
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

        // Query parts with forecasts for this month
        $partsQuery = Part::query()
            ->whereHas('forecasts', function ($q) use ($monthDate) {
                $q->whereMonth('forecast_month', $monthDate->month)
                    ->whereYear('forecast_month', $monthDate->year);
            });

        // Apply customer filter
        if ($customer) {
            // Special case for combined customers
            if ($customer === "Denso ") {
                $partsQuery->whereHas('customer', function ($q) {
                    $q->whereIn('username', ['DENSO', 'DNIA']);
                });
            } else if ($customer === "MMKI") {
                $partsQuery->whereHas('customer', function ($q) {
                    $q->whereIn('username', ['MMKI', 'ITSP']);
                });
            } else {
                $partsQuery->whereHas('customer', function ($q) use ($customer) {
                    $q->where('username', $customer);
                });
            }
        } else {
            // Default: only valid customers
            $partsQuery->whereHas('customer', function ($q) use ($validCustomers) {
                $q->whereIn('username', $validCustomers);
            });
        }

        // Apply category filter
        if ($category) {
            $partsQuery->where('id_category', $category);
        }

        $parts = $partsQuery->get();

        // Define stock classification keys and initialize data array
        $sortedKeys = ['>3', '3', '2.5', '2', '1.5', '1', '0.5', '0'];
        $initialData = array_fill_keys($sortedKeys, []);

        // Return empty data if no parts found
        if ($parts->isEmpty()) {
            return $this->getEmptyClassificationResponse($sortedKeys, $monthDate);
        }

        $partIds = $parts->pluck('id');

        // Find all available dates for these parts
        $availableDates = DailyStockLog::whereIn('id_inventory', $partIds)
            ->select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        // Determine which date to use, always fallback to most recent if requested date not available
        if ($date) {
            if (in_array($date, $availableDates)) {
                $preferredDate = $date;
                $isFallback = false;
            } else if (!empty($availableDates)) {
                // Fallback to most recent data
                $preferredDate = $availableDates[0];
                $isFallback = true;
            } else {
                // No data at all
                return $this->getEmptyClassificationResponse($sortedKeys, $monthDate);
            }
        } else {
            // No specific date requested, use most recent data
            if (!empty($availableDates)) {
                $preferredDate = $availableDates[0];
                $isFallback = false;
            } else {
                // No data at all
                return $this->getEmptyClassificationResponse($sortedKeys, $monthDate);
            }
        }

        // Is this data from today?
        $isCurrentData = ($preferredDate === $today->toDateString());

        // Get logs for the selected date
        $logsQuery = DailyStockLog::whereIn('id_inventory', $partIds)
            ->whereDate('date', $preferredDate);

        // Filter Raw Material by status
        $logsQuery->when($category === 'Raw Material', function ($q) {
            $q->whereIn('status', ['OK', 'VIRGIN']);
        });

        // Apply area filter if specified
        if ($request->filled('area')) {
            $logsQuery->whereHas('areaHead', function($q) use ($request) {
                $q->where('id', $request->area);
            });
        }
        
        $logs = $logsQuery->get();

        // Get forecast data for this month
        $forecasts = Forecast::whereIn('id_part', $partIds)
            ->whereMonth('forecast_month', $monthDate->month)
            ->whereYear('forecast_month', $monthDate->year)
            ->get()
            ->groupBy('id_part');

        // Process logs and group by stock per day classification
        $groupData = $this->processStockClassificationData($logs, $forecasts, $initialData);

        // Format the response data
        $data = $this->formatClassificationData($groupData, $sortedKeys);

        // Add meta information about the date
        $formattedDate = Carbon::parse($preferredDate)->format('d M Y');
        
        return response()->json([
            'series' => [
                [
                    'name' => 'Klasifikasi Stok Harian',
                    'data' => $data
                ]
            ],
            'last_update' => Carbon::parse($preferredDate)->format('d M, Y'),
            'data_source' => 'Log tanggal ' . $formattedDate,
            'month_display' => $monthDate->format('F Y'),
            'total_parts' => $parts->count(),
            'parts_with_data' => array_sum(array_map('count', $groupData)),
            'target_month' => $monthDate->format('Y-m'),
            'meta' => [
                'requested_date' => $date ?? $today->toDateString(),
                'actual_date' => $preferredDate,
                'formatted_date' => $formattedDate,
                'is_fallback' => $isFallback,
                'is_current' => $isCurrentData,
                'available_dates' => $availableDates,
                'data_status' => $isCurrentData ? 'current' : 'historical',
                'valid_customers' => $validCustomers
            ]
        ]);
    }

    // Helper method to process stock classification data
    protected function processStockClassificationData($logs, $forecasts, $initialData)
    {
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

            // Filter Raw Material status
            if (($part->category->name ?? '') === 'Raw Material' && !in_array($log->status, ['OK', 'VIRGIN'])) {
                continue;
            }

            $sumStock = $log->stock_per_day;
            $roundedStock = round($sumStock, 1);
            $categoryKey = $this->getStockClassificationKey($roundedStock);
            
            // Create unique key by combination of inventory ID, area, and plant
            $uniqueKey = $part->Inv_id . '|' . ($areaId ?? '-') . '|' . $plantName;
            
            // Ensure each combination is counted only once
            if (!isset($uniqueCombinations[$uniqueKey])) {
                $groupData[$categoryKey][] = [
                    'inv_id' => $part->Inv_id,
                    'area' => $areaId ?? '-',
                    'plant' => $plantName,
                    'stock' => $sumStock,
                ];
                $uniqueCombinations[$uniqueKey] = $categoryKey;
            }
        }
        
        return $groupData;
    }

    // Helper method to determine stock classification category key
    protected function getStockClassificationKey($roundedStock)
    {
        return match (true) {
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
    }

    // Helper method to format classification data for response
    protected function formatClassificationData($groupData, $sortedKeys)
    {
        $data = [];
        foreach ($sortedKeys as $key) {
            $items = $groupData[$key];
            $data[] = [
                'x' => $key,
                'y' => count($items),
                'meta' => !empty($items) ? collect($items)->pluck('inv_id')->implode(', ') : '-',
                'area_meta' => !empty($items) ? collect($items)->pluck('area')->implode(', ') : '-',
                'plant_meta' => !empty($items) ? collect($items)->pluck('plant')->unique()->implode(', ') : '-',
                'details' => array_values($items),
            ];
        }
        return $data;
    }

    // Helper method to return empty classification response
    protected function getEmptyClassificationResponse($sortedKeys, $monthDate, $message = 'Tidak ada data tersedia')
    {
        return response()->json([
            'series' => [
                [
                    'name' => 'Stock per Day Classification',
                    'data' => array_map(fn($key) => ['x' => $key, 'y' => 0, 'meta' => '-'], $sortedKeys)
                ]
            ],
            'last_update' => $message,
            'data_source' => 'Tidak ada data',
            'month_display' => $monthDate->format('F Y'),
            'meta' => [
                'data_status' => 'nodata'
            ]
        ]);
    }

    // Get inventory chart data
    public function stoChartData(Request $request)
    {
        $date = $request->input('date');
        $category = $request->input('category');
        $customer = $request->input('customer');

        $query = DailyStockLog::with(['part.customer', 'part.category']);

        // Get all available dates in descending order
        $availableDates = DailyStockLog::select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        // Determine which date to use with fallback logic
        $today = Carbon::today()->toDateString();

        if ($date && in_array($date, $availableDates)) {
            $actualDate = $date;
        } else if (!empty($availableDates)) {
            // If date is not specified or not available, use most recent date
            $actualDate = $availableDates[0];
        } else {
            // No data available
            return response()->json([
                'totalStock' => 0,
                'finishedGoodStock' => 0,
                'wipStock' => 0,
                'packagingStock' => 0,
                'childPartStock' => 0,
                'rawMaterialStock' => 0,
                'lastUpdate' => 'No data available'
            ]);
        }

        // Apply date filter using the determined date
        $query->whereDate('date', $actualDate);

        // Apply customer filter - simple filter without combining
        if ($customer) {
            $query->whereHas('part.customer', function ($q) use ($customer) {
                $q->where('username', $customer);
            });
        } else {
            // Default: only public valid customers
            $query->whereHas('part.customer', function ($q) {
                $q->whereIn('username', $this->validPublicCustomers);
            });
        }

        if ($category) {
            $query->whereHas('part.category', function ($q) use ($category) {
                $q->where('id', $category);
            });
        }

        $totalStock = $query->sum('Total_qty');

        // Get stock by category
        $stockByCategory = $query->get()
            ->groupBy(function ($log) {
                return $log->part->category->name ?? 'Unknown';
            })
            ->map(function ($group) {
                return $group->sum('Total_qty');
            });

        $lastUpdate = DailyStockLog::latest('date')->first();
        $lastUpdateFormatted = $lastUpdate ? 
            $lastUpdate->date->format('d M, Y H:i') . 
            ($actualDate !== $date && $date ? ' (Showing data from: ' . Carbon::parse($actualDate)->format('d M Y') . ')' : '') : 
            'No data';

        return response()->json([
            'totalStock' => $totalStock,
            'finishedGoodStock' => $stockByCategory['Finished Good'] ?? 0,
            'wipStock' => $stockByCategory['WIP'] ?? 0,
            'packagingStock' => $stockByCategory['Packaging'] ?? 0,
            'childPartStock' => $stockByCategory['ChildPart'] ?? 0,
            'rawMaterialStock' => $stockByCategory['Raw Material'] ?? 0,
            'lastUpdate' => $lastUpdateFormatted,
            'meta' => [
                'requested_date' => $date ?? null,
                'actual_date' => $actualDate,
                'is_fallback' => $date && $date !== $actualDate,
                'available_dates' => $availableDates
            ]
        ]);
    }

    /**
     * Get inventory table data for the public dashboard
     */
    public function getInventoryTableData(Request $request)
    {
        try {
            $requestedDate = $request->input('date', now()->toDateString());
            $customer = $request->input('customer');
            $category = $request->input('category');

            // Only these customers are allowed
            $validCustomers = $this->validPublicCustomers;

            // Use date for available dates - get all distinct dates
            $dateQuery = DB::table('tbl_daily_stock_logs')
                ->select('tbl_daily_stock_logs.date')
                ->distinct()
                ->orderBy('tbl_daily_stock_logs.date', 'desc');

            if ($customer) {
                // Simple customer filter without combining
                $dateQuery->join('tbl_part as p', 'tbl_daily_stock_logs.id_inventory', '=', 'p.id')
                        ->join('tbl_customer as c', 'p.id_customer', '=', 'c.id')
                        ->where('c.username', $customer);
            }

            if ($category && $category !== '0') {
                if (!$customer) {
                    $dateQuery->join('tbl_part as p', 'tbl_daily_stock_logs.id_inventory', '=', 'p.id');
                }
                $dateQuery->where('p.id_category', $category);
            }

            // Get all available dates with data
            $availableDates = $dateQuery->pluck('date')
                                       ->map(fn($d) => Carbon::parse($d)->toDateString())
                                       ->unique()
                                       ->values()
                                       ->toArray();

            $today = Carbon::today()->toDateString();

            // --- Fallback logic: always use latest available date if requested date not available ---
            if (in_array($requestedDate, $availableDates)) {
                $date = $requestedDate;
                $isFallback = false;
                $isCurrentData = ($requestedDate === $today);
            } elseif (!empty($availableDates)) {
                $date = $availableDates[0]; // Use the latest available date
                $isFallback = true;
                $isCurrentData = ($date === $today);
            } else {
                // No data available at all
                return response()->json([
                    'data' => [],
                    'meta' => [
                        'tanggal_diminta' => $requestedDate ? Carbon::parse($requestedDate)->format('d-m-Y') : '',
                        'tanggal_data' => '',
                        'formatted_date_indo' => '',
                        'is_fallback' => false,
                        'is_current' => false,
                        'available_dates' => [],
                        'count' => 0,
                        'data_status' => 'nodata',
                        'message' => 'Data tidak tersedia.'
                    ]
                ]);
           }

            $query = DB::table('tbl_daily_stock_logs as d')
                ->join('tbl_part as p', 'p.id', '=', 'd.id_inventory')
                ->join('tbl_customer as c', 'p.id_customer', '=', 'c.id')
                // Join with tbl_forecast to only get items that have forecast data
                ->join('tbl_forecast as f', function($join) {
                    $join->on('f.id_part', '=', 'p.id')
                         ->whereRaw('DATE_FORMAT(f.forecast_month, "%Y-%m") = DATE_FORMAT(d.created_at, "%Y-%m")');
                })
                ->select([
                    'd.date', 
                    'p.Inv_id as inv_id',
                    'p.Part_name as part_name',
                    'p.Part_number as part_no',
                    'c.username as customer',
                    // Get min_stock from joined forecast table directly
                    'f.min as min_stock',
                    // Get max_stock from joined forecast table directly
                    'f.max as max_stock',
                    DB::raw('COALESCE(d.Total_qty, 0) as qty'), 
                    DB::raw('CASE WHEN d.stock_per_day IS NULL THEN "NOFC" ELSE CAST(d.stock_per_day AS CHAR) END as day')
                ])
                ->whereDate('d.date', $date);

            // Apply customer filter - simple filter without combining
            if ($customer) {
                $query->where('c.username', $customer);
            } else {
                // Default to valid customers
                $query->whereIn('c.username', $validCustomers);
            }

            // Apply category filter
            if ($category && $category !== '0') {
                $query->where('p.id_category', $category);
            }

            // Only include records where min and max are not null
            $query->whereNotNull('f.min')
                  ->whereNotNull('f.max');

            $query->orderBy('c.username')
                  ->orderBy('p.Part_name');

            $inventoryData = $query->get()->toArray();
            $inventoryData = json_decode(json_encode($inventoryData), true);

            $formattedDate = Carbon::parse($date)->format('d-m-Y');

            return response()->json([
                'data' => $inventoryData,
                'meta' => [
                    'tanggal_diminta' => $requestedDate ? Carbon::parse($requestedDate)->format('d-m-Y') : '',
                    'tanggal_data' => $date ? Carbon::parse($date)->format('d-m-Y') : '',
                    'formatted_date_indo' => $date ? Carbon::parse($date)->format('d-m-Y') : '',
                    'is_fallback' => $isFallback,
                    'is_current' => $isCurrentData,
                    'available_dates' => array_map(function($tgl) {
                        return $tgl ? Carbon::parse($tgl)->format('d-m-Y') : '';
                    }, $availableDates),
                    'count' => count($inventoryData),
                    'data_status' => $isCurrentData ? 'current' : 'historical'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting inventory table data: ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'meta' => [
                    'error' => $e->getMessage(),
                    'data_status' => 'error'
                ]
            ], 500);
        }
    }
}