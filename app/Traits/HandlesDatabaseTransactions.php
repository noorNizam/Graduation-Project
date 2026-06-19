<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\DB;

trait HandlesDatabaseTransactions
{
    /**
     * Execute a callback within a database transaction with automatic response.
     */
    protected function executeWithTransaction(callable $callback): array
    {
        DB::beginTransaction();

        try {
            $result = $callback();

            DB::commit();

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
