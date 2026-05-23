<?php

namespace App\Domain\Services;

interface ServingCategoryServiceInterface
{
    public function getCategories(array $filters): array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;
}
