<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\Serving;

interface ServingRepositoryInterface
{
    public function create(array $data): Serving;
}
