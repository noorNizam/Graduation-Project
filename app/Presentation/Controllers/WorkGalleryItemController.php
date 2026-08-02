<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\WorkGalleryItemServiceInterface;
use App\Presentation\Requests\AddWorkGalleryItemRequest;
use App\Presentation\Requests\UpdateWorkGalleryItemRequest;
use Illuminate\Http\Request;

class WorkGalleryItemController
{
    public function __construct(
        private WorkGalleryItemServiceInterface $workGalleryItemService
    ) {}

    public function add(AddWorkGalleryItemRequest $request)
    {
        $data = $request->validated();

        $result = $this->workGalleryItemService->createItem(
            auth()->id(),
            $data,
            $request->file('files'),
        );

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function edit(int $id, UpdateWorkGalleryItemRequest $request)
    {
        $data = $request->validated();

        $result = $this->workGalleryItemService->updateItem(
            auth()->id(),
            $id,
            $data,
            $request->file('files'),
        );

        return response()->json($result, $this->statusCode($result));
    }

    public function remove(int $id)
    {
        $result = $this->workGalleryItemService->deleteItem(auth()->id(), $id);

        return response()->json($result, $this->statusCode($result));
    }

    public function getMy(Request $request)
    {
        $result = $this->workGalleryItemService->getMyItems(
            auth()->id(),
            $request->input('skip'),
            $request->input('take'),
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getUserItems(int $userId, Request $request)
    {
        $result = $this->workGalleryItemService->getUserItems(
            $userId,
            $request->input('skip'),
            $request->input('take'),
        );

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'User not found') {
            return response()->json($result, 404);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    private function statusCode(array $result): int
    {
        if ($result['success']) {
            return 200;
        }

        return match ($result['message'] ?? null) {
            'Forbidden' => 403,
            'Work item not found' => 404,
            default => 500,
        };
    }
}
