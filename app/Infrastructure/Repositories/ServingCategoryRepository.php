<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ServingCategoryRepositoryInterface;
use App\Models\ServingCategory;
use Illuminate\Support\Collection;

class ServingCategoryRepository implements ServingCategoryRepositoryInterface
{
    public function findAll(?array $filters): Collection
    {
        $query = ServingCategory::query();

        if (! empty($filters['name'])) {
            $query->where('name', 'LIKE', '%'.$filters['name'].'%');
        }
        if (array_key_exists('parent_id', $filters ?? [])) {
            if ($filters['parent_id'] !== null) {
                $query->where('parent_id', $filters['parent_id']);
            } else {
                $query->whereNull('parent_id');
            }
        }

        return $query->get();
    }

    public function findById(int $id): ?ServingCategory
    {
        return ServingCategory::find($id);
    }

    public function create(array $data): ServingCategory
    {
        return ServingCategory::create($data);
    }

    public function update(int $id, array $data): ServingCategory
    {
        $category = ServingCategory::findOrFail($id);
        $category->fill($data);
        $category->save();

        return $category;
    }
}
