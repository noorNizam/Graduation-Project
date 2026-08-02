<?php

namespace App\Domain\Services;

interface WorkGalleryItemServiceInterface
{
    public function createItem(int $userId, array $data, ?array $files): array;

    public function updateItem(int $userId, int $itemId, array $data, ?array $files): array;

    public function deleteItem(int $userId, int $itemId): array;

    public function getMyItems(int $userId, ?int $skip, ?int $take): array;

    public function getUserItems(int $userId, ?int $skip, ?int $take): array;
}
