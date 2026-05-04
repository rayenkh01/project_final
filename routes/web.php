<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\FtpController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Business\MsisdnSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Operations\AggregationController;
use App\Http\Controllers\Operations\CdrLoadingController;
use App\Http\Controllers\Operations\ProviderController;
use App\Http\Controllers\Operations\ServiceController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
    ->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'resetPassword'])
    ->name('password.reset.custom');

Route::middleware('dashboard.access')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:business')->prefix('business')->name('business.')->group(function (): void {
        Route::get('/services/msisdn', [MsisdnSearchController::class, 'search'])
            ->name('msisdn.search');

        Route::get('/services/msisdn/excel', [MsisdnSearchController::class, 'excel'])
            ->name('msisdn.excel');
        Route::post('/services/msisdn/excel', [MsisdnSearchController::class, 'excelSearch'])
            ->name('msisdn.excel.search');

        Route::get('/alertes', [DashboardController::class, 'placeholder'])
            ->name('alerts.index')
            ->defaults('title', 'Alertes / historique des incidents');

        Route::get('/notifications/email', [DashboardController::class, 'placeholder'])
            ->name('notifications.email')
            ->defaults('title', 'Notification email');
    });

    Route::middleware('role:operationnel')->prefix('operations')->name('operations.')->group(function (): void {
        Route::get('/cdr/loading', [CdrLoadingController::class, 'index'])
            ->name('cdr.loading');

        Route::get('/aggregation', [AggregationController::class, 'index'])
            ->name('aggregation.index');

        Route::get('/cdr/suppression', [DashboardController::class, 'placeholder'])
            ->name('cdr.delete')
            ->defaults('title', 'Suppression CDR');

        Route::resource('services', ServiceController::class)
            ->parameters(['services' => 'service'])
            ->except(['show']);

        Route::resource('fournisseurs', ProviderController::class)
            ->names('providers')
            ->parameters(['fournisseurs' => 'provider'])
            ->except(['show']);
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/database', [DatabaseController::class, 'index'])
            ->name('database.index');
        Route::post('/database/import', [DatabaseController::class, 'runImport'])
            ->name('database.import');
        Route::post('/database/etl/toggle', [DatabaseController::class, 'toggleEtl'])
            ->name('database.etl.toggle');
        Route::post('/database/cleanup', [DatabaseController::class, 'cleanupOldData'])
            ->name('database.cleanup');

        Route::get('/ftp', [FtpController::class, 'index'])
            ->name('ftp.index');

        Route::resource('utilisateurs', UserController::class)
            ->names('users')
            ->parameters(['utilisateurs' => 'user'])
            ->except(['show']);
    });
});
