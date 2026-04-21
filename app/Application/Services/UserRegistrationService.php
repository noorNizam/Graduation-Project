<?php

namespace App\Application\Services;

use App\Domain\Services\UserRegistrationServiceInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Infrastructure\Models\User;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Hash;

class UserRegistrationService implements UserRegistrationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private EmailVerificationServiceInterface $emailVerificationService
    ) {}

    public function registerCustomer(array $userData, string $otp): array
    {
        $otpVerification = $this->emailVerificationService->verifyOtp($userData['email'], $otp);

        if (!$otpVerification['success']) {
            return $otpVerification;
        }

        $transactionResult = $this->executeWithTransaction(
            function () use ($userData) {
                return User::create([
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'phone' => $userData['phone'] ?? null,
                    'birth_date' => $userData['birth_date'] ?? null,
                    'role' => User::ROLE_USER
                ]);
            }
        );

        if (!$transactionResult['success']) {
            return $transactionResult;
        }

        $user = $transactionResult['data'];
        return [
            'success' => true,
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }
}
