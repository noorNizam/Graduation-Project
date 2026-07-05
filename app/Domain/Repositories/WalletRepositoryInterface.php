<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\WalletModel;
use Illuminate\Support\Collection;

interface WalletRepositoryInterface
{
    public function findByUserId(int $userId): Collection;

    public function findByUserAndUnit(int $userId, int $unitId): ?WalletModel;
}
