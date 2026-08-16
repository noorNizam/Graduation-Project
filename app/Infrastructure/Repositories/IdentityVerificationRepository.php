<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\IdentityVerificationRepositoryInterface;
use App\Infrastructure\Models\IdentityVerificationModel;

class IdentityVerificationRepository implements IdentityVerificationRepositoryInterface
{
    public function create(array $data): IdentityVerificationModel
    {
        return IdentityVerificationModel::create($data);
    }

    public function findByUserId(int $userId): ?IdentityVerificationModel
    {
        return IdentityVerificationModel::where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    public function findBySessionId(string $sessionId): ?IdentityVerificationModel
    {
        return IdentityVerificationModel::where('session_id', $sessionId)
            ->latest('id')
            ->first();
    }

    public function updateStatus(int $userId, string $status, ?array $data = null): bool
    {
        // 1. البحث أولاً باستخدام session_id إن توفرت في البيانات
        $sessionId = $data['session_id'] ?? $data['id'] ?? null;
        $record = null;

        if ($sessionId) {
            $record = $this->findBySessionId($sessionId);
        }

        // 2. إذا لم يجد بالسشن، يبحث عن أحدث جلسة للمستخدم
        if (!$record) {
            $record = $this->findByUserId($userId);
        }

        // 3. إذا لم يجد سطلاً سابقاً إطلاقاً، ينشئ سجلاً جديداً فوراً
        if (!$record) {
            $record = new IdentityVerificationModel();
            $record->user_id = $userId;
            $record->session_id = $sessionId ?? ('manual_' . uniqid());
        }

        // 4. تعيين البيانات الجديدة والحفظ
        $record->status = $status;
        if ($data) {
            $record->verification_data = $data;
        }
        if ($status === 'approved') {
            $record->verified_at = now();
        }

        return $record->save();
    }
}