<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CloudBackupController;
use App\Http\Controllers\Api\GoogleAccountLinkController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ManualRegistrationController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\MobileContextController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\MobileSubscriptionCheckoutController;
use App\Http\Controllers\Api\MobileSubscriptionPaymentsController;
use App\Http\Controllers\Api\MobileSubscriptionPlansController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');

Route::post('/v2/auth/google', [GoogleAuthController::class, 'authenticate'])
    ->middleware('throttle:10,1');

Route::post('/v2/auth/register', [ManualRegistrationController::class, 'register'])
    ->middleware('throttle:6,1');
Route::post('/v2/auth/otp/verify', [ManualRegistrationController::class, 'verify'])
    ->middleware('throttle:30,1');
Route::post('/v2/auth/otp/resend', [ManualRegistrationController::class, 'resend'])
    ->middleware('throttle:10,1');

Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v2/auth/reauthenticate', [GoogleAccountLinkController::class, 'reauthenticate'])
        ->middleware('throttle:5,1');
    Route::post('/v2/auth/google/link', [GoogleAccountLinkController::class, 'link'])
        ->middleware('throttle:10,1');

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::delete('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/mobile/context', [MobileContextController::class, 'context']);
    Route::post('/mobile/devices', [MobileDeviceController::class, 'store']);
    Route::get('/mobile/subscription/plans', [MobileSubscriptionPlansController::class, 'index']);
    Route::post('/mobile/subscription/checkout', [MobileSubscriptionCheckoutController::class, 'store'])
        ->middleware('throttle:subscription-checkout');
    Route::get('/mobile/subscription/payments', [MobileSubscriptionPaymentsController::class, 'index']);
    Route::get('/mobile/subscription/payments/{payment}', [MobileSubscriptionPaymentsController::class, 'show']);

    // PREM-D03 — private cloud backup snapshots (upload/list/detail/download).
    Route::post('/mobile/backups', [CloudBackupController::class, 'store']);
    Route::get('/mobile/backups', [CloudBackupController::class, 'index']);
    Route::get('/mobile/backups/{backup}', [CloudBackupController::class, 'show']);
    Route::get('/mobile/backups/{backup}/download', [CloudBackupController::class, 'download']);

    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);

    // INT-03 — read-only status of a previously pushed request.
    Route::get('/sync/requests/{request_id}/status', [SyncController::class, 'requestStatus'])
        ->name('api.sync.requests.status');
});
