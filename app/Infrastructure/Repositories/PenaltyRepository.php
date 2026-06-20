<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Infrastructure\Models\PenaltyModel;
use Illuminate\Pagination\LengthAwarePaginator;

class PenaltyRepository implements PenaltyRepositoryInterface
{
    public function create(array $data): PenaltyModel
    {
        return PenaltyModel::create($data);
    }

    public function findById(int $id): ?PenaltyModel
    {
        return PenaltyModel::with(['user', 'complaint'])->find($id);
    }

    public function findByUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return PenaltyModel::with(['user', 'complaint'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PenaltyModel::with(['user', 'complaint']);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function update(int $id, array $data): PenaltyModel
    {
        $penalty = PenaltyModel::findOrFail($id);
        $penalty->update($data);

        return $penalty->fresh();
    }

    public function delete(int $id): bool
    {
        return PenaltyModel::destroy($id) > 0;
    }
}
