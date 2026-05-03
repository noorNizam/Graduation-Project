<?php

namespace App\Domain\Services;

interface EmailVerificationServiceInterface
{
    public function sendVerificationOtp(string $email): array;

    public function verifyOtp(string $email, string $otp): array;

    public function hasActiveAttempt(string $email): bool;
}
