<?php

use App\Presentation\Controllers\AdminComplaintController;
use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\PaymentUnitController;
use App\Presentation\Controllers\PenaltyController;
use App\Presentation\Controllers\ServingCategoryController;
use App\Presentation\Controllers\ServingController;
use App\Presentation\Controllers\ServingRequestController;
use App\Presentation\Controllers\TroubleshootingController;
use App\Presentation\Controllers\UserComplaintController;
use App\Presentation\Controllers\UserManagementController;
use App\Presentation\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// TroubleshootingController
Route::get('/test-monitor', [TroubleshootingController::class, 'testMonitor']);
Route::get('/test-error', [TroubleshootingController::class, 'testError']);

// AuthController
Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/add-paid', [ServingController::class, 'addPaidServing']);
    Route::put('/servings/add-paid/{id}', [ServingController::class, 'updatePaid']);
    Route::post('/servings/{servingId}/comments', [ServingController::class, 'addComment']);
    Route::post('/comments/{commentId}/react', [ServingController::class, 'reactToComment']);
    Route::put('/servings/{servingId}/availability-slots', [ServingController::class, 'updateAvailabilitySlots']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/{servingId}/comments', [ServingController::class, 'getComments']);
    Route::get('/comments/{commentId}/replies', [ServingController::class, 'getReplies']);
});

// ServingController
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/{servingId}/availability-slots', [ServingController::class, 'getAvailabilitySlots']);
    Route::get('/servings/{id}', [ServingController::class, 'getById']);
    Route::post('/servings/search', [ServingController::class, 'getServings']);
    Route::post('/servings/nearby', [ServingController::class, 'getNearbyServings']);
});

// ServingRequestController
Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/requests', [ServingRequestController::class, 'create']);
    Route::put('/servings/requests/{id}/accept', [ServingRequestController::class, 'accept']);
    Route::put('/servings/requests/{id}/reject', [ServingRequestController::class, 'reject']);
    Route::delete('/servings/requests/{id}', [ServingRequestController::class, 'remove']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/requests/my', [ServingRequestController::class, 'listByRequester']);
    Route::get('/servings/requests/received', [ServingRequestController::class, 'listByOwner']);
    Route::get('/servings/requests/serving/{servingId}', [ServingRequestController::class, 'listByServing']);
});

// PaymentUnitController
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payment-units', [PaymentUnitController::class, 'getAll']);
});

// WalletController
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallets', [WalletController::class, 'getMyWallets']);
});

Route::middleware(['auth:sanctum', 'ensure.admin'])->group(function () {
    Route::post('/payment-units', [PaymentUnitController::class, 'create']);
});

// ServingCategoryController
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/categories/search', [ServingCategoryController::class, 'getCategories']);
});

Route::middleware(['auth:sanctum', 'ensure.admin'])->group(function () {
    Route::post('/categories', [ServingCategoryController::class, 'create']);
    Route::put('/categories/{id}', [ServingCategoryController::class, 'update']);
});

// UserManagementController
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{id}', [UserManagementController::class, 'getUserById']);
});

Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/profile', [UserManagementController::class, 'updateProfile']);
});

Route::middleware(['auth:sanctum', 'ensure.admin'])->group(function () {
    Route::post('/admin/users/search', [UserManagementController::class, 'searchUsers']);
    Route::put('/admin/users/{id}/block', [UserManagementController::class, 'blockUser']);
    Route::put('/admin/users/{id}/unblock', [UserManagementController::class, 'unblockUser']);
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
