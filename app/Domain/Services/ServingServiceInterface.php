<?php

namespace App\Domain\Services;

interface ServingServiceInterface
{
    /**
     * Create a new serving.
     *
     * @return array ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function createPaidServing(array $data, $image = null): array;

    public function updatePaidServing(int $id, array $data, $image = null): array;
}
