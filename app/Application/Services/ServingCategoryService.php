<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingCategoryRepositoryInterface;
use App\Domain\Services\ServingCategoryServiceInterface;

class ServingCategoryService implements ServingCategoryServiceInterface
{
    public function __construct(
        private ServingCategoryRepositoryInterface $repository
    ) {}

    public function getCategories(array $filters): array
    {
        $categories = $this->repository->findAll($filters);

        return [
            'success' => true,
            'data' => $categories,
        ];
    }

    public function create(array $data): array
    {
        if (isset($data['parent_id'])) {
            $parent = $this->repository->findById($data['parent_id']);
            if (! $parent) {
                return ['success' => false, 'message' => 'Parent category not found'];
            }
            if ($parent->parent_id !== null) {
                return [
                    'success' => false,
                    'message' => 'Maximum category depth is 1 (child categories cannot have sub-categories)',
                ];
            }
        }

        $category = $this->repository->create($data);

        return [
            'success' => true,
            'data' => $category,
            'message' => 'Category created successfully',
        ];
    }

    public function update(int $id, array $data): array
    {
        $category = $this->repository->findById($id);
        if (! $category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        $category = $this->repository->update($id, $data);

        return [
            'success' => true,
            'data' => $category,
            'message' => 'Category updated successfully',
        ];
    }
}
