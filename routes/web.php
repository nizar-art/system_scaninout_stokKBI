<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyStockLogController;
use App\Http\Controllers\DashboardInOutController;
use App\Http\Controllers\ScanInController;
use App\Http\Controllers\ScanOutController;
use App\Http\Controllers\WorkDaysController;
use App\Http\Controllers\UserLogicController;
use App\Models\ImportLog;

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
*/
// routes/web.php
Route::get('/import/progress/{id}', function ($id) {
    return response()->json(ImportLog::find($id));
});

Route::prefix('/')->controller(AuthController::class)->group(function () {
    Route::get('/', 'showUser')->name('user.login');   // ⬅️ langsung ke login user
    Route::get('/user/login/scan', 'showUser')->name('user.login.scan');
    // Route::post('/admin/login', 'login')->name('admin.login.post');
    Route::post('/user/login/scan', 'userLogin')->name('user.login.post');
    // Route::post('/admin/logout', 'logout')->name('logout');
    Route::post('/user/logout/scan', 'logoutUser')->name('logout.user');
});



Route::middleware(['auth', 'user.only'])->group(function () {

    // 👇 ini jadi halaman awal setelah login (Dashboard)
    Route::get('/dashboard/inout', [DashboardInOutController::class, 'index'])
        ->name('dashboardinout.index');
    
    Route::prefix('scanInStok')
    ->controller(ScanInController::class)
    ->group(function () {

        // Halaman awal scanner
        Route::get('/', 'index')->name('scanInStok.index');

        // Proses hasil scan (ambil Inv_id dari QR code)
        Route::post('/scan', 'scan')->name('scan.instok');

        Route::get('/search', 'searchin')->name('scan.searchin');

        // Tampilkan form tambah stok (setelah barcode di-scan)
        Route::get('/edit/{inventory_id}', 'editReportin')->name('scan.edit.report.in');

        // Simpan hasil penambahan stok ke database
        Route::post('/store/{inventory_id}', 'storeHistoryin')->name('scan.store.history.in');
    });

    Route::prefix('scanOutStok')
    ->controller(ScanOutController::class)
    ->group(function () {

        // Halaman awal scanner OUT
        Route::get('/', 'index')->name('scanOutStok.index');

        // Proses hasil scan (ambil Inv_id dari QR code)
        Route::post('/scan', 'scan')->name('scan.outstok');

        Route::get('/search', 'searchout')->name('scan.searchout');

        // Tampilkan form pengurangan stok (setelah barcode di-scan)
        Route::get('/edit/{inventory_id}', 'editReportOut')->name('scan.edit.report.out');

        // Simpan hasil pengurangan stok ke database
        Route::post('/store/{inventory_id}', 'storeHistoryOut')->name('scan.store.history.out');
    });

});


Route::get('/keep-alive', function () {
    return response()->json(['status' => 'alive']);
})->name('keep-alive');