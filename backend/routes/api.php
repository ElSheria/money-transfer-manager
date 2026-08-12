<?php

use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\TransferNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
});

Route::middleware('api.token')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('dashboard', DashboardController::class);
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:dg');
    Route::get('system-settings', [SystemSettingController::class, 'index'])->middleware('role:dg');
    Route::patch('system-settings/{systemSetting}', [SystemSettingController::class, 'update'])->middleware('role:dg');
    Route::get('transfer-notifications', [TransferNotificationController::class, 'index'])->middleware('role:dg,supervisor');
    Route::post('transfer-notifications/send-pending', [TransferNotificationController::class, 'sendPending'])->middleware('role:dg,supervisor');

    Route::get('agencies', [AgencyController::class, 'index']);
    Route::get('agencies/{agency}', [AgencyController::class, 'show']);
    Route::post('agencies', [AgencyController::class, 'store'])->middleware('role:dg');
    Route::patch('agencies/{agency}', [AgencyController::class, 'update'])->middleware('role:dg');
    Route::put('agencies/{agency}', [AgencyController::class, 'update'])->middleware('role:dg');
    Route::delete('agencies/{agency}', [AgencyController::class, 'destroy'])->middleware('role:dg');

    Route::apiResource('staff', StaffController::class)
        ->except(['show'])
        ->middleware('role:dg,supervisor');

    Route::get('transfers', [TransferController::class, 'index']);
    Route::get('transfers/export/csv', [TransferController::class, 'exportCsv']);
    Route::get('transfers/export/excel', [TransferController::class, 'exportExcel']);
    Route::get('transfers/export/pdf', [TransferController::class, 'exportPdf']);
    Route::get('transfers/code/{code}', [TransferController::class, 'findByCode']);
    Route::post('transfers', [TransferController::class, 'store'])->middleware('role:manager');
    Route::get('transfers/{transfer}', [TransferController::class, 'show']);
    Route::get('transfers/{transfer}/receipt', [TransferController::class, 'receipt']);
    Route::patch('transfers/{transfer}/cancel', [TransferController::class, 'cancel']);
    Route::patch('transfers/{transfer}/withdraw', [TransferController::class, 'withdraw'])->middleware('role:manager');
});
