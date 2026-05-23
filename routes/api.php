<?php

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\PaymentUnitController;
use App\Presentation\Controllers\ServingCategoryController;
use App\Presentation\Controllers\ServingController;
use App\Presentation\Controllers\ServingRequestController;
use App\Presentation\Controllers\TroubleshootingController;
use App\Presentation\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// TroubleshootingController
Route::get('/test-monitor', [TroubleshootingController::class, 'testMonitor']);
Route::get('/test-error', [TroubleshootingController::class, 'testError']);

// AuthController
Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ServingController
Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/add-paid', [ServingController::class, 'addPaidServing']);
    Route::put('/servings/add-paid/{id}', [ServingController::class, 'updatePaid']);
    Route::post('/servings/{servingId}/comments', [ServingController::class, 'addComment']);
    Route::post('/comments/{commentId}/react', [ServingController::class, 'reactToComment']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/{servingId}/comments', [ServingController::class, 'getComments']);
    Route::get('/comments/{commentId}/replies', [ServingController::class, 'getReplies']);
});

// ServingController
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/servings/search', [ServingController::class, 'getServings']);
});

// ServingRequestController
Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/requests', [ServingRequestController::class, 'create']);
    Route::put('/servings/requests/{id}/accept', [ServingRequestController::class, 'accept']);
    Route::put('/servings/requests/{id}/reject', [ServingRequestController::class, 'reject']);
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
