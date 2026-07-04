<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\NotificationServiceInterface;
use App\Presentation\Requests\UpdateFcmTokenRequest;

class UserNotificationController
{
    public function __construct(
        private NotificationServiceInterface $notificationService
    ) {}

    public function index()
    {
        $result = $this->notificationService->getUserNotifications(auth()->id());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function markAsRead(int $id)
    {
        $result = $this->notificationService->markAsRead($id);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function markAllAsRead()
    {
        $result = $this->notificationService->markAllAsRead(auth()->id());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function unreadCount()
    {
        $result = $this->notificationService->getUnreadCount(auth()->id());
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request)
    {
        auth()->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['success' => true, 'message' => 'Token updated']);
    }
}