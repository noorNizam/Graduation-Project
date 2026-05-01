<?php

namespace App\Domain\Services;

interface ServingServiceInterface
{
    /**
     * Create a new serving.
     *
     * @param array $data
     * @return array  ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function createServing(array $data, $image = null): array;
}
