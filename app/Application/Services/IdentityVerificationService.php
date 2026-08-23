<?php

namespace App\Application\Services;

use App\Domain\Repositories\IdentityVerificationRepositoryInterface;
use App\Domain\Services\IdentityVerificationServiceInterface;
use App\Infrastructure\Models\IdentityVerificationModel;
use App\Infrastructure\Models\User;
use App\Traits\HandlesDatabaseTransactions;
use AwaisJameel\DiditLaravelClient\Exceptions\DiditException;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IdentityVerificationService implements IdentityVerificationServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private IdentityVerificationRepositoryInterface $repository
    ) {}

    public function startVerification(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if ($user->is_identity_verified) {
            return ['success' => false, 'message' => 'Already verified'];
        }

        $existing = $this->repository->findByUserId($userId);

        if ($existing && $existing->status === 'pending') {
            if ($this->isReusablePendingSession($existing)) {
                return ['success' => true, 'verification_url' => $existing->verification_url];
            }

            // The pending session outlived its usefulness (stale link, missing
            // URL, or abandoned flow): retire it so a fresh Didit session is
            // created below.
            $this->repository->updateStatus($userId, 'expired');
        }

        $vendorToken = Str::random(32);

        try {
            $session = DiditLaravelClient::createSession(
                callbackUrl: route('identity.callback'),
                vendorData: $vendorToken,
                options: ['workflow_id' => config('didit-laravel-client.workflow_id')]
            );
        } catch (DiditException $e) {
            Log::error('Didit session creation failed', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to create verification session with Didit'];
        } catch (\Throwable $e) {
            Log::error('Didit session creation failed', ['message' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to create verification session'];
        }

        $verificationUrl = $session['url'] ?? $session['url_session'] ?? $session['verification_url'] ?? null;
        $sessionId = $session['session_id'] ?? $session['id'] ?? null;

        if (! $verificationUrl || ! $sessionId) {
            Log::error('Didit session creation returned no URL or session id', ['response' => $session]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve verification URL or session ID from Didit',
            ];
        }

        $this->repository->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'vendor_token' => $vendorToken,
            'verification_url' => $verificationUrl,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'verification_url' => $verificationUrl,
        ];
    }

    public function handleCallback(array $payload): array
    {
        $sessionId = $payload['session_id']
            ?? $payload['verificationSessionId']
            ?? ($payload['decision']['session_id'] ?? null);
        $vendorToken = $payload['vendor_data'] ?? ($payload['decision']['vendor_data'] ?? null);

        Log::info('Didit webhook received', [
            'session_id' => $sessionId,
            'status' => $payload['status'] ?? ($payload['decision']['status'] ?? null),
        ]);

        $record = null;
        if ($sessionId) {
            $record = $this->repository->findBySessionId($sessionId);
        }

        if (! $record && $vendorToken) {
            $record = $this->repository->findByVendorToken((string) $vendorToken);
        }

        if (! $record) {
            Log::warning('Didit webhook: verification record not found', [
                'session_id' => $sessionId,
            ]);

            return ['success' => false, 'message' => 'Record not found'];
        }

        $finalStatus = $this->mapDiditStatus($payload);

        if ($finalStatus === null) {
            return ['success' => true, 'message' => 'Webhook acknowledged, no status change'];
        }

        $targetUserId = $record->user_id;

        $result = $this->executeWithTransaction(function () use ($targetUserId, $finalStatus, $payload) {
            $updated = $this->repository->updateStatus($targetUserId, $finalStatus, $payload);
            $this->updateUserVerificationStatus(
                $targetUserId,
                $this->shouldMarkUserVerified($targetUserId, $finalStatus)
            );

            return $updated;
        });

        if (! $result['success'] || $result['data'] === false) {
            Log::error('Didit webhook: failed to persist verification status', [
                'user_id' => $targetUserId,
                'message' => $result['message'] ?? null,
            ]);

            return ['success' => false, 'message' => 'Failed to persist verification status'];
        }

        Log::info("Didit webhook processed for user {$targetUserId}: status updated to {$finalStatus}");

        return ['success' => true, 'message' => 'Status updated successfully'];
    }

    public function getStatus(int $userId): array
    {
        $user = User::findOrFail($userId);
        $record = $this->repository->findByUserId($userId);

        return [
            'success' => true,
            'data' => [
                'status' => $record->status ?? 'none',
                'session_id' => $record->session_id ?? null,
                'verified_at' => $record->verified_at ?? null,
                'is_identity_verified' => $user->is_identity_verified,
                'identity_verified_at' => $user->identity_verified_at,
            ],
        ];
    }

    private function mapDiditStatus(array $payload): ?string
    {
        $status = $payload['status']
            ?? $payload['decision']['status']
            ?? $payload['data']['status']
            ?? null;

        return match (strtolower((string) $status)) {
            'approved' => 'approved',
            'declined' => 'declined',
            'resubmitted' => 'resubmission_requested',
            'abandoned', 'canceled', 'cancelled', 'expired' => 'expired',
            default => null,
        };
    }

    private function isReusablePendingSession(IdentityVerificationModel $record): bool
    {
        if (! $record->verification_url || ! $record->created_at) {
            return false;
        }

        $ttlHours = max(1, (int) config('didit-laravel-client.pending_session_ttl_hours', 24));

        return $record->created_at->gt(now()->subHours($ttlHours));
    }

    private function shouldMarkUserVerified(int $userId, string $finalStatus): bool
    {
        if ($finalStatus === 'approved') {
            return true;
        }

        // A late decision for an old session must never strip a badge earned
        // through another completed session.
        return IdentityVerificationModel::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();
    }

    private function updateUserVerificationStatus(int $userId, bool $isVerified): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        // Keep the original approval timestamp instead of refreshing it when a
        // late webhook for another session re-affirms an already-verified user.
        if ($isVerified && $user->is_identity_verified) {
            return;
        }

        $user->is_identity_verified = $isVerified;
        $user->identity_verified_at = $isVerified ? now() : null;
        $user->save();
    }
}
