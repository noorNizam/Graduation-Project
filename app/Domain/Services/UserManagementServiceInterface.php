<?php

namespace App\Domain\Services;

interface UserManagementServiceInterface
{
    public function updateProfile(int $userId, array $data, $profilePicture = null): array;

    public function getUserById(int $userId): array;

    public function searchUsers(array $params): array;

    public function blockUser(int $userId): array;

    public function unblockUser(int $userId): array;
}
