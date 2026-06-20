<?php

namespace App\Domain\Repositories;

use App\Models\ServingCategory;
use Illuminate\Support\Collection;

interface ServingCategoryRepositoryInterface
{
    public function findAll(?array $filters): Collection;

    public function findById(int $id): ?ServingCategory;

    public function create(array $data): ServingCategory;

    public function update(int $id, array $data): ServingCategory;
}
