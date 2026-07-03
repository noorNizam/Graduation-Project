<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingServiceInterface;
use App\Presentation\Requests\AddPaidServingRequest;
use App\Presentation\Requests\CreateCommentRequest;
use App\Presentation\Requests\GetServingsRequest;
use App\Presentation\Requests\NearbyServingsRequest;
use App\Presentation\Requests\ReactCommentRequest;
use App\Presentation\Requests\UpdateAvailabilitySlotsRequest;
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

    public function addVoluntaryServing(AddPaidServingRequest $request)
    {
        $data = $request->validated();

        $data['user_id'] = auth()->id();

        $image = $request->file('image');

        $result = $this->servingService->createVoluntaryServing($data, $image);

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function approveServing(int $id)
    {
        $result = $this->servingService->approveServing($id);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function rejectServing(int $id)
    {
        $result = $this->servingService->rejectServing($id);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getPendingServings()
    {
        $result = $this->servingService->getPendingServings();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getAvailabilitySlots(int $servingId)
    {
        $result = $this->servingService->getAvailabilitySlots($servingId);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function getById(int $id)
    {
        $result = $this->servingService->getServingById($id, auth()->id());

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function getNearbyServings(NearbyServingsRequest $request)
    {
        $validated = $request->validated();
        $result = $this->servingService->getNearbyServings(
            auth()->id(),
            $validated['lat'],
            $validated['lng'],
            $validated['skip'] ?? null,
            $validated['take'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getServings(GetServingsRequest $request)
    {
        $excludeUserId = auth()->id();
        $validated = $request->validated();

        $result = $this->servingService->getServings(
            $excludeUserId,
            $validated['serving_type_id'] ?? null,
            $validated['payment_unit_id'] ?? null,
            $validated['serving_category_id'] ?? null,
            $validated['skip'] ?? null,
            $validated['take'] ?? null,
            $validated['name'] ?? null
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getMyServings(\Illuminate\Http\Request $request)
    {
        $result = $this->servingService->getMyServings(
            auth()->id(),
            $request->input('skip'),
            $request->input('take'),
        );

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

    public function updateAvailabilitySlots(int $servingId, UpdateAvailabilitySlotsRequest $request)
    {
        $result = $this->servingService->updateAvailabilitySlots(
            $servingId,
            auth()->id(),
            $request->validated()['slots'],
        );

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
