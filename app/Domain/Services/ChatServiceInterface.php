<?php

namespace App\Domain\Services;

interface ChatServiceInterface
{
    public function createChat(int $userId, array $data): array;
    public function sendMessage(int $chatId, int $senderId, string $content): array;
    public function getChats(int $userId): array;
    public function getMessages(int $chatId, int $userId, ?int $afterId = null, ?int $beforeId = null): array;
    public function markAsRead(int $chatId, int $userId): array;
    public function markAsReceived(int $chatId, int $userId): array;
    public function getMembers(int $chatId, int $userId): array;
    public function addMembers(int $chatId, int $userId, array $memberIds): array;
    public function removeMember(int $chatId, int $userId, int $targetUserId): array;
    public function leaveGroup(int $chatId, int $userId): array;
    public function updateGroup(int $chatId, int $userId, array $data): array;
}
