<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingCategoryServiceInterface;
use App\Presentation\Requests\CreateCategoryRequest;
use App\Presentation\Requests\GetCategoriesRequest;
use App\Presentation\Requests\UpdateCategoryRequest;

class ServingCategoryController
{
    public function __construct(
        private ServingCategoryServiceInterface $servingCategoryService
    ) {}

    public function getCategories(GetCategoriesRequest $request)
    {
        $result = $this->servingCategoryService->getCategories($request->validated());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function create(CreateCategoryRequest $request)
    {
        $result = $this->servingCategoryService->create($request->validated());

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function update(int $id, UpdateCategoryRequest $request)
    {
        $result = $this->servingCategoryService->update($id, $request->validated());

        return response()->json($result, $result['success'] ? 200 : 404);
    }
}
