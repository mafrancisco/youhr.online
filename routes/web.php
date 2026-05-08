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
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Biometric\BiometricDeviceController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/',        [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',  [AuthController::class, 'login'])->name('login.submit');
});

// Authenticated
Route::middleware('auth')->group(function () {

    Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard',  [DashboardController::class, 'index'])->name('dashboard');

    // HR Only
    Route::middleware('hr')->group(function () {

        Route::get('/employees',                    [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees',                   [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{emp}',              [EmployeeController::class, 'update'])->name('employees.update');
        Route::put('/employees/{emp}/deactivate',   [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        Route::put('/employees/{emp}/reactivate',   [EmployeeController::class, 'reactivate'])->name('employees.reactivate');

        Route::get('/schedules',                    [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules',                   [ScheduleController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{sched}',            [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{sched}',         [ScheduleController::class, 'destroy'])->name('schedules.destroy');

        Route::get('/attendance/upload',            [AttendanceImportController::class, 'index'])->name('attendance.upload');
        Route::post('/attendance/import',           [AttendanceImportController::class, 'import'])->name('attendance.import');
        Route::post('/attendance/compute',          [AttendanceImportController::class, 'compute'])->name('attendance.compute');
        Route::get('/attendance/computation-status', [AttendanceImportController::class, 'computationStatus'])->name('attendance.computation-status');

        Route::get('/employees/{emp}/dtr',          [AttendanceLogController::class, 'show'])->name('attendance.log');
        Route::put('/attendance/{clean}/time',      [AttendanceLogController::class, 'updateTime'])->name('attendance.updateTime');

        Route::get('/reports/dtr',                  [DTRReportController::class, 'bulk'])->name('reports.dtr');
        Route::post('/reports/dtr/download',        [DTRReportController::class, 'bulkDownload'])->name('reports.dtr.download');

        Route::get('/holidays',                     [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays',                    [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}',           [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}',        [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::get('/admin/leaves',                 [LeaveController::class, 'adminIndex'])->name('leaves.admin');
        Route::put('/leaves/{leave}',               [LeaveController::class, 'update'])->name('leaves.update');

        Route::get('/admin/gate-passes',            [GatePassController::class, 'adminIndex'])->name('gate-passes.admin');
        Route::put('/gate-passes/{gp}/approve',     [GatePassController::class, 'approve'])->name('gate-passes.approve');
        Route::put('/gate-passes/{gp}/cancel',      [GatePassController::class, 'cancelAdmin'])->name('gate-passes.cancel');

        Route::get('/credits',                      [LeaveCreditController::class, 'index'])->name('credits.index');
        Route::post('/credits',                     [LeaveCreditController::class, 'store'])->name('credits.store');
        Route::put('/credits/{badgeID}',            [LeaveCreditController::class, 'update'])->name('credits.update');

        Route::get('/users',                        [UserController::class, 'index'])->name('users.index');
        Route::post('/users',                       [UserController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}',              [UserController::class, 'destroy'])->name('users.destroy');

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

        // Biometric Device Management
        Route::get('/biometric/devices',                        [BiometricDeviceController::class, 'index'])->name('biometric.devices');
        Route::post('/biometric/devices',                       [BiometricDeviceController::class, 'store'])->name('biometric.devices.store');
        Route::put('/biometric/devices/{device}',               [BiometricDeviceController::class, 'update'])->name('biometric.devices.update');
        Route::delete('/biometric/devices/{device}',            [BiometricDeviceController::class, 'destroy'])->name('biometric.devices.destroy');
        Route::post('/biometric/devices/test-connection',       [BiometricDeviceController::class, 'testConnection'])->name('biometric.devices.test');
        Route::get('/biometric/devices/{device}',               [BiometricDeviceController::class, 'show'])->name('biometric.devices.show');
        Route::post('/biometric/devices/{device}/sync-logs',    [BiometricDeviceController::class, 'syncLogs'])->name('biometric.devices.sync-logs');
        Route::post('/biometric/devices/{device}/sync-users',   [BiometricDeviceController::class, 'syncUsers'])->name('biometric.devices.sync-users');
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

        Route::get('/leaves',                       [LeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves',                      [LeaveController::class, 'store'])->name('leaves.store');
        Route::delete('/leaves/{leave}',            [LeaveController::class, 'destroy'])->name('leaves.destroy');
        Route::get('/leaves/{leave}/download',      [LeaveController::class, 'downloadForm'])->name('leaves.download');

        Route::get('/gate-passes',                  [GatePassController::class, 'index'])->name('gate-passes.index');
        Route::post('/gate-passes',                 [GatePassController::class, 'store'])->name('gate-passes.store');
        Route::put('/gate-passes/{gp}',             [GatePassController::class, 'update'])->name('gate-passes.update');
        Route::delete('/gate-passes/{gp}',          [GatePassController::class, 'destroy'])->name('gate-passes.destroy');
        Route::get('/gate-passes/{gp}/download',    [GatePassController::class, 'download'])->name('gate-passes.download');
    });

    // Any authenticated user
    Route::get('/dtr/download',                     [DTRReportController::class, 'individual'])->name('dtr.download');
});
