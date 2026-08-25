<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlarmController;
use App\Http\Controllers\ApplianceController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatastoreController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\ModSecurityController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\SmartDetectionController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VmController;
use App\Http\Controllers\VsphereController;

// Where the server room's (not-yet-installed) temperature/humidity sensor
// pushes readings — a device has no user session, so this sits outside the
// auth group entirely and is instead gated by a shared-secret token inside
// EnvironmentController::ingest() itself.
Route::post('api/environment/readings', [EnvironmentController::class, 'ingest'])
    ->middleware('throttle:60,1')
    ->name('environment.ingest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('page:dashboard')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/ping/{host}', [DashboardController::class, 'ping'])->name('dashboard.ping');
        Route::get('api/environment/latest', [EnvironmentController::class, 'latest'])->name('environment.latest');
    });

    Route::middleware('page:hosts')->group(function () {
        Route::resource('hosts', HostController::class);
    });

    Route::middleware('page:vms')->group(function () {
        Route::post('vms/sync', [VmController::class, 'sync'])->name('vms.sync');
        Route::get('api/vms/certificate-candidates', [VmController::class, 'certificateExpCandidates'])->name('vms.certificate-candidates');
        Route::post('vms/certificate-exp', [VmController::class, 'bulkCertificateExp'])->name('vms.certificate-exp');
        Route::resource('vms', VmController::class)->except(['create', 'store']);
    });

    Route::middleware('page:appliance')->group(function () {
        Route::get('appliance', [ApplianceController::class, 'index'])->name('appliance.index');
    });

    Route::middleware('page:daily-reports')->group(function () {
        Route::get('daily-reports', [DailyReportController::class, 'index'])->name('daily-reports.index');
        Route::post('daily-reports/pull', [DailyReportController::class, 'pull'])->name('daily-reports.pull');
        Route::post('daily-reports', [DailyReportController::class, 'store'])->name('daily-reports.store');
        Route::get('daily-reports/export', [DailyReportController::class, 'export'])->name('daily-reports.export');
    });

    Route::middleware('page:alarms')->group(function () {
        Route::get('alarms', [AlarmController::class, 'index'])->name('alarms.index');
        Route::get('api/vsphere/alarms', [VsphereController::class, 'alarms'])->name('vsphere.alarms');
        Route::get('api/vsphere/alarms/count', [VsphereController::class, 'alarmsCount'])->name('vsphere.alarms.count');
    });

    Route::middleware('page:datastores')->group(function () {
        Route::get('datastores', [DatastoreController::class, 'index'])->name('datastores.index');
        Route::get('api/vsphere/datastores/trends', [VsphereController::class, 'datastoreTrends'])->name('vsphere.datastores.trends');
    });

    Route::middleware('page:performance')->group(function () {
        Route::get('performance', [PerformanceController::class, 'index'])->name('performance.index');
        Route::get('api/vsphere/performance/entities', [VsphereController::class, 'performanceEntities'])->name('vsphere.performance.entities');
        Route::get('api/vsphere/performance/metrics', [VsphereController::class, 'performanceMetrics'])->name('vsphere.performance.metrics');
    });

    Route::middleware('page:smart-detection')->group(function () {
        Route::get('smart-detection', [SmartDetectionController::class, 'index'])->name('smart-detection.index');
        Route::get('api/smart-detection/findings', [SmartDetectionController::class, 'findings'])->name('smart-detection.findings');
        Route::get('api/smart-detection/open-count', [SmartDetectionController::class, 'openCount'])->name('smart-detection.open-count');
        Route::post('smart-detection/findings/{finding}/acknowledge', [SmartDetectionController::class, 'acknowledge'])->name('smart-detection.acknowledge');
        Route::post('smart-detection/findings/{finding}/resolve', [SmartDetectionController::class, 'resolve'])->name('smart-detection.resolve');
    });

    Route::middleware('page:modsecurity')->group(function () {
        Route::get('modsecurity', [ModSecurityController::class, 'index'])->name('modsecurity.index');
        Route::get('api/modsecurity/logs', [ModSecurityController::class, 'logs'])->name('modsecurity.logs');
    });

    // User management and the Activity Log (every user's actions + IPs) are
    // themselves privileged/audit surfaces, so both are admin-only rather
    // than grantable pages.
    Route::middleware('admin')->group(function () {
        Route::get('users/online-status', [UserController::class, 'onlineStatus'])->name('users.online-status');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::post('system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    });

    Route::middleware('page:hosts,dashboard')->group(function () {
        Route::get('api/vsphere/vms', [VsphereController::class, 'vms'])->name('vsphere.vms');
        Route::get('api/vsphere/hosts', [VsphereController::class, 'hosts'])->name('vsphere.hosts');
        Route::get('api/vsphere/hosts/{host}/network', [VsphereController::class, 'hostNetwork'])->name('vsphere.hosts.network');
    });

    Route::middleware('page:dashboard')->group(function () {
        Route::get('api/vsphere/clusters', [VsphereController::class, 'clusters'])->name('vsphere.clusters');
        Route::get('api/vsphere/datastores', [VsphereController::class, 'datastores'])->name('vsphere.datastores');
    });

    Route::middleware('page:appliance')->group(function () {
        Route::get('api/vsphere/appliance', [VsphereController::class, 'appliance'])->name('vsphere.appliance');
    });
});

require __DIR__.'/settings.php';
