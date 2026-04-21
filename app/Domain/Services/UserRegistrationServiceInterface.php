<?php

namespace App\Domain\Services;

interface UserRegistrationServiceInterface
{
    public function registerCustomer(array $userData, string $otp): array;
}
