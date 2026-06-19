<?php

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\TroubleshootingController;
use App\Presentation\Controllers\UserComplaintController;
use App\Presentation\Controllers\AdminComplaintController;
use App\Presentation\Controllers\PenaltyController;
use Illuminate\Support\Facades\Route;

Route::get('/test-monitor', [TroubleshootingController::class, 'testMonitor']);
Route::get('/test-error', [TroubleshootingController::class, 'testError']);

Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {});

// Serving endpoints (شغل رفقاتك)
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

// ==================== Routes للمستخدم العادي (شغلك) ====================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/complaints', [UserComplaintController::class, 'store']);
    Route::get('/my-complaints', [UserComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [UserComplaintController::class, 'show']);
    Route::get('/my-wallet', [UserComplaintController::class, 'getWalletBalance']);
    Route::get('/my-penalties', [UserComplaintController::class, 'myPenalties']);
});

// ==================== Routes للمدير فقط (شغلك + شغل رفقاتك) ====================
Route::middleware(['auth:sanctum', 'ensure.admin'])->prefix('admin')->group(function () {
    // الشكاوي (شغلك)
    Route::get('/complaints', [AdminComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [AdminComplaintController::class, 'show']);
    Route::put('/complaints/{id}/status', [AdminComplaintController::class, 'updateStatus']);
    Route::delete('/complaints/{id}', [AdminComplaintController::class, 'destroy']);
    Route::get('/complaints/statistics', [AdminComplaintController::class, 'statistics']);
    
    // العقوبات (شغلك)
    Route::get('/penalties', [PenaltyController::class, 'index']);
    Route::get('/penalties/{id}', [PenaltyController::class, 'show']);
    Route::get('/users/{userId}/penalties', [PenaltyController::class, 'getUserPenalties']);
    Route::delete('/penalties/{id}', [PenaltyController::class, 'destroy']);
});