<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\IdentityVerificationModel;

interface IdentityVerificationRepositoryInterface
{
    public function create(array $data): IdentityVerificationModel;

    public function findByUserId(int $userId): ?IdentityVerificationModel;

    public function findBySessionId(string $sessionId): ?IdentityVerificationModel;

    public function findByVendorToken(string $vendorToken): ?IdentityVerificationModel;

    public function updateStatus(int $userId, string $status, ?array $data = null): bool;
}
