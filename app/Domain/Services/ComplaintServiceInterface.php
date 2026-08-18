<?php

namespace App\Domain\Services;

use App\Infrastructure\Models\ComplaintModel;

interface ComplaintServiceInterface
{
    public function createComplaint(array $data, $attachment = null): array;

    public function getComplaint(int $id): array;

    public function getAllComplaints(array $filters = [], int $perPage = 15): array;

    public function getUserComplaints(int $userId, int $perPage = 15): array;

    public function getComplaintsAgainstUser(int $userId, int $perPage = 15): array;

    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null): array;

    public function deleteComplaint(int $id): array;

    public function getComplaintsCountByStatus(string $status): array;

    public function getStatistics(): array;

    public function getComplaintModel(int $id): ?ComplaintModel;

    /**
     * Awaiting-documents complaints whose explicit deadline (documents_due_at)
     * has passed and where at least one party has uploaded their documents.
     *
     * @return ComplaintModel[]
     */
    public function getExpiredAwaitingDocuments(): array;
}
