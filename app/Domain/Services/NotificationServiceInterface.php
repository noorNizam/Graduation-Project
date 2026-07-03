<?php

namespace App\Domain\Services;

interface NotificationServiceInterface
{
    public function send(int $userId, string $type, string $title, string $body, array $data = []): array;
    public function getUserNotifications(int $userId, int $perPage = 15): array;
    public function markAsRead(int $notificationId): array;
    public function markAllAsRead(int $userId): array;
    public function getUnreadCount(int $userId): array;
}