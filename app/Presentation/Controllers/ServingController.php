<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingServiceInterface;
use App\Presentation\Requests\AddPaidServingRequest;
use App\Presentation\Requests\CreateCommentRequest;
use App\Presentation\Requests\ReactCommentRequest;
use App\Presentation\Requests\UpdatePaidServingRequest;

class ServingController
{
    public function __construct(
        private ServingServiceInterface $servingService
    ) {}

    public function addPaidServing(AddPaidServingRequest $request)
    {
        $data = $request->validated();

        // Set owner from authenticated user (route protected by auth)
        $data['user_id'] = auth()->id();

        // Pass uploaded file to the service for handling
        $image = $request->file('image');

        $result = $this->servingService->createPaidServing($data, $image);

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function updatePaid(int $id, UpdatePaidServingRequest $request)
    {
        $data = $request->validated();

        // Set owner from authenticated user (route protected by auth)
        $data['user_id'] = auth()->id();

        $image = $request->file('image');

        $result = $this->servingService->updatePaidServing($id, $data, $image);

        // If service threw Forbidden, return 403
        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function addComment(int $servingId, CreateCommentRequest $request)
    {
        $data = $request->validated();

        $result = $this->servingService->createComment(
            auth()->id(),
            $servingId,
            $data['content'],
            $data['parent_id'] ?? null,
        );

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    public function getComments(int $servingId)
    {
        $result = $this->servingService->getComments($servingId);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function getReplies(int $commentId)
    {
        $result = $this->servingService->getReplies($commentId);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function reactToComment(int $commentId, ReactCommentRequest $request)
    {
        $result = $this->servingService->reactToComment(
            auth()->id(),
            $commentId,
            $request->validated()['type'],
        );

        return response()->json($result, $result['success'] ? 200 : 404);
    }
}
