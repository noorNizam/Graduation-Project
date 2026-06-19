<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Infrastructure\Models\ComplaintModel;
use Illuminate\Pagination\LengthAwarePaginator;

class ComplaintRepository implements ComplaintRepositoryInterface
{
    public function create(array $data): ComplaintModel
    {
        return ComplaintModel::create($data);
    }

    public function findById(int $id): ?ComplaintModel
    {
        return ComplaintModel::with(['complainant', 'accusedUser'])->find($id);
    }

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ComplaintModel::with(['complainant', 'accusedUser']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['complainant_id'])) {
            $query->where('complainant_id', $filters['complainant_id']);
        }

        if (!empty($filters['accused_user_id'])) {
            $query->where('accused_user_id', $filters['accused_user_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByComplainant(int $complainantId, int $perPage = 15): LengthAwarePaginator
    {
        return ComplaintModel::with(['complainant', 'accusedUser'])
            ->where('complainant_id', $complainantId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByAccusedUser(int $accusedUserId, int $perPage = 15): LengthAwarePaginator
    {
        return ComplaintModel::with(['complainant', 'accusedUser'])
            ->where('accused_user_id', $accusedUserId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function updateStatus(int $id, string $status, ?string $adminNote = null): ComplaintModel
    {
        $complaint = ComplaintModel::findOrFail($id);
        
        $complaint->update([
            'status' => $status,
            'admin_note' => $adminNote,
        ]);

        return $complaint->fresh();
    }

    public function delete(int $id): bool
    {
        return ComplaintModel::destroy($id) > 0;
    }

    public function countByStatus(string $status): int
    {
        return ComplaintModel::where('status', $status)->count();
    }
}