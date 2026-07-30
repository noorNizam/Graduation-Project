<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Infrastructure\Models\NotificationModel;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(array $data): NotificationModel
    {
        return NotificationModel::create($data);
    }

    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return NotificationModel::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function markAsRead(int $notificationId): bool
    {
        $notification = NotificationModel::find($notificationId);
        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead(int $userId): bool
    {
        NotificationModel::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return true;
    }

    public function getUnreadCount(int $userId): int
    {
        return NotificationModel::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}
