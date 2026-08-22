<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\ComplaintModel;
use Illuminate\Pagination\LengthAwarePaginator;

interface ComplaintRepositoryInterface
{
    public function create(array $data): ComplaintModel;

    public function findById(int $id): ?ComplaintModel;

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByComplainant(int $complainantId, int $perPage = 15): LengthAwarePaginator;

    public function findByAccusedUser(int $accusedUserId, int $perPage = 15): LengthAwarePaginator;

    public function updateStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null, ?string $outcome = null, ?int $resolvedBy = null): ComplaintModel;

    /**
     * Record who the resolution favored ('justified' | 'unjustified').
     */
    public function setOutcome(int $id, string $outcome): ComplaintModel;

    public function delete(int $id): bool;

    public function countByStatus(string $status): int;
}
