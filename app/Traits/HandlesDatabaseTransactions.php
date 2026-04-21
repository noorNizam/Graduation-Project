<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Exception;

trait HandlesDatabaseTransactions
{
    /**
     * Execute a callback within a database transaction with automatic response.
     *
     * @param callable $callback
     * @return array
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