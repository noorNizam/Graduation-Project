<?php

use App\Presentation\Controllers\AdminComplaintController;
use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\ChatController;
use App\Presentation\Controllers\PaymentUnitController;
use App\Presentation\Controllers\PenaltyController;
use App\Presentation\Controllers\ServingCategoryController;
use App\Presentation\Controllers\ServingController;
use App\Presentation\Controllers\ServingRequestController;
use App\Presentation\Controllers\ServingTypeController;
use App\Presentation\Controllers\TroubleshootingController;
use App\Presentation\Controllers\UserComplaintController;
use App\Presentation\Controllers\UserManagementController;
use App\Presentation\Controllers\WalletController;
use App\Presentation\Controllers\UserNotificationController;
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

// ServingController
Route::middleware(['auth:sanctum', 'ensure.user'])->group(function () {
    Route::post('/servings/add-paid', [ServingController::class, 'addPaidServing']);
    Route::put('/servings/add-paid/{id}', [ServingController::class, 'updatePaid']);
    Route::post('/servings/add-voluntary', [ServingController::class, 'addVoluntaryServing']);
    Route::post('/servings/{servingId}/comments', [ServingController::class, 'addComment']);
    Route::post('/comments/{commentId}/react', [ServingController::class, 'reactToComment']);
    Route::put('/servings/{servingId}/availability-slots', [ServingController::class, 'updateAvailabilitySlots']);
    Route::post('/servings/{id}/deactivate', [ServingController::class, 'deactivate']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/{servingId}/comments', [ServingController::class, 'getComments']);
    Route::get('/comments/{commentId}/replies', [ServingController::class, 'getReplies']);
});

//any user can search for servings without authentication
Route::post('/servings/search', [ServingController::class, 'getServings']);
Route::post('/servings/nearby', [ServingController::class, 'getNearbyServings']);
// ServingController
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/servings/{servingId}/availability-slots', [ServingController::class, 'getAvailabilitySlots']);
    Route::get('/servings/{id}', [ServingController::class, 'getById']);
    Route::post('/servings/my', [ServingController::class, 'getMyServings']);
    Route::get('/servings/my-deactivated', [ServingController::class, 'getDeactivated']);
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

// ServingTypeController
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/serving-types', [ServingTypeController::class, 'getAll']);
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

// UserComplaintController (authenticated users)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/complaints', [UserComplaintController::class, 'store']);
    Route::get('/my-complaints', [UserComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [UserComplaintController::class, 'show']);
    Route::get('/my-wallet', [UserComplaintController::class, 'getWalletBalance']);
    Route::get('/my-penalties', [UserComplaintController::class, 'myPenalties']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-notifications', [UserNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [UserNotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
    Route::post('/update-fcm-token', [UserNotificationController::class, 'updateFcmToken']);
});

// Admin complaint & penalty routes
Route::middleware(['auth:sanctum', 'ensure.admin'])->prefix('admin')->group(function () {
    Route::get('/complaints', [AdminComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [AdminComplaintController::class, 'show']);
    Route::put('/complaints/{id}/status', [AdminComplaintController::class, 'updateStatus']);
    Route::delete('/complaints/{id}', [AdminComplaintController::class, 'destroy']);
    Route::get('/complaints/statistics', [AdminComplaintController::class, 'statistics']);

    Route::get('/penalties', [PenaltyController::class, 'index']);
    Route::get('/penalties/{id}', [PenaltyController::class, 'show']);
    Route::get('/users/{userId}/penalties', [PenaltyController::class, 'getUserPenalties']);
    Route::delete('/penalties/{id}', [PenaltyController::class, 'destroy']);

    Route::get('/servings/pending', [ServingController::class, 'getPendingServings']);
    Route::put('/servings/{id}/approve', [ServingController::class, 'approveServing']);
    Route::put('/servings/{id}/reject', [ServingController::class, 'rejectServing']);
});

// ChatController
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chats', [ChatController::class, 'createChat']);
    Route::get('/chats', [ChatController::class, 'getChats']);
    Route::get('/chats/personal', [ChatController::class, 'getPersonalChats']);
    Route::get('/chats/groups', [ChatController::class, 'getGroupChats']);
    Route::put('/chats/{chat}', [ChatController::class, 'updateGroup']);

    Route::post('/chats/{chat}/messages', [ChatController::class, 'sendMessage']);
    Route::get('/chats/{chat}/messages', [ChatController::class, 'getMessages']);
    Route::put('/chats/{chat}/read', [ChatController::class, 'markAsRead']);
    Route::put('/chats/{chat}/received', [ChatController::class, 'markAsReceived']);

    Route::get('/chats/{chat}/members', [ChatController::class, 'getMembers']);
    Route::post('/chats/{chat}/members', [ChatController::class, 'addMembers']);
    Route::delete('/chats/{chat}/members/{user}', [ChatController::class, 'removeMember']);
    Route::post('/chats/{chat}/leave', [ChatController::class, 'leaveGroup']);
});
