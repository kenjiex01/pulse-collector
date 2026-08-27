<?php

use App\Http\Controllers\BiometricDeviceController;
use App\Http\Controllers\CollectedLogController;
use App\Http\Controllers\CollectorDashboardController;
use App\Http\Controllers\DeletedLogArchiveController;
use App\Http\Controllers\DtrDownloadController;
use App\Http\Controllers\DesktopInstallerUpdateController;
use App\Http\Controllers\DesktopUpdaterController;
use App\Http\Controllers\DeviceStatusController;
use App\Http\Controllers\DeviceUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CollectorDashboardController::class, 'index'])->name('dashboard');
Route::get('/desktop/update/download', [DesktopInstallerUpdateController::class, 'download'])
    ->name('desktop.update.download');
Route::get('/desktop/update/stream', [DesktopInstallerUpdateController::class, 'stream'])
    ->name('desktop.update.stream');
Route::get('/desktop/updater/status', [DesktopUpdaterController::class, 'status'])
    ->name('desktop.updater.status');
Route::post('/desktop/updater/check', [DesktopUpdaterController::class, 'check'])
    ->name('desktop.updater.check');
Route::post('/desktop/updater/install', [DesktopUpdaterController::class, 'install'])
    ->name('desktop.updater.install');
Route::post('/settings/collector-name', [CollectorDashboardController::class, 'updateCollectorName'])->name('settings.collector-name');
Route::post('/settings/attendance-start-date', [CollectorDashboardController::class, 'updateAttendanceStartDate'])
    ->name('settings.attendance-start-date');
Route::post('/settings/log-retention', [CollectorDashboardController::class, 'updateLogRetention'])
    ->name('settings.log-retention');
Route::post('/collect', [CollectorDashboardController::class, 'collectNow'])->name('collect.now');
Route::get('/collect/status', [CollectorDashboardController::class, 'collectStatus'])->name('collect.status');
Route::post('/collect/auto', [CollectorDashboardController::class, 'collectAuto'])->name('collect.auto');

Route::get('/devices/create', [BiometricDeviceController::class, 'create'])->name('devices.create');
Route::post('/devices', [BiometricDeviceController::class, 'store'])->name('devices.store');
Route::get('/devices/{device}/edit', [BiometricDeviceController::class, 'edit'])->name('devices.edit');
Route::put('/devices/{device}', [BiometricDeviceController::class, 'update'])->name('devices.update');
Route::delete('/devices/{device}', [BiometricDeviceController::class, 'destroy'])->name('devices.destroy');

Route::get('/logs', [CollectedLogController::class, 'index'])->name('logs.index');

Route::get('/dtr', [DtrDownloadController::class, 'index'])->name('dtr.index');
Route::post('/dtr/download', [DtrDownloadController::class, 'download'])->name('dtr.download');

Route::get('/archives', [DeletedLogArchiveController::class, 'index'])->name('archives.index');
Route::get('/archives/upload', [DeletedLogArchiveController::class, 'uploadForm'])->name('archives.upload');
Route::post('/archives/upload', [DeletedLogArchiveController::class, 'upload'])->name('archives.upload.store');
Route::get('/archives/{filename}/download', [DeletedLogArchiveController::class, 'download'])
    ->where('filename', '[^/]+')
    ->name('archives.download');

Route::get('/users', [DeviceUserController::class, 'index'])->name('users.index');
Route::get('/users/create', [DeviceUserController::class, 'create'])->name('users.create');
Route::post('/users', [DeviceUserController::class, 'store'])->name('users.store');
Route::get('/users/{deviceUser}/edit', [DeviceUserController::class, 'edit'])->name('users.edit');
Route::match(['put', 'post'], '/users/{deviceUser}', [DeviceUserController::class, 'update'])->name('users.update');
Route::post('/users/{deviceUser}/enroll-fingerprint', [DeviceUserController::class, 'enrollFingerprint'])->name('users.enroll-fingerprint');
Route::post('/users/refresh/{device}', [DeviceUserController::class, 'refreshFromDevice'])->name('users.refresh');

Route::get('/devices/status', [DeviceStatusController::class, 'index'])->name('devices.status');
