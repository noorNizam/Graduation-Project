<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function updateBalance(int $userId, int $newBalance): bool;
    public function getAllUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}