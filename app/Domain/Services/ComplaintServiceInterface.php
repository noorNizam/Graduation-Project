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

    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null, ?string $outcome = null, ?int $resolvedBy = null): array;

    public function setOutcome(int $id, string $outcome): array;

    public function deleteComplaint(int $id): array;

    public function getComplaintsCountByStatus(string $status): array;

    public function getStatistics(): array;

    public function getComplaintModel(int $id): ?ComplaintModel;

    /**
     * Progress the complaint after a party uploaded documents: moves it to
     * under review once both parties have uploaded, otherwise reports that
     * it is still waiting. Resolution itself is always an admin decision —
     * deadlines never trigger automatic penalties or closures.
     */
    public function progressAfterDocumentUpload(ComplaintModel $complaint): array;
}
