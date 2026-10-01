<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\MobileContextController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\MobileSubscriptionCheckoutController;
use App\Http\Controllers\Api\MobileSubscriptionPaymentsController;
use App\Http\Controllers\Api\MobileSubscriptionPlansController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');

Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::delete('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/mobile/context', [MobileContextController::class, 'context']);
    Route::post('/mobile/devices', [MobileDeviceController::class, 'store']);
    Route::get('/mobile/subscription/plans', [MobileSubscriptionPlansController::class, 'index']);
    Route::post('/mobile/subscription/checkout', [MobileSubscriptionCheckoutController::class, 'store'])
        ->middleware('throttle:subscription-checkout');
    Route::get('/mobile/subscription/payments', [MobileSubscriptionPaymentsController::class, 'index']);
    Route::get('/mobile/subscription/payments/{payment}', [MobileSubscriptionPaymentsController::class, 'show']);

    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);

    // INT-03 — read-only status of a previously pushed request.
    Route::get('/sync/requests/{request_id}/status', [SyncController::class, 'requestStatus'])
        ->name('api.sync.requests.status');
});
