<?php

namespace App\Application\Services;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ComplaintService implements ComplaintServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ComplaintRepositoryInterface $complaintRepository,
        private NotificationServiceInterface $notificationService
    ) {}

    public function createComplaint(array $data, $attachment = null): array
    {
        $result = $this->executeWithTransaction(function () use ($data, $attachment) {
            if ($attachment && $attachment instanceof UploadedFile) {
                $filename = time().'_'.str_replace(' ', '_', $attachment->getClientOriginalName());

                $path = 'complaints/'.$filename;

                Storage::disk('public')->put($path, file_get_contents($attachment));

                $data['attachment_path'] = $path;
                $data['attachment_name'] = $attachment->getClientOriginalName();
            }

            return $this->complaintRepository->create($data);
        });

        if (! $result['success']) {
            return $result;
        }

        $complaint = $result['data'];
        if (! empty($complaint->accused_user_id)) {
            $this->notificationService->send(
                $complaint->accused_user_id,
                'complaint_filed',
                'تم تقديم شكوى ضدك',
                'تم تقديم شكوى جديدة ضدك، ينتظر مراجعة المشرف',
                ['complaint_id' => $complaint->id]
            );
        }

        return [
            'success' => true,
            'data' => $complaint,
            'message' => 'Complaint created successfully',
        ];
    }

    public function getComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);

        if (! $complaint) {
            return [
                'success' => false,
                'message' => 'Complaint not found',
            ];
        }

        return [
            'success' => true,
            'data' => $complaint,
        ];
    }

    public function getAllComplaints(array $filters = [], int $perPage = 15): array
    {
        $complaints = $this->complaintRepository->findAll($filters, $perPage);

        return [
            'success' => true,
            'data' => $complaints->items(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
            ],
        ];
    }

    public function getUserComplaints(int $userId, int $perPage = 15): array
    {
        $complaints = $this->complaintRepository->findByComplainant($userId, $perPage);

        return [
            'success' => true,
            'data' => $complaints->items(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
            ],
        ];
    }

    public function getComplaintsAgainstUser(int $userId, int $perPage = 15): array
    {
        $complaints = $this->complaintRepository->findByAccusedUser($userId, $perPage);

        return [
            'success' => true,
            'data' => $complaints->items(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
            ],
        ];
    }

    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null): array
    {
        $complaint = $this->complaintRepository->findById($id);
        if (! $complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $result = $this->executeWithTransaction(function () use ($id, $status, $adminNote) {
            return $this->complaintRepository->updateStatus($id, $status, $adminNote);
        });

        if (! $result['success']) {
            return $result;
        }

        if ($status !== 'resolved') {
            $statusLabels = [
                'under_review' => 'قيد المراجعة',
                'rejected' => 'مرفوضة',
            ];
            $label = $statusLabels[$status] ?? $status;

            $this->notificationService->send(
                $complaint->complainant_id,
                'complaint_status_changed',
                'تحديث حالة الشكوى',
                "تم تحديث حالة شكواك إلى {$label}",
                ['complaint_id' => $id, 'status' => $status]
            );
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Complaint status updated successfully',
        ];
    }

    public function deleteComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);

        if (! $complaint) {
            return [
                'success' => false,
                'message' => 'Complaint not found',
            ];
        }

        $result = $this->executeWithTransaction(function () use ($id) {
            return $this->complaintRepository->delete($id);
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'Complaint deleted successfully',
        ];
    }

    public function getComplaintsCountByStatus(string $status): array
    {
        $count = $this->complaintRepository->countByStatus($status);

        return [
            'success' => true,
            'data' => ['count' => $count],
        ];
    }

    public function getStatistics(): array
    {
        $pending = $this->complaintRepository->countByStatus('pending');
        $underReview = $this->complaintRepository->countByStatus('under_review');
        $resolved = $this->complaintRepository->countByStatus('resolved');
        $rejected = $this->complaintRepository->countByStatus('rejected');

        return [
            'success' => true,
            'data' => [
                'pending' => $pending,
                'under_review' => $underReview,
                'resolved' => $resolved,
                'rejected' => $rejected,
                'total' => $pending + $underReview + $resolved + $rejected,
            ],
        ];
    }

    public function getComplaintModel(int $id): ?ComplaintModel
    {
        return $this->complaintRepository->findById($id);
    }
}
