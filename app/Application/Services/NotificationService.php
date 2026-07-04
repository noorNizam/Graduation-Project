<?php

namespace App\Application\Services;

use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Models\User;
use App\Notifications\GeneralNotification;
use App\Traits\HandlesDatabaseTransactions;

class NotificationService implements NotificationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private NotificationRepositoryInterface $notificationRepository
    ) {}

    public function send(int $userId, string $type, string $title, string $body, array $data = []): array
    {
        $result = $this->executeWithTransaction(function () use ($userId, $type, $title, $body, $data) {
            return $this->notificationRepository->create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        });

        if (!$result['success']) {
            return $result;
        }

        // 🔥 إرسال Firebase
        $this->sendFirebase($userId, $title, $body, $data);

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'تم إرسال الإشعار'
        ];
    }

    public function getUserNotifications(int $userId, int $perPage = 15): array
    {
        $notifications = $this->notificationRepository->findByUser($userId, $perPage);

        return [
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ]
        ];
    }

    public function markAsRead(int $notificationId): array
    {
        $result = $this->notificationRepository->markAsRead($notificationId);
        if (!$result) {
            return ['success' => false, 'message' => 'الإشعار غير موجود'];
        }
        return ['success' => true, 'message' => 'تم تحديث الإشعار'];
    }

    public function markAllAsRead(int $userId): array
    {
        $this->notificationRepository->markAllAsRead($userId);
        return ['success' => true, 'message' => 'تم تحديث كل الإشعارات'];
    }

    public function getUnreadCount(int $userId): array
    {
        $count = $this->notificationRepository->getUnreadCount($userId);
        return ['success' => true, 'data' => ['count' => $count]];
    }

    private function sendFirebase(int $userId, string $title, string $body, array $data = []): void
    {
        $user = User::find($userId);
        if ($user && $user->fcm_token) {
            $user->notify(new GeneralNotification($title, $body, $data));
        }
    }
}