<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Infrastructure\Models\ComplaintModel;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ComplaintRepository implements ComplaintRepositoryInterface
{
    public function create(array $data): ComplaintModel
    {
        return ComplaintModel::create($data);
    }

    public function findById(int $id): ?ComplaintModel
    {
        return ComplaintModel::with(['complainant', 'accusedUser', 'documents'])->find($id);
    }

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ComplaintModel::with(['complainant', 'accusedUser', 'documents']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['complainant_id'])) {
            $query->where('complainant_id', $filters['complainant_id']);
        }

        if (! empty($filters['accused_user_id'])) {
            $query->where('accused_user_id', $filters['accused_user_id']);
        }

        if (! empty($filters['serving_request_id'])) {
            $query->where('serving_request_id', $filters['serving_request_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findByComplainant(int $complainantId, int $perPage = 15): LengthAwarePaginator
    {
        return ComplaintModel::with(['complainant', 'accusedUser', 'documents'])
            ->where('complainant_id', $complainantId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByAccusedUser(int $accusedUserId, int $perPage = 15): LengthAwarePaginator
    {
        return ComplaintModel::with(['complainant', 'accusedUser', 'documents'])
            ->where('accused_user_id', $accusedUserId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function updateStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null, ?string $outcome = null, ?int $resolvedBy = null): ComplaintModel
    {
        $complaint = ComplaintModel::findOrFail($id);

        $data = [
            'status' => $status,
            'admin_note' => $adminNote,
        ];

        // Only touch the documents flow columns while requesting documents,
        // and only when explicitly provided, so unrelated status updates
        // cannot wipe an active deadline.
        if ($status === 'awaiting_documents' && $documentsRequestedFrom !== null) {
            $data['documents_requested_from'] = $documentsRequestedFrom;
        }
        if ($status === 'awaiting_documents' && $documentsDueAt !== null) {
            $data['documents_due_at'] = $documentsDueAt;
        }

        if ($outcome !== null) {
            $data['outcome'] = $outcome;
        }

        // Audit stamps: who closed the complaint and when. The auto-decision
        // path passes no resolver id (system decision).
        if ($status === 'resolved') {
            $data['resolved_at'] = Carbon::now();
            if ($resolvedBy !== null) {
                $data['resolved_by'] = $resolvedBy;
            }
        }

        $complaint->update($data);

        return $complaint->fresh();
    }

    public function setOutcome(int $id, string $outcome): ComplaintModel
    {
        $complaint = ComplaintModel::findOrFail($id);
        $complaint->update(['outcome' => $outcome]);

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
