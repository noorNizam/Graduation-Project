<?php

namespace App\Application\Services\ComplaintService;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Infrastructure\Models\ComplaintModel;
use Illuminate\Pagination\LengthAwarePaginator;

class ComplaintService
{
    public function __construct(
        private ComplaintRepositoryInterface $complaintRepository
    ) {}

    public function createComplaint(array $data): ComplaintModel
    {
        return $this->complaintRepository->create($data);
    }

    public function getComplaint(int $id): ?ComplaintModel
    {
        return $this->complaintRepository->findById($id);
    }

    public function getAllComplaints(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->complaintRepository->findAll($filters, $perPage);
    }

    public function getUserComplaints(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->complaintRepository->findByComplainant($userId, $perPage);
    }

    public function getComplaintsAgainstUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->complaintRepository->findByAccusedUser($userId, $perPage);
    }

    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null): ComplaintModel
    {
        return $this->complaintRepository->updateStatus($id, $status, $adminNote);
    }

    public function deleteComplaint(int $id): bool
    {
        return $this->complaintRepository->delete($id);
    }

    public function getComplaintsCountByStatus(string $status): int
    {
        return $this->complaintRepository->countByStatus($status);
    }
}