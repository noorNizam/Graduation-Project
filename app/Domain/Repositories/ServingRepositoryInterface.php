<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\Serving;

interface ServingRepositoryInterface
{
    public function create(array $data): Serving;
    public function findById(int $id): ?Serving;
    public function update(int $id, array $data): Serving;
}
