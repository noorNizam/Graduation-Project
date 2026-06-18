<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\WalletServiceInterface;

class WalletController
{
    public function __construct(
        private WalletServiceInterface $walletService
    ) {}

    public function getMyWallets()
    {
        $result = $this->walletService->getUserWallets(auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
