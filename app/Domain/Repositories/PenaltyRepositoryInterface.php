<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\PenaltyModel;
use Illuminate\Pagination\LengthAwarePaginator;

interface PenaltyRepositoryInterface
{
    public function create(array $data): PenaltyModel;

    public function findById(int $id): ?PenaltyModel;

    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function update(int $id, array $data): PenaltyModel;

    public function delete(int $id): bool;
}
