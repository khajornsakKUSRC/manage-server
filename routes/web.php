<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlarmController;
use App\Http\Controllers\ApplianceController;
use App\Http\Controllers\CalendarNoticeController;
use App\Http\Controllers\CertificateExpirationController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatastoreController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\ItRepairController;
use App\Http\Controllers\ModSecurityController;
use App\Http\Controllers\NetworkMapController;
use App\Http\Controllers\NetworkMonitorController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceEvaluationController;
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

// Public, login-free IT repair request form — anyone on the network can
// file a request without an account. Staff triage them and set the status
// from the authed /it-repair page.
Route::get('it-repair/new', [ItRepairController::class, 'create'])->name('it-repair.create');
Route::post('it-repair/new', [ItRepairController::class, 'publicStore'])
    ->middleware('throttle:20,1')
    ->name('it-repair.public-store');
// Public status lookup by the recipient's own email address.
Route::get('it-repair/track', [ItRepairController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('it-repair.track');
// The recipient rates a resolved request from the public tracker.
Route::post('it-repair/track/{itRepairRequest}/evaluation', [ItRepairController::class, 'publicEvaluate'])
    ->middleware('throttle:20,1')
    ->name('it-repair.track.evaluate');

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

    Route::middleware('page:certificate-expiration')->group(function () {
        Route::get('certificate-expiration', [CertificateExpirationController::class, 'index'])->name('certificate-expiration.index');
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

    Route::middleware('page:calendar-notice')->group(function () {
        Route::get('calendar-notice', [CalendarNoticeController::class, 'index'])->name('calendar-notice.index');
        Route::post('calendar-notice', [CalendarNoticeController::class, 'store'])->name('calendar-notice.store');
        Route::put('calendar-notice/{calendarNotice}', [CalendarNoticeController::class, 'update'])->name('calendar-notice.update');
        Route::delete('calendar-notice/{calendarNotice}', [CalendarNoticeController::class, 'destroy'])->name('calendar-notice.destroy');
    });

    Route::middleware('page:it-repair')->group(function () {
        Route::get('it-repair', [ItRepairController::class, 'index'])->name('it-repair.index');
        Route::post('it-repair', [ItRepairController::class, 'store'])->name('it-repair.store');
        Route::put('it-repair/{itRepairRequest}', [ItRepairController::class, 'update'])->name('it-repair.update');
        Route::patch('it-repair/{itRepairRequest}/status', [ItRepairController::class, 'updateStatus'])->name('it-repair.status');
        Route::delete('it-repair/{itRepairRequest}', [ItRepairController::class, 'destroy'])->name('it-repair.destroy');
        Route::post('it-repair/{itRepairRequest}/evaluation', [ItRepairController::class, 'storeEvaluation'])->name('it-repair.evaluation.store');
        Route::post('it-repair/{itRepairRequest}/send-email', [ItRepairController::class, 'sendEmail'])->name('it-repair.send-email');
        Route::post('it-repair/service-types', [ItRepairController::class, 'storeType'])->name('it-repair.service-types.store');
        Route::put('it-repair/service-types/{itRepairServiceType}', [ItRepairController::class, 'updateType'])->name('it-repair.service-types.update');
        Route::delete('it-repair/service-types/{itRepairServiceType}', [ItRepairController::class, 'destroyType'])->name('it-repair.service-types.destroy');
    });

    Route::middleware('page:it-repair-evaluation')->group(function () {
        Route::get('it-repair-evaluation', [ServiceEvaluationController::class, 'index'])->name('it-repair-evaluation.index');
        Route::get('it-repair-evaluation/export', [ServiceEvaluationController::class, 'export'])->name('it-repair-evaluation.export');
        Route::post('it-repair-evaluation/criteria', [ServiceEvaluationController::class, 'storeCriterion'])->name('it-repair-evaluation.criteria.store');
        Route::put('it-repair-evaluation/criteria/{itRepairEvalCriterion}', [ServiceEvaluationController::class, 'updateCriterion'])->name('it-repair-evaluation.criteria.update');
        Route::delete('it-repair-evaluation/criteria/{itRepairEvalCriterion}', [ServiceEvaluationController::class, 'destroyCriterion'])->name('it-repair-evaluation.criteria.destroy');
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

    Route::middleware('page:network-infrastructure')->group(function () {
        Route::get('api/network-monitors/status', [NetworkMonitorController::class, 'status'])->name('network-monitors.status');
        Route::resource('network-monitors', NetworkMonitorController::class)->except(['show']);
    });

    Route::middleware('page:network-map')->group(function () {
        Route::get('network-map', [NetworkMapController::class, 'index'])->name('network-map.index');
        Route::get('api/network-map/nodes', [NetworkMapController::class, 'nodes'])->name('network-map.nodes');
        Route::get('api/network-map/nodes/{networkMapNode}/ping', [NetworkMapController::class, 'ping'])->name('network-map.ping');
        Route::post('network-map', [NetworkMapController::class, 'store'])->name('network-map.store');
        Route::put('network-map/{networkMapNode}', [NetworkMapController::class, 'update'])->name('network-map.update');
        Route::delete('network-map/{networkMapNode}', [NetworkMapController::class, 'destroy'])->name('network-map.destroy');
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

    Route::middleware('page:services')->group(function () {
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('api/services/statuses', [ServiceController::class, 'statuses'])->name('services.statuses');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    // User management and the Activity Log (every user's actions + IPs) are
    // themselves privileged/audit surfaces, so both are admin-only rather
    // than grantable pages.
    Route::middleware('admin')->group(function () {
        Route::get('users/online-status', [UserController::class, 'onlineStatus'])->name('users.online-status');
        // Registered before the users resource so "users/roles" isn't
        // swallowed by the users/{user} route-model binding.
        Route::get('users/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('users/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('users/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('users/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::post('system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    });

    Route::middleware('page:hosts,dashboard')->group(function () {
        Route::get('api/vsphere/vms', [VsphereController::class, 'vms'])->name('vsphere.vms');
        Route::get('api/vsphere/hosts', [VsphereController::class, 'hosts'])->name('vsphere.hosts');
        Route::get('api/vsphere/hosts/{host}/network', [VsphereController::class, 'hostNetwork'])->name('vsphere.hosts.network');
        Route::get('api/vsphere/hosts/{host}/hardware', [VsphereController::class, 'hostHardware'])->name('vsphere.hosts.hardware');
    });

    Route::middleware('page:dashboard')->group(function () {
        Route::get('api/vsphere/clusters', [VsphereController::class, 'clusters'])->name('vsphere.clusters');
        Route::get('api/vsphere/datastores', [VsphereController::class, 'datastores'])->name('vsphere.datastores');
        Route::get('api/vsphere/top-cpu-vms', [VsphereController::class, 'topCpuVms'])->name('vsphere.top-cpu-vms');
    });

    Route::middleware('page:appliance')->group(function () {
        Route::get('api/vsphere/appliance', [VsphereController::class, 'appliance'])->name('vsphere.appliance');
    });
});

require __DIR__.'/settings.php';
