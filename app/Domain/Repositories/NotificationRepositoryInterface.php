<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\NotificationModel;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function create(array $data): NotificationModel;

    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function markAsRead(int $notificationId): bool;

    public function markAllAsRead(int $userId): bool;

    public function getUnreadCount(int $userId): int;
}
