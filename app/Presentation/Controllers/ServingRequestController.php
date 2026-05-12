<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingRequestServiceInterface;
use App\Presentation\Requests\CreateServingRequestRequest;

class ServingRequestController
{
    public function __construct(
        private ServingRequestServiceInterface $servingRequestService
    ) {}

    public function create(CreateServingRequestRequest $request)
    {
        $data = $request->validated();

        $result = $this->servingRequestService->createRequest(
            auth()->id(),
            $data['serving_id'],
            $data['message'] ?? null
        );

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    public function accept(int $id)
    {
        $result = $this->servingRequestService->acceptRequest($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function reject(int $id)
    {
        $result = $this->servingRequestService->rejectRequest($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function listByServing(int $servingId)
    {
        $result = $this->servingRequestService->getServingRequests($servingId);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function listByRequester()
    {
        $result = $this->servingRequestService->getRequesterRequests(auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function listByOwner()
    {
        $result = $this->servingRequestService->getReceivedRequests(auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
