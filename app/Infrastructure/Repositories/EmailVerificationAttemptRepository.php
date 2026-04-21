<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Infrastructure\Models\EmailVerificationAttempt;

class EmailVerificationAttemptRepository implements EmailVerificationAttemptRepositoryInterface
{
    public function findActiveAttempt(string $email): ?EmailVerificationAttempt
    {
        return EmailVerificationAttempt::forEmail($email)
            ->active()
            ->first();
    }

    public function createAttempt(array $data): EmailVerificationAttempt
    {
        return EmailVerificationAttempt::create($data);
    }

    public function invalidateActiveAttempts(string $email): bool
    {
        return EmailVerificationAttempt::forEmail($email)
            ->active()
            ->update(['status' => 'used']);
    }

    public function findValidAttempt(string $email, string $otp): ?EmailVerificationAttempt
    {
        return EmailVerificationAttempt::forEmail($email)
            ->where('otp', $otp)
            ->active()
            ->first();
    }
}