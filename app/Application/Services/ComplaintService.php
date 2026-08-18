<?php

namespace App\Application\Services;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Traits\HandlesDatabaseTransactions;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ComplaintService implements ComplaintServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ComplaintRepositoryInterface $complaintRepository,
        private NotificationServiceInterface $notificationService,
        private PenaltyServiceInterface $penaltyService
    ) {}

    // ===================== CREATE =====================
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

        // Notify the accused party
        if (! empty($complaint->accused_user_id)) {
            $this->notificationService->send(
                $complaint->accused_user_id,
                'complaint_filed',
                'تم تقديم شكوى ضدك',
                'تم تقديم شكوى جديدة ضدك، ينتظر مراجعة المشرف',
                ['complaint_id' => $complaint->id]
            );
        }

        // Notify admins about the new complaint
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin->id,
                'new_complaint',
                'شكوى جديدة تحتاج مراجعة',
                "شكوى جديدة رقم {$complaint->id} ضد المستخدم {$complaint->accused_user_id}",
                ['complaint_id' => $complaint->id]
            );
        }

        return [
            'success' => true,
            'data' => $complaint,
            'message' => 'Complaint created successfully',
        ];
    }

    // ===================== READ =====================
    public function getComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);
        if (! $complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        return ['success' => true, 'data' => $complaint];
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

    public function getComplaintModel(int $id): ?ComplaintModel
    {
        return $this->complaintRepository->findById($id);
    }

    public function getExpiredAwaitingDocuments(): array
    {
        return $this->complaintRepository->findExpiredAwaitingDocuments();
    }

    // ===================== UPDATE =====================
    public function updateComplaintStatus(
        int $id,
        string $status,
        ?string $adminNote = null,
        ?string $documentsRequestedFrom = null,
        ?string $documentsDueAt = null
    ): array {
        $complaint = $this->complaintRepository->findById($id);
        if (! $complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }

        $result = $this->executeWithTransaction(function () use ($id, $status, $adminNote, $documentsRequestedFrom, $documentsDueAt) {
            return $this->complaintRepository->updateStatus(
                $id,
                $status,
                $adminNote,
                $documentsRequestedFrom,
                // Only persist an explicit deadline. Without one, the complaint
                // stays awaiting_documents until the admin changes it manually.
                $documentsDueAt
            );
        });

        if (! $result['success']) {
            return $result;
        }

        // Notify the complainant about the status change
        $statusLabels = [
            'under_review' => 'قيد المراجعة',
            'rejected' => 'مرفوضة',
            'resolved' => 'تم الحل',
            'awaiting_documents' => 'بانتظار الوثائق',
        ];
        $label = $statusLabels[$status] ?? $status;

        if ($status !== 'resolved') {
            $this->notificationService->send(
                $complaint->complainant_id,
                'complaint_status_changed',
                'تحديث حالة الشكوى',
                "تم تحديث حالة شكواك إلى {$label}",
                ['complaint_id' => $id, 'status' => $status]
            );
        }

        // Notify the accused party as well
        if (! empty($complaint->accused_user_id)) {
            $this->notificationService->send(
                $complaint->accused_user_id,
                'complaint_status_changed',
                'تحديث حالة الشكوى',
                "تم تحديث حالة الشكوى المقدمة ضدك إلى {$label}",
                ['complaint_id' => $id, 'status' => $status]
            );
        }

        // If awaiting documents, send the document requests
        if ($status === 'awaiting_documents') {
            $this->sendDocumentRequests($complaint, $documentsRequestedFrom, $documentsDueAt);
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Complaint status updated successfully',
        ];
    }

    // ===================== DOCUMENTS =====================
    private function sendDocumentRequests(ComplaintModel $complaint, ?string $requestedFrom, ?string $dueAt): void
    {
        $recipients = [];

        if ($requestedFrom === 'complainant' || $requestedFrom === 'both') {
            $recipients[] = $complaint->complainant_id;
        }
        if ($requestedFrom === 'accused' || $requestedFrom === 'both') {
            $recipients[] = $complaint->accused_user_id;
        }
        if (empty($recipients)) {
            $recipients = [$complaint->complainant_id, $complaint->accused_user_id];
        }

        $dueDate = $dueAt ? Carbon::parse($dueAt)->format('Y/m/d') : null;
        $message = $dueDate
            ? "يرجى تقديم وثائقك للرد على الشكوى خلال {$dueDate}"
            : 'يرجى تقديم وثائقك للرد على الشكوى في أقرب وقت';

        foreach ($recipients as $userId) {
            if (! $userId) {
                continue;
            }

            $this->notificationService->send(
                $userId,
                'documents_requested',
                'طلب تقديم وثائق إضافية',
                $message,
                ['complaint_id' => $complaint->id, 'due_at' => $dueAt]
            );
        }
    }

    /**
     * Check the documents status and apply the decision.
     * Called after either party uploads their documents.
     */
    public function checkDocumentStatusAndApplyDecision(ComplaintModel $complaint): array
    {
        if (! $complaint->isAwaitingDocuments()) {
            return ['success' => false, 'message' => 'Complaint is not awaiting documents'];
        }

        $isComplainantUploaded = (bool) $complaint->complainant_documents_uploaded;
        $isAccusedUploaded = (bool) $complaint->accused_documents_uploaded;

        // Both parties uploaded -> review
        if ($isComplainantUploaded && $isAccusedUploaded) {
            return $this->moveToUnderReview($complaint);
        }

        // Neither party uploaded yet -> keep waiting
        if (! $isComplainantUploaded && ! $isAccusedUploaded) {
            return [
                'success' => true,
                'message' => 'Waiting for both parties to upload documents',
            ];
        }

        // One side uploaded: only decide when the admin set a deadline AND it
        // has passed. Without a deadline, the complaint stays open until the
        // admin changes its status manually.
        $deadline = $complaint->documents_due_at;
        if (! $deadline) {
            return [
                'success' => true,
                'message' => 'Waiting for the other party to upload documents',
            ];
        }

        if (Carbon::now()->lessThan($deadline)) {
            return [
                'success' => true,
                'message' => 'Waiting for the other party to upload documents before the deadline',
            ];
        }

        // Complainant uploaded, accused did not -> penalty
        if ($isComplainantUploaded) {
            return $this->applyPenaltyAndResolve($complaint);
        }

        // Accused uploaded, complainant did not -> reject
        return $this->rejectComplaint($complaint);
    }

    // ===================== DECISIONS =====================
    private function applyPenaltyAndResolve(ComplaintModel $complaint): array
    {
        $result = $this->executeWithTransaction(function () use ($complaint) {
            // applyPenalty commits its own nested transaction (savepoint), so a
            // failure there must abort the outer transaction explicitly.
            $penaltyResult = $this->penaltyService->applyPenalty(
                $complaint->accused_user_id,
                'deduct_hours',
                $complaint->id,
                'لم يقدم وثائقه للدفاع عن نفسه'
            );
            if (! $penaltyResult['success']) {
                throw new \RuntimeException($penaltyResult['message'] ?? 'Penalty could not be applied');
            }

            $wallet = WalletModel::where('user_id', $complaint->accused_user_id)->first();
            if ($wallet) {
                $wallet->balance = max(0, $wallet->balance - 1);
                $wallet->save();
            }

            $complaint->status = 'resolved';
            $complaint->admin_note = 'المشتكى عليه لم يقدم وثائقه، تم تطبيق العقوبة';
            $complaint->save();
        });

        if (! $result['success']) {
            return $result;
        }

        // Notify the complainant
        $this->notificationService->send(
            $complaint->complainant_id,
            'complaint_resolved',
            'تم حل الشكوى لصالحك',
            'تم حل الشكوى لصالحك بسبب عدم تقديم الطرف الآخر وثائقه',
            ['complaint_id' => $complaint->id]
        );

        // Notify the accused party
        $this->notificationService->send(
            $complaint->accused_user_id,
            'penalty_applied',
            'تم تطبيق عقوبة',
            'تم تطبيق عقوبة عليك بسبب عدم تقديم وثائقاتك',
            ['complaint_id' => $complaint->id]
        );

        // Notify admins about the resolution
        $this->notifyAdmins('تم حل الشكوى رقم '.$complaint->id.' لصالح المشتكي', $complaint->id);

        return [
            'success' => true,
            'message' => 'Penalty applied and complaint resolved',
            'data' => $complaint,
        ];
    }

    private function rejectComplaint(ComplaintModel $complaint): array
    {
        $this->executeWithTransaction(function () use ($complaint) {
            $complaint->status = 'rejected';
            $complaint->admin_note = 'المشتكي لم يقدم وثائقه، تم رفض الشكوى';
            $complaint->save();
        });

        $this->notificationService->send(
            $complaint->complainant_id,
            'complaint_rejected',
            'تم رفض شكواك',
            'تم رفض شكواك بسبب عدم تقديم وثائقك',
            ['complaint_id' => $complaint->id]
        );

        $this->notificationService->send(
            $complaint->accused_user_id,
            'complaint_rejected',
            'تم رفض الشكوى ضدك',
            'تم رفض الشكوى ضدك بسبب عدم تقديم الطرف الآخر وثائقه',
            ['complaint_id' => $complaint->id]
        );

        // Notify admins about the rejection
        $this->notifyAdmins('تم رفض الشكوى رقم '.$complaint->id.' بسبب عدم تقديم المشتكي وثائقه', $complaint->id);

        return [
            'success' => true,
            'message' => 'Complaint rejected',
            'data' => $complaint,
        ];
    }

    private function moveToUnderReview(ComplaintModel $complaint): array
    {
        $this->executeWithTransaction(function () use ($complaint) {
            $complaint->status = 'under_review';
            $complaint->admin_note = 'تم تقديم الوثائق من الطرفين، قيد المراجعة';
            $complaint->save();
        });

        // Notify admins that both parties uploaded documents
        $this->notifyAdmins('تم رفع الوثائق من الطرفين في الشكوى رقم '.$complaint->id.'، جاهزة للمراجعة', $complaint->id);

        return [
            'success' => true,
            'message' => 'Both parties uploaded documents, moving to under review',
            'data' => $complaint,
        ];
    }

    // ===================== HELPERS =====================
    private function notifyAdmins(string $message, int $complaintId): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin->id,
                'documents_uploaded',
                'تحديث بخصوص الشكوى',
                $message,
                ['complaint_id' => $complaintId]
            );
        }
    }

    // ===================== DELETE =====================
    public function deleteComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);
        if (! $complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
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

    // ===================== STATISTICS =====================
    public function getComplaintsCountByStatus(string $status): array
    {
        $count = $this->complaintRepository->countByStatus($status);

        return ['success' => true, 'data' => ['count' => $count]];
    }

    public function getStatistics(): array
    {
        $pending = $this->complaintRepository->countByStatus('pending');
        $awaitingDocuments = $this->complaintRepository->countByStatus('awaiting_documents');
        $underReview = $this->complaintRepository->countByStatus('under_review');
        $resolved = $this->complaintRepository->countByStatus('resolved');
        $rejected = $this->complaintRepository->countByStatus('rejected');

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
}
