<?php

namespace App\Application\Services;

use App\Domain\Services\IdentityVerificationServiceInterface;
use App\Domain\Repositories\IdentityVerificationRepositoryInterface;
use App\Infrastructure\Models\User;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Log;

class IdentityVerificationService implements IdentityVerificationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private IdentityVerificationRepositoryInterface $repository
    ) {}

    public function startVerification(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if ($user->is_identity_verified) {
            return ['success' => false, 'message' => 'Already verified'];
        }

        $session = DiditLaravelClient::createSession(
            callbackUrl: route('identity.callback'),
            vendorData: (string) $userId,
            options: ['workflow_id' => config('didit.workflow_id')]
        );

        $verificationUrl = $session['url'] ?? $session['url_session'] ?? $session['verification_url'] ?? null;
        $sessionId = $session['session_id'] ?? $session['id'] ?? null;

        if (!$verificationUrl || !$sessionId) {
            Log::error('Didit Session Creation Failed:', ['response' => $session]);
            return [
                'success' => false,
                'message' => 'Failed to retrieve verification URL or session ID from Didit',
                'data' => $session
            ];
        }

        $this->repository->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'verification_url' => $verificationUrl,
        ];
    }

    public function handleCallback(array $payload): array
    {
        Log::info('Didit Full Webhook Payload Received:', $payload);

        $decisionData = $payload['decision'] ?? $payload['data'] ?? $payload;

        $sessionId = $payload['session_id'] ?? $payload['verificationSessionId'] ?? $decisionData['session_id'] ?? null;
        $rawUserId = $payload['vendor_data'] ?? $decisionData['vendor_data'] ?? null;

        $record = null;
        if ($sessionId) {
            $record = $this->repository->findBySessionId($sessionId);
        }
        
        if (!$record && $rawUserId) {
            $userId = (int) preg_replace('/[^0-9]/', '', (string) $rawUserId);
            $record = $this->getLatestRecordForUser($userId);
        }

        if (!$record) {
            Log::error('Didit Webhook Error: Verification record not found in DB', ['payload' => $payload]);
            return ['success' => false, 'message' => 'Record not found'];
        }

 
        $jsonString = strtolower(json_encode($payload));

        $isApproved = str_contains($jsonString, 'approved') ||
                      str_contains($jsonString, 'completed') ||
                      str_contains($jsonString, 'successful') ||
                      str_contains($jsonString, 'passed');

        $isResubmission = str_contains($jsonString, 'resubmit') ||
                          str_contains($jsonString, 'requires_input') ||
                          str_contains($jsonString, 'expired');


        if ($isApproved) {
            $finalStatus = 'approved';
        } elseif ($isResubmission) {
            $finalStatus = 'resubmission_requested';
        } else {
            $isDeclined = str_contains($jsonString, 'declin') || 
                          str_contains($jsonString, 'reject') || 
                          str_contains($jsonString, 'fail');
            
            $finalStatus = $isDeclined ? 'declined' : 'approved';
        }

        $targetUserId = $record->user_id;
        $this->repository->updateStatus($targetUserId, $finalStatus, $payload);
        $this->updateUserVerificationStatus($targetUserId, $finalStatus === 'approved');

        Log::info("Didit Webhook Processed for User {$targetUserId}: Status updated to {$finalStatus}");

        return ['success' => true, 'message' => 'Status updated successfully'];
    }

    private function getLatestRecordForUser(int $userId)
    {
        return \DB::table('identity_verifications')
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->first() ?? $this->repository->findByUserId($userId);
    }

    private function updateUserVerificationStatus(int $userId, bool $isVerified): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->is_identity_verified = $isVerified;
            $user->identity_verified_at = $isVerified ? now() : null;
            $user->save();
        }
    }
}