<?php

namespace App\Domain\Services;

interface WalletServiceInterface
{
    public function getUserWallets(int $userId): array;
}
