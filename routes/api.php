<?php

use App\Presentation\Controllers\TroubleshootingController;
use Illuminate\Support\Facades\Route;
use App\Presentation\Controllers\AuthController;

Route::get('/test-monitor', [TroubleshootingController::class, 'testMonitor']);
Route::get('/test-error', [TroubleshootingController::class, 'testError']);

Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {});

// Serving endpoints
Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/add-paid', [\App\Presentation\Controllers\ServingController::class, 'addPaidServing']);
    Route::put('/servings/add-paid/{id}', [\App\Presentation\Controllers\ServingController::class, 'updatePaid']);
});
