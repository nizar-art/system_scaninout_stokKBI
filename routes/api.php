<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------

|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// Public Dashboard Routes (No Authentication Required)
Route::prefix('public')->controller(PublicDashboardController::class)->group(function () {
    Route::get('/dashboard/customers', 'getPublicCustomers');
    Route::get('/dashboard/categories', 'getPublicCategories');
    Route::get('/dashboard/stats', 'getPublicStats');
    Route::get('/dashboard/sto-chart-data', 'getPublicStoChartData');
    Route::get('/dashboard/daily-stock-classification', 'getPublicDailyStockClassification');
});

// api route for inventory table data
Route::prefix('public/dashboard')->group(function () {
    // Add this new route for inventory table data
    Route::get('/inventory-table-data', [PublicDashboardController::class, 'getInventoryTableData']);
});