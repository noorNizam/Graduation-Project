<?php

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\TroubleshootingController;
use Illuminate\Support\Facades\Route;

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

    Route::post('/servings/requests', [\App\Presentation\Controllers\ServingRequestController::class, 'create']);
    Route::put('/servings/requests/{id}/accept', [\App\Presentation\Controllers\ServingRequestController::class, 'accept']);
    Route::put('/servings/requests/{id}/reject', [\App\Presentation\Controllers\ServingRequestController::class, 'reject']);
    Route::get('/servings/requests/serving/{servingId}', [\App\Presentation\Controllers\ServingRequestController::class, 'listByServing']);
    Route::get('/servings/requests/my', [\App\Presentation\Controllers\ServingRequestController::class, 'listByRequester']);

    Route::post('/servings/{servingId}/comments', [\App\Presentation\Controllers\ServingController::class, 'addComment']);
    Route::get('/servings/{servingId}/comments', [\App\Presentation\Controllers\ServingController::class, 'getComments']);
    Route::get('/comments/{commentId}/replies', [\App\Presentation\Controllers\ServingController::class, 'getReplies']);
    Route::post('/comments/{commentId}/react', [\App\Presentation\Controllers\ServingController::class, 'reactToComment']);
});

//test