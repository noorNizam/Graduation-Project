<?php

namespace App\Application\Services;

use App\Domain\Repositories\ComplaintRepositoryInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use App\Traits\HandlesDatabaseTransactions; // 🔥 نفس الـ Trait يلي عندهم
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ComplaintService
{
    use HandlesDatabaseTransactions; // 🔥 استخدميها

    public function __construct(
        private ComplaintRepositoryInterface $complaintRepository
    ) {}

    /**
     * إنشاء شكوى جديدة
     */
    public function createComplaint(array $data, $attachment = null): array
    {
        $result = $this->executeWithTransaction(function () use ($data, $attachment) {
            
            // معالجة المرفق - طريقة مبسطة
            if ($attachment && $attachment instanceof UploadedFile) {
                try {
                    // اسم الملف الجديد
                    $filename = time() . '_' . str_replace(' ', '_', $attachment->getClientOriginalName());
                    
                    // تحديد المسار
                    $path = 'complaints/' . $filename;
                    
                    // حفظ الملف في storage/app/public/complaints
                    Storage::disk('public')->put($path, file_get_contents($attachment));
                    
                    $data['attachment_path'] = $path;
                    $data['attachment_name'] = $attachment->getClientOriginalName();
                    
                    Log::info('File saved: ' . $path);
                    
                } catch (\Exception $e) {
                    Log::error('File upload error: ' . $e->getMessage());
                    return [
                        'success' => false,
                        'message' => 'خطأ في رفع الملف: ' . $e->getMessage()
                    ];
                }
            }
            
            return $this->complaintRepository->create($data);
        });
    
        if (!$result['success']) {
            return $result;
        }
    
        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'تم إنشاء الشكوى بنجاح'
        ];
    }

    /**
     * جلب شكوى حسب ID
     */
    public function getComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);

        if (!$complaint) {
            return [
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ];
        }

        return [
            'success' => true,
            'data' => $complaint
        ];
    }

    /**
     * جلب كل الشكاوي (للمدير)
     */
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
            ]
        ];
    }

    /**
     * جلب شكاوي المستخدم (يلي قدمها)
     */
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
            ]
        ];
    }

    /**
     * جلب الشكاوي يلي ضد مستخدم معين
     */
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
            ]
        ];
    }

    /**
     * تحديث حالة الشكوى (للمدير)
     */
    public function updateComplaintStatus(int $id, string $status, ?string $adminNote = null): array
    {
        $result = $this->executeWithTransaction(function () use ($id, $status, $adminNote) {
            return $this->complaintRepository->updateStatus($id, $status, $adminNote);
        });

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'تم تحديث حالة الشكوى بنجاح'
        ];
    }

    /**
     * حذف شكوى
     */
    public function deleteComplaint(int $id): array
    {
        $complaint = $this->complaintRepository->findById($id);

        if (!$complaint) {
            return [
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ];
        }

        $result = $this->executeWithTransaction(function () use ($id) {
            return $this->complaintRepository->delete($id);
        });

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'تم حذف الشكوى بنجاح'
        ];
    }

    /**
     * إحصائية عدد الشكاوي حسب الحالة
     */
    public function getComplaintsCountByStatus(string $status): array
    {
        $count = $this->complaintRepository->countByStatus($status);

        return [
            'success' => true,
            'data' => ['count' => $count]
        ];
    }

    /**
     * إحصائيات كاملة للشكاوي
     */
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
                'total' => $pending + $underReview + $resolved + $rejected
            ]
        ];
    }
    public function getComplaintModel(int $id): ?\App\Infrastructure\Models\ComplaintModel
{
    return $this->complaintRepository->findById($id);
}
}