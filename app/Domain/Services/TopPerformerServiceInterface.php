<?php

namespace App\Domain\Services;

use Carbon\CarbonInterface;

interface TopPerformerServiceInterface
{
    /**
     * @return array ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function getTopPerformers(int $servingTypeId, ?string $month = null): array;

    /**
     * @return array ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function calculateAndStore(CarbonInterface $executionAt): array;
}
