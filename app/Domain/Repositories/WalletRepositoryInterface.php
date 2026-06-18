<?php

namespace App\Domain\Repositories;

use Illuminate\Support\Collection;

interface WalletRepositoryInterface
{
    public function findByUserId(int $userId): Collection;
}
