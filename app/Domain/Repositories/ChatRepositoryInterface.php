<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\Chat;
use Illuminate\Support\Collection;

interface ChatRepositoryInterface
{
    public function findOrCreateBetween(int $userIdOne, int $userIdTwo): Chat;
    public function findById(int $id): ?Chat;
    public function findByUserId(int $userId): Collection;
    public function updateLastMessageAt(int $chatId): void;
    public function getMembers(int $chatId): Collection;
    public function addMembers(int $chatId, array $userIds): void;
    public function removeMember(int $chatId, int $userId): void;
    public function isMember(int $chatId, int $userId): bool;
}
