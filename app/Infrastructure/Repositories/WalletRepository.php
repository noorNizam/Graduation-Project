<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\WalletRepositoryInterface;
use App\Infrastructure\Models\WalletModel;
use Illuminate\Support\Collection;

class WalletRepository implements WalletRepositoryInterface
{
    public function findByUserId(int $userId): Collection
    {
        return WalletModel::where('user_id', $userId)
            ->with('unit')
            ->get();
    }

    public function findByUserAndUnit(int $userId, int $unitId): ?WalletModel
    {
        return WalletModel::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();
    }
}
