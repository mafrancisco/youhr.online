<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Credits\LeaveCreditController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\DTR\AdjustmentRequestController;
use App\Http\Controllers\DTR\AttendanceLogController;
use App\Http\Controllers\DTR\DTRController;
use App\Http\Controllers\DTR\DTRReportController;
use App\Http\Controllers\DTR\SubmissionController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\GatePass\GatePassController;
use App\Http\Controllers\Holidays\HolidayController;
use App\Http\Controllers\Leaves\LeaveController;
use App\Http\Controllers\Schedules\ScheduleController;
use App\Http\Controllers\Attendance\AttendanceImportController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\EmployeeStatusController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TimeDetectionController;
use App\Http\Controllers\Biometric\BiometricDeviceController;
use App\Http\Controllers\SaaS\CompanyOnboardingController;
use App\Http\Controllers\SaaS\LicenseActivationController;
use App\Http\Controllers\Landlord\AuthController as LandlordAuthController;
use App\Http\Controllers\Landlord\CompanyController as LandlordCompanyController;
use App\Http\Controllers\Landlord\CompanyModuleController as LandlordCompanyModuleController;
use App\Http\Controllers\Landlord\DatabaseExportController as LandlordDatabaseExportController;
use App\Http\Controllers\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Landlord\LicenseController as LandlordLicenseController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/', [CompanyOnboardingController::class, 'showLogin'])->name('login');
    Route::post('/login', [CompanyOnboardingController::class, 'loginWithCredentials'])->name('login.credentials');
    Route::post('/login/google', [CompanyOnboardingController::class, 'startCompanyLogin'])->name('login.submit');

    Route::get('/register-company', [CompanyOnboardingController::class, 'showCompanyRegistration'])->name('register.company');
    Route::post('/register-company', [CompanyOnboardingController::class, 'startCompanyRegistration'])->name('register.company.submit');

    Route::get('/auth/google/redirect', [CompanyOnboardingController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [CompanyOnboardingController::class, 'handleGoogleCallback'])->name('auth.google.callback');

    Route::get('/landlord/login', [LandlordAuthController::class, 'showLogin'])->name('landlord.login');
    Route::get('/landlord/auth/google/redirect', [LandlordAuthController::class, 'redirectToGoogle'])->name('landlord.auth.redirect');
    Route::get('/landlord/auth/google/callback', [LandlordAuthController::class, 'handleGoogleCallback'])->name('landlord.auth.callback');
});

// Authenticated (with tenant context)
Route::middleware(['auth', 'tenant'])->group(function () {

    Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');

    Route::get('/license/activate', [LicenseActivationController::class, 'show'])->name('license.show');
    Route::post('/license/activate', [LicenseActivationController::class, 'activate'])->name('license.activate');

    Route::middleware('licensed')->group(function () {

    Route::get('/dashboard',  [DashboardController::class, 'index'])->name('dashboard');

    // HR Only
    Route::middleware('hr')->group(function () {

        Route::get('/employees',                    [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees',                   [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{emp}',              [EmployeeController::class, 'update'])->name('employees.update');
        Route::put('/employees/{emp}/deactivate',   [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        Route::put('/employees/{emp}/reactivate',   [EmployeeController::class, 'reactivate'])->name('employees.reactivate');

        Route::get('/schedules',                    [ScheduleController::class, 'index'])->name('schedules.index')->middleware('module:schedules');
        Route::post('/schedules',                   [ScheduleController::class, 'store'])->name('schedules.store')->middleware('module:schedules');
        Route::put('/schedules/{sched}',            [ScheduleController::class, 'update'])->name('schedules.update')->middleware('module:schedules');
        Route::delete('/schedules/{sched}',         [ScheduleController::class, 'destroy'])->name('schedules.destroy')->middleware('module:schedules');

        Route::get('/attendance/upload',            [AttendanceImportController::class, 'index'])->name('attendance.upload');
        Route::post('/attendance/import',           [AttendanceImportController::class, 'import'])->name('attendance.import');
        Route::post('/attendance/compute',          [AttendanceImportController::class, 'compute'])->name('attendance.compute');

        Route::get('/employees/{emp}/dtr',          [AttendanceLogController::class, 'show'])->name('attendance.log');
        Route::put('/attendance/{clean}/time',      [AttendanceLogController::class, 'updateTime'])->name('attendance.updateTime');

        Route::get('/reports/dtr',                  [DTRReportController::class, 'bulk'])->name('reports.dtr');
        Route::get('/reports/dtr/download',         [DTRReportController::class, 'bulkDownload'])->name('reports.dtr.download');

        Route::get('/holidays',                     [HolidayController::class, 'index'])->name('holidays.index')->middleware('module:holidays');
        Route::post('/holidays',                    [HolidayController::class, 'store'])->name('holidays.store')->middleware('module:holidays');
        Route::put('/holidays/{holiday}',           [HolidayController::class, 'update'])->name('holidays.update')->middleware('module:holidays');
        Route::delete('/holidays/{holiday}',        [HolidayController::class, 'destroy'])->name('holidays.destroy')->middleware('module:holidays');

        Route::get('/admin/leaves',                 [LeaveController::class, 'adminIndex'])->name('leaves.admin')->middleware('module:leaves');
        Route::put('/leaves/{leave}',               [LeaveController::class, 'update'])->name('leaves.update')->middleware('module:leaves');

        Route::get('/admin/gate-passes',            [GatePassController::class, 'adminIndex'])->name('gate-passes.admin')->middleware('module:gate-passes');
        Route::put('/gate-passes/{gp}/approve',     [GatePassController::class, 'approve'])->name('gate-passes.approve')->middleware('module:gate-passes');
        Route::put('/gate-passes/{gp}/cancel',      [GatePassController::class, 'cancelAdmin'])->name('gate-passes.cancel')->middleware('module:gate-passes');

        Route::get('/credits',                      [LeaveCreditController::class, 'index'])->name('credits.index')->middleware('module:credits');
        Route::post('/credits',                     [LeaveCreditController::class, 'store'])->name('credits.store')->middleware('module:credits');
        Route::put('/credits/{badgeID}',            [LeaveCreditController::class, 'update'])->name('credits.update')->middleware('module:credits');

        Route::get('/users',                        [UserController::class, 'index'])->name('users.index')->middleware('module:users');
        Route::post('/users',                       [UserController::class, 'store'])->name('users.store')->middleware('module:users');
        Route::put('/users/{user}',                 [UserController::class, 'update'])->name('users.update')->middleware('module:users');
        Route::delete('/users/{user}',              [UserController::class, 'destroy'])->name('users.destroy')->middleware('module:users');

        Route::get('/admin/divisions',                      [DivisionController::class, 'index'])->name('divisions.index');
        Route::post('/admin/divisions',                     [DivisionController::class, 'storeDivision'])->name('divisions.store');
        Route::post('/admin/divisions/units',               [DivisionController::class, 'storeUnit'])->name('units.store');
        Route::put('/admin/divisions/units/{unit}',         [DivisionController::class, 'updateUnit'])->name('units.update');
        Route::delete('/admin/divisions/units/{unit}',      [DivisionController::class, 'destroyUnit'])->name('units.destroy');
        Route::put('/admin/divisions/{division}',           [DivisionController::class, 'updateDivision'])->name('divisions.update');
        Route::delete('/admin/divisions/{division}',        [DivisionController::class, 'destroyDivision'])->name('divisions.destroy');

        Route::get('/admin/settings',               [SettingController::class, 'index'])->name('admin.settings');
        Route::post('/admin/settings',              [SettingController::class, 'update'])->name('admin.settings.update');
        Route::delete('/admin/settings/logo',       [SettingController::class, 'deleteLogo'])->name('admin.settings.logo.delete');

        Route::get('/admin/employee-statuses',              [EmployeeStatusController::class, 'index'])->name('employee-statuses.index');
        Route::post('/admin/employee-statuses',             [EmployeeStatusController::class, 'store'])->name('employee-statuses.store');
        Route::put('/admin/employee-statuses/{status}',     [EmployeeStatusController::class, 'update'])->name('employee-statuses.update');
        Route::delete('/admin/employee-statuses/{status}',  [EmployeeStatusController::class, 'destroy'])->name('employee-statuses.destroy');

        Route::get('/admin/leave-types',                    [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('/admin/leave-types',                   [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::put('/admin/leave-types/{leaveType}',        [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::delete('/admin/leave-types/{leaveType}',     [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');

        Route::get('/admin/time-detection',                 [TimeDetectionController::class, 'index'])->name('time-detection.index');
        Route::put('/admin/time-detection/{setting}',       [TimeDetectionController::class, 'update'])->name('time-detection.update');

        // Biometric Device Management
        Route::get('/biometric/devices',                        [BiometricDeviceController::class, 'index'])->name('biometric.devices');
        Route::post('/biometric/devices',                       [BiometricDeviceController::class, 'store'])->name('biometric.devices.store');
        Route::put('/biometric/devices/{device}',               [BiometricDeviceController::class, 'update'])->name('biometric.devices.update');
        Route::delete('/biometric/devices/{device}',            [BiometricDeviceController::class, 'destroy'])->name('biometric.devices.destroy');
        Route::post('/biometric/devices/test-connection',       [BiometricDeviceController::class, 'testConnection'])->name('biometric.devices.test');
        Route::get('/biometric/devices/{device}',               [BiometricDeviceController::class, 'show'])->name('biometric.devices.show');
        Route::post('/biometric/devices/{device}/sync-logs',    [BiometricDeviceController::class, 'syncLogs'])->name('biometric.devices.sync-logs');
        Route::post('/biometric/devices/{device}/sync-users',   [BiometricDeviceController::class, 'syncUsers'])->name('biometric.devices.sync-users');
        Route::post('/biometric/process-logs',                  [BiometricDeviceController::class, 'processLogs'])->name('biometric.process-logs');
        Route::post('/biometric/users/{deviceUser}/map',        [BiometricDeviceController::class, 'mapUser'])->name('biometric.users.map');
        Route::delete('/biometric/users/{deviceUser}/map',      [BiometricDeviceController::class, 'unmapUser'])->name('biometric.users.unmap');
        Route::get('/biometric/devices/{device}/history',       [BiometricDeviceController::class, 'syncHistory'])->name('biometric.devices.history');
    });

    // Employee Only
    Route::middleware('employee')->group(function () {

        Route::get('/dtr',                          [DTRController::class, 'index'])->name('dtr.index');
        Route::post('/dtr/submit',                  [SubmissionController::class, 'store'])->name('dtr.submit');
        Route::post('/dtr/requests',                [AdjustmentRequestController::class, 'store'])->name('dtr.requests.store');
        Route::delete('/dtr/requests/{req}',        [AdjustmentRequestController::class, 'destroy'])->name('dtr.requests.destroy');

        Route::get('/leaves',                       [LeaveController::class, 'index'])->name('leaves.index')->middleware('module:leaves');
        Route::post('/leaves',                      [LeaveController::class, 'store'])->name('leaves.store')->middleware('module:leaves');
        Route::delete('/leaves/{leave}',            [LeaveController::class, 'destroy'])->name('leaves.destroy')->middleware('module:leaves');
        Route::get('/leaves/{leave}/download',      [LeaveController::class, 'downloadForm'])->name('leaves.download')->middleware('module:leaves');

        Route::get('/gate-passes',                  [GatePassController::class, 'index'])->name('gate-passes.index')->middleware('module:gate-passes');
        Route::post('/gate-passes',                 [GatePassController::class, 'store'])->name('gate-passes.store')->middleware('module:gate-passes');
        Route::put('/gate-passes/{gp}',             [GatePassController::class, 'update'])->name('gate-passes.update')->middleware('module:gate-passes');
        Route::delete('/gate-passes/{gp}',          [GatePassController::class, 'destroy'])->name('gate-passes.destroy')->middleware('module:gate-passes');
        Route::get('/gate-passes/{gp}/download',    [GatePassController::class, 'download'])->name('gate-passes.download')->middleware('module:gate-passes');
    });

    // Any authenticated user
    Route::get('/dtr/download',                     [DTRReportController::class, 'individual'])->name('dtr.download');
    });
});

Route::prefix('landlord')->middleware('landlord')->group(function () {
    Route::get('/', [LandlordDashboardController::class, 'index'])->name('landlord.dashboard');
    Route::post('/companies/{company}/status', [LandlordCompanyController::class, 'update'])->name('landlord.companies.status');
    Route::get('/companies/{company}/modules', [LandlordCompanyModuleController::class, 'edit'])->name('landlord.companies.modules');
    Route::put('/companies/{company}/modules', [LandlordCompanyModuleController::class, 'update'])->name('landlord.companies.modules.update');
    Route::get('/companies/{company}/database/download', [LandlordDatabaseExportController::class, 'download'])->name('landlord.companies.database.download');
    Route::post('/companies/{company}/licenses', [LandlordLicenseController::class, 'generate'])->name('landlord.companies.licenses.generate');
    Route::post('/licenses/{license}/activate', [LandlordLicenseController::class, 'activate'])->name('landlord.licenses.activate');
    Route::post('/licenses/{license}/suspend', [LandlordLicenseController::class, 'suspend'])->name('landlord.licenses.suspend');
    Route::post('/logout', [LandlordAuthController::class, 'logout'])->name('landlord.logout');
});

// ADMS (ZKTeco push mode). These endpoints cannot carry authentication — the device
// only presents its serial number — so they are registered only where a device is
// actually in use. See config/saas.php for the reasoning.
if (config('saas.adms_enabled')) {
    Route::prefix('iclock')->withoutMiddleware([\App\Http\Middleware\ResolveTenantContext::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
        Route::get('/cdata', [\App\Http\Controllers\Biometric\AdmsController::class, 'handshake']);
        Route::post('/cdata', [\App\Http\Controllers\Biometric\AdmsController::class, 'receiveData']);
        Route::get('/getrequest', [\App\Http\Controllers\Biometric\AdmsController::class, 'getRequest']);
        Route::post('/devicecmd', [\App\Http\Controllers\Biometric\AdmsController::class, 'deviceCmd']);
    });
}
