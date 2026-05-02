<?php

namespace App\Application\Services;

use App\Domain\Repositories\EmailVerificationAttemptRepositoryInterface;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Mail\OTPVerificationEmail;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService implements EmailVerificationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private EmailVerificationAttemptRepositoryInterface $repository
    ) {}

    public function sendVerificationOtp(string $email): array
    {
        $transactionResult = $this->executeWithTransaction(
            function () use ($email) {
                $this->repository->invalidateActiveAttempts($email);

                $otp = $this->generateOtp();

                $attempt = $this->repository->createAttempt([
                    'email' => $email,
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(10),
                ]);

                Mail::to($email)->send(new OTPVerificationEmail($otp));

                return $attempt;
            }
        );

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_at' => $transactionResult['data']->expires_at,
        ];
    }

    public function verifyOtp(string $email, string $otp): array
    {
        $attempt = $this->repository->findValidAttempt($email, $otp);

        if (! $attempt) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ];
        }

        $transactionResult = $this->executeWithTransaction(
            function () use ($attempt) {
                $attempt->markAsUsed();

                return $attempt;
            }
        );

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'message' => 'Email verified successfully',
        ];
    }

    public function hasActiveAttempt(string $email): bool
    {
        return $this->repository->findActiveAttempt($email) !== null;
    }

    private function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
