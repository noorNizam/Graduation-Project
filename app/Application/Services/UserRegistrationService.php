<?php

namespace App\Application\Services;

use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserRegistrationService implements UserRegistrationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private EmailVerificationServiceInterface $emailVerificationService
    ) {}

    public function registerCustomer(array $userData, string $otp, $profilePicture = null): array
    {
        $otpVerification = $this->emailVerificationService->verifyOtp($userData['email'], $otp);

        if (! $otpVerification['success']) {
            return $otpVerification;
        }

        $transactionResult = $this->executeWithTransaction(
            function () use ($userData, $profilePicture) {
                $user = User::create([
                    'full_name' => $userData['full_name'],
                    'current_job' => $userData['current_job'] ?? null,
                    'address' => $userData['address'] ?? null,
                    'gender' => $userData['gender'] ?? null,
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'phone_number' => $userData['phone'] ?? null,
                    'birth_date' => $userData['birth_date'] ?? null,
                    'role' => User::ROLE_USER,
                ]);

                if ($profilePicture) {
                    $path = sprintf('profiles/%s/%s', $user->id, date('Y/m/d'));
                    $filename = sprintf('%s_%s.%s', time(), Str::random(8), $profilePicture->getClientOriginalExtension());
                    $stored = $profilePicture->storeAs($path, $filename, 'public');
                    $user->update(['profile_picture' => Storage::url($stored)]);
                }

                // create default wallet for the user, ensure payment unit exists
                $unit = PaymentUnit::firstOrCreate(['name' => 'Hour']);

                WalletModel::create([
                    'user_id' => $user->id,
                    'title' => 'رصيد الساعات',
                    'balance' => 2,
                    'unit_id' => $unit->id,
                ]);

                return $user;
            }
        );

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $user = $transactionResult['data'];

        return [
            'success' => true,
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'current_job' => $user->current_job,
                'address' => $user->address,
                'gender' => $user->gender,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'profile_picture' => $user->profile_picture ? asset($user->profile_picture) : null,
                'role' => $user->role,
            ],
        ];
    }
}
