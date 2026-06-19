<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\EmailVerificationAttempt;

interface EmailVerificationAttemptRepositoryInterface
{
    public function findActiveAttempt(string $email): ?EmailVerificationAttempt;

    public function createAttempt(array $data): EmailVerificationAttempt;

    public function invalidateActiveAttempts(string $email): bool;

    public function findValidAttempt(string $email, string $otp): ?EmailVerificationAttempt;
}
