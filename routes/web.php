<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\VmController;
use App\Http\Controllers\VsphereController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/ping/{host}', [DashboardController::class, 'ping'])->name('dashboard.ping');
    Route::resource('hosts', HostController::class);
    Route::resource('vms', VmController::class);

    Route::get('daily-reports', [DailyReportController::class, 'index'])->name('daily-reports.index');
    Route::post('daily-reports/generate', [DailyReportController::class, 'generate'])->name('daily-reports.generate');
    Route::get('daily-reports/import', [DailyReportController::class, 'create'])->name('daily-reports.import');
    Route::post('daily-reports/import', [DailyReportController::class, 'store'])->name('daily-reports.store');
    Route::get('daily-reports/export', [DailyReportController::class, 'export'])->name('daily-reports.export');
    Route::post('daily-reports/vsphere/preview', [DailyReportController::class, 'vspherePreview'])->name('daily-reports.vsphere-preview');
    Route::post('daily-reports/vsphere/save', [DailyReportController::class, 'vsphereSave'])->name('daily-reports.vsphere-save');

    Route::get('api/vsphere/vms', [VsphereController::class, 'vms'])->name('vsphere.vms');
    Route::get('api/vsphere/hosts', [VsphereController::class, 'hosts'])->name('vsphere.hosts');
    Route::get('api/vsphere/clusters', [VsphereController::class, 'clusters'])->name('vsphere.clusters');
    Route::get('api/vsphere/datastores', [VsphereController::class, 'datastores'])->name('vsphere.datastores');
});

require __DIR__.'/settings.php';
