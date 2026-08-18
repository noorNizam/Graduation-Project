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

    public function updateStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null): ComplaintModel;

    /**
     * Awaiting-documents complaints whose explicit deadline (documents_due_at)
     * has passed and where at least one party has uploaded their documents.
     *
     * @return ComplaintModel[]
     */
    public function findExpiredAwaitingDocuments(): array;

    public function delete(int $id): bool;

    public function countByStatus(string $status): int;
}
