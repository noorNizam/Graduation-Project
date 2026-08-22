<?php

namespace App\Application\Services;

use App\Domain\Repositories\PenaltyRepositoryInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Domain\Services\UserManagementServiceInterface;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Traits\HandlesDatabaseTransactions;

class PenaltyService implements PenaltyServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private PenaltyRepositoryInterface $penaltyRepository,
        private UserManagementServiceInterface $userManagementService
    ) {}

    public function applyPenalty(int $userId, string $type, ?int $complaintId = null, ?string $reason = null): array
    {
        $userResult = $this->userManagementService->getUserById($userId);
        if (! $userResult['success']) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $type, $complaintId, $reason) {
            $penaltyData = [
                'user_id' => $userId,
                'complaint_id' => $complaintId,
                'type' => $type,
                'reason' => $reason,
                'is_active' => true,
            ];

            switch ($type) {
                case 'warning':
                    break;

                case 'deduct_hours':
                    $penaltyData['hours_deducted'] = 1;
                    break;

                case 'suspend':
                    $penaltyData['expires_at'] = now()->addDays(7);
                    $user = User::find($userId);
                    if ($user) {
                        // A penalty must hold the account closed either way,
                        // but it never steals ownership of an admin block:
                        // the scheduler may only lift what it owns.
                        $updates = ['is_active' => false];
                        if ($user->block_source !== User::BLOCK_SOURCE_ADMIN) {
                            $updates['block_source'] = User::BLOCK_SOURCE_SUSPENSION;
                        }
                        $user->update($updates);
                    }
                    break;

                case 'ban':
                    $user = User::find($userId);
                    if ($user) {
                        $updates = ['is_active' => false];
                        if ($user->block_source !== User::BLOCK_SOURCE_ADMIN) {
                            $updates['block_source'] = User::BLOCK_SOURCE_BAN;
                        }
                        $user->update($updates);
                    }
                    break;

                default:
                    return ['success' => false, 'message' => 'Unknown penalty type'];
            }

            return $this->penaltyRepository->create($penaltyData);
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Penalty applied successfully',
        ];
    }

    public function deductHours(int $userId, int $hours, ?int $complaintId = null, ?string $reason = null): array
    {
        $wallet = WalletModel::where('user_id', $userId)->first();
        if (! $wallet) {
            return ['success' => false, 'message' => 'User wallet not found'];
        }

        // The wallet cannot go below zero: deduct what is available and
        // record the full requested amount as the penalty's severity.
        $actualDeducted = (int) min($hours, max(0, $wallet->balance));

        $result = $this->executeWithTransaction(function () use ($userId, $hours, $complaintId, $reason, $wallet) {
            $wallet->balance = max(0, $wallet->balance - $hours);
            $wallet->save();

            return $this->penaltyRepository->create([
                'user_id' => $userId,
                'complaint_id' => $complaintId,
                'type' => 'deduct_hours',
                'hours_deducted' => $hours,
                'reason' => $reason ?? "Deducted {$hours} hours due to resolved complaint",
                'is_active' => true,
            ]);
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => [
                'penalty' => $result['data'],
                'deducted_hours' => $actualDeducted,
                'new_balance' => $wallet->balance,
            ],
            'message' => "Deducted {$actualDeducted} of {$hours} hours from wallet. Remaining balance: {$wallet->balance} hours",
        ];
    }

    public function addHours(int $userId, int $hours, ?string $reason = null): array
    {
        $wallet = WalletModel::where('user_id', $userId)->first();
        if (! $wallet) {
            return ['success' => false, 'message' => 'User wallet not found'];
        }

        $result = $this->executeWithTransaction(function () use ($hours, $wallet) {
            $wallet->balance += $hours;
            $wallet->save();

            return [
                'new_balance' => $wallet->balance,
            ];
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => "Added {$hours} hours to wallet. New balance: {$result['data']['new_balance']} hours",
        ];
    }

    public function getWalletBalance(int $userId): array
    {
        $wallet = WalletModel::with(['user', 'unit'])
            ->where('user_id', $userId)
            ->first();

        if (! $wallet) {
            return ['success' => false, 'message' => 'User wallet not found'];
        }

        return [
            'success' => true,
            'data' => [
                'user_id' => $wallet->user_id,
                'user_name' => $wallet->user->full_name ?? null,
                'title' => $wallet->title,
                'balance' => $wallet->balance,
                'unit' => $wallet->unit->name ?? 'hour',
                'unit_id' => $wallet->unit_id,
            ],
        ];
    }

    public function getUserPenalties(int $userId, int $perPage = 15): array
    {
        $penalties = $this->penaltyRepository->findByUser($userId, $perPage);

        return [
            'success' => true,
            'data' => $penalties->items(),
            'meta' => [
                'current_page' => $penalties->currentPage(),
                'per_page' => $penalties->perPage(),
                'total' => $penalties->total(),
                'last_page' => $penalties->lastPage(),
            ],
        ];
    }

    public function getAllPenalties(array $filters = [], int $perPage = 15): array
    {
        $penalties = $this->penaltyRepository->findAll($filters, $perPage);

        return [
            'success' => true,
            'data' => $penalties->items(),
            'meta' => [
                'current_page' => $penalties->currentPage(),
                'per_page' => $penalties->perPage(),
                'total' => $penalties->total(),
                'last_page' => $penalties->lastPage(),
            ],
        ];
    }

    public function getPenaltyById(int $id): array
    {
        $penalty = $this->penaltyRepository->findById($id);

        if (! $penalty) {
            return ['success' => false, 'message' => 'Penalty not found'];
        }

        return [
            'success' => true,
            'data' => $penalty,
        ];
    }

    public function deactivatePenalty(int $penaltyId): array
    {
        $penalty = $this->penaltyRepository->findById($penaltyId);

        if (! $penalty) {
            return ['success' => false, 'message' => 'Penalty not found'];
        }

        $penalty = $this->penaltyRepository->update($penaltyId, ['is_active' => false]);

        if ($penalty->type === 'suspend' || $penalty->type === 'ban') {
            // Lift the block only when this was the last active suspend/ban
            // AND the account is not separately blocked by an admin.
            $otherActiveBlock = PenaltyModel::where('user_id', $penalty->user_id)
                ->where('id', '!=', $penaltyId)
                ->where('is_active', true)
                ->whereIn('type', ['suspend', 'ban'])
                ->exists();

            $user = User::find($penalty->user_id);
            if ($user
                && ! $otherActiveBlock
                && $user->block_source !== User::BLOCK_SOURCE_ADMIN) {
                $user->update([
                    'is_active' => true,
                    'block_source' => null,
                ]);
            }
        }

        return [
            'success' => true,
            'data' => $penalty,
            'message' => 'Penalty deactivated successfully',
        ];
    }

    public function createWalletForUser(int $userId, string $title = 'Main Wallet', int $unitId = 1): array
    {
        $existingWallet = WalletModel::where('user_id', $userId)->first();
        if ($existingWallet) {
            return ['success' => false, 'message' => 'User already has a wallet'];
        }

        $wallet = WalletModel::create([
            'user_id' => $userId,
            'title' => $title,
            'balance' => 0,
            'unit_id' => $unitId,
        ]);

        return [
            'success' => true,
            'data' => $wallet,
            'message' => 'Wallet created successfully',
        ];
    }
}
