<?php

namespace App\Domain\Services;

interface IdentityVerificationServiceInterface
{
    public function startVerification(int $userId): array;

    public function handleCallback(array $payload): array;

    public function getStatus(int $userId): array;
}
