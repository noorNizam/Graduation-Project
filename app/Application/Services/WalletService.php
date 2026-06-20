<?php

namespace App\Application\Services;

use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Services\WalletServiceInterface;

class WalletService implements WalletServiceInterface
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository
    ) {}

    public function getUserWallets(int $userId): array
    {
        $wallets = $this->walletRepository->findByUserId($userId);

        return [
            'success' => true,
            'data' => $wallets,
        ];
    }
}
