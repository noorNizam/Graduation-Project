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

    public function findByVendorToken(string $vendorToken): ?IdentityVerificationModel
    {
        return IdentityVerificationModel::where('vendor_token', $vendorToken)
            ->latest('id')
            ->first();
    }

    public function updateStatus(int $userId, string $status, ?array $data = null): bool
    {
        $sessionId = $data['session_id'] ?? $data['id'] ?? null;
        $vendorToken = $data['vendor_data'] ?? null;
        $record = null;

        if ($sessionId) {
            $record = $this->findBySessionId($sessionId);
        }

        if (! $record && $vendorToken) {
            $record = $this->findByVendorToken((string) $vendorToken);
        }

        if (! $record) {
            $record = $this->findByUserId($userId);
        }

        if (! $record) {
            return false;
        }

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
