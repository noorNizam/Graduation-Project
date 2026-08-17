<?php

namespace App\Application\Services;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\User;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ComplaintService implements ComplaintServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ComplaintRepositoryInterface $complaintRepository,
        private NotificationServiceInterface $notificationService,
        private PenaltyServiceInterface $penaltyService
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

    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null, ?string $documentsRequestedFrom = null, ?string $documentsDueAt = null): array
    {
        $complaint = $this->complaintRepository->findById($id);
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $result = $this->executeWithTransaction(function () use ($id, $status, $adminNote, $documentsRequestedFrom, $documentsDueAt) {
            return $this->complaintRepository->updateStatus($id, $status, $adminNote, $documentsRequestedFrom, $documentsDueAt);
        });

        if (!$result['success']) {
            return $result;
        }

        $statusLabels = [
            'under_review' => 'قيد المراجعة',
            'rejected' => 'مرفوضة',
            'resolved' => 'تم الحل',
            'awaiting_documents' => 'بانتظار الوثائق',
        ];

        $label = $statusLabels[$status] ?? $status;

        // إشعار للمشتكي
        if ($status !== 'resolved') {
            $this->notificationService->send(
                $complaint->complainant_id,
                'complaint_status_changed',
                'تحديث حالة الشكوى',
                "تم تحديث حالة شكواك إلى {$label}",
                ['complaint_id' => $id, 'status' => $status]
            );
        }

        // 🔥 إذا كانت الحالة "awaiting_documents"، نبعث إشعارات للجهة المطلوبة
        if ($status === 'awaiting_documents') {
            $this->sendDocumentRequests($complaint, $documentsRequestedFrom, $documentsDueAt);
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Complaint status updated successfully',
        ];
    }

    /**
     * إرسال إشعارات طلب الوثائق للجهات المطلوبة
     */
    private function sendDocumentRequests(ComplaintModel $complaint, ?string $requestedFrom, ?string $dueAt): void
    {
        $recipients = [];

        if ($requestedFrom === 'complainant' || $requestedFrom === 'both') {
            $recipients[] = $complaint->complainant_id;
        }

        if ($requestedFrom === 'accused' || $requestedFrom === 'both') {
            $recipients[] = $complaint->accused_user_id;
        }

        // إذا ما حدد المدير جهة، نرسل للطرفين
        if (empty($recipients)) {
            $recipients = [$complaint->complainant_id, $complaint->accused_user_id];
        }

        $dueDate = $dueAt ? Carbon::parse($dueAt)->format('Y/m/d') : '7 أيام';

        foreach ($recipients as $userId) {
            $this->notificationService->send(
                $userId,
                'documents_requested',
                'طلب تقديم وثائق إضافية',
                "يرجى تقديم وثائقك للرد على الشكوى خلال {$dueDate}",
                [
                    'complaint_id' => $complaint->id,
                    'due_at' => $dueAt,
                ]
            );
        }
    }

    /**
     * التحقق من حالة الوثائق بعد رفعها واتخاذ القرار
     */
    public function checkDocumentStatusAndApplyDecision(ComplaintModel $complaint): array
    {
        if (!$complaint->isAwaitingDocuments()) {
            return ['success' => false, 'message' => 'Complaint is not awaiting documents'];
        }

        $isComplainantUploaded = $complaint->complainant_documents_uploaded;
        $isAccusedUploaded = $complaint->accused_documents_uploaded;

        // الحالة 1: المشتكي رفع والمشتكى عليه ما رفع → تطبيق عقوبة
        if ($isComplainantUploaded && !$isAccusedUploaded) {
            return $this->applyPenaltyAndResolve($complaint);
        }

        // الحالة 2: المشتكى عليه رفع والمشتكي ما رفع → رفض الشكوى
        if (!$isComplainantUploaded && $isAccusedUploaded) {
            return $this->rejectComplaint($complaint);
        }

        // الحالة 3: الطرفين رفعوا → مراجعة
        if ($isComplainantUploaded && $isAccusedUploaded) {
            return $this->moveToUnderReview($complaint);
        }

        // الحالة 4: ولا واحد رفع → انتظار
        return [
            'success' => true,
            'message' => 'Waiting for both parties to upload documents',
        ];
    }

    /**
     * تطبيق عقوبة وحل الشكوى (عندما يرفع المشتكي فقط)
     */
    private function applyPenaltyAndResolve(ComplaintModel $complaint): array
    {
        $this->executeWithTransaction(function () use ($complaint) {
            $complaint->status = 'resolved';
            $complaint->admin_note = 'المشتكى عليه لم يقدم وثائقه، تم تطبيق العقوبة';
            $complaint->save();

            $this->penaltyService->applyPenalty(
                $complaint->accused_user_id,
                'deduct_hours',
                $complaint->id,
                'لم يقدم وثائقه للدفاع عن نفسه'
            );
        });

        // إشعار للمشتكي
        $this->notificationService->send(
            $complaint->complainant_id,
            'complaint_resolved',
            'تم حل الشكوى لصالحك',
            'تم حل الشكوى لصالحك بسبب عدم تقديم الطرف الآخر وثائقه',
            ['complaint_id' => $complaint->id]
        );

        // إشعار للمشتكى عليه
        $this->notificationService->send(
            $complaint->accused_user_id,
            'penalty_applied',
            'تم تطبيق عقوبة',
            'تم تطبيق عقوبة عليك بسبب عدم تقديم وثائقاتك',
            ['complaint_id' => $complaint->id]
        );

        return [
            'success' => true,
            'message' => 'Penalty applied and complaint resolved',
            'data' => $complaint
        ];
    }

    /**
     * رفض الشكوى (عندما يرفع المشتكى عليه فقط)
     */
    private function rejectComplaint(ComplaintModel $complaint): array
    {
        $this->executeWithTransaction(function () use ($complaint) {
            $complaint->status = 'rejected';
            $complaint->admin_note = 'المشتكي لم يقدم وثائقه، تم رفض الشكوى';
            $complaint->save();
        });

        // إشعار للمشتكي
        $this->notificationService->send(
            $complaint->complainant_id,
            'complaint_rejected',
            'تم رفض شكواك',
            'تم رفض شكواك بسبب عدم تقديم وثائقك',
            ['complaint_id' => $complaint->id]
        );

        // إشعار للمشتكى عليه
        $this->notificationService->send(
            $complaint->accused_user_id,
            'complaint_rejected',
            'تم رفض الشكوى ضدك',
            'تم رفض الشكوى ضدك بسبب عدم تقديم الطرف الآخر وثائقه',
            ['complaint_id' => $complaint->id]
        );

        return [
            'success' => true,
            'message' => 'Complaint rejected',
            'data' => $complaint
        ];
    }

    /**
     * نقل الشكوى للمراجعة (عندما يرفع الطرفين)
     */
    private function moveToUnderReview(ComplaintModel $complaint): array
    {
        $this->executeWithTransaction(function () use ($complaint) {
            $complaint->status = 'under_review';
            $complaint->admin_note = 'تم تقديم الوثائق من الطرفين، قيد المراجعة';
            $complaint->save();
        });

        // إشعار للمديرين
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin->id,
                'documents_received',
                'تم تقديم الوثائق من الطرفين',
                'تم تقديم الوثائق من الطرفين في الشكوى رقم ' . $complaint->id,
                ['complaint_id' => $complaint->id]
            );
        }

        return [
            'success' => true,
            'message' => 'Both parties uploaded documents, moving to under review',
            'data' => $complaint
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
        $awaitingDocuments = $this->complaintRepository->countByStatus('awaiting_documents');

        return [
            'success' => true,
            'data' => [
                'pending' => $pending,
                'awaiting_documents' => $awaitingDocuments,
                'under_review' => $underReview,
                'resolved' => $resolved,
                'rejected' => $rejected,
                'total' => $pending + $awaitingDocuments + $underReview + $resolved + $rejected,
            ],
        ];
    }

    public function getComplaintModel(int $id): ?ComplaintModel
    {
        return $this->complaintRepository->findById($id);
    }
}