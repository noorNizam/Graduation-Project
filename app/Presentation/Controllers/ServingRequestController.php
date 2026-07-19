<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingRequestServiceInterface;
use App\Presentation\Requests\CreateServingRequestRequest;
use Illuminate\Http\Request;

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
            $data['message'] ?? null,
            $data['automatically_cancel_after']
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

    public function listByServing(Request $request, int $servingId)
    {
        $result = $this->servingRequestService->getServingRequests($servingId, $request->input('status'));

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function listByRequester(Request $request)
    {
        $result = $this->servingRequestService->getRequesterRequests(auth()->id(), $request->input('status'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function listByOwner(Request $request)
    {
        $result = $this->servingRequestService->getReceivedRequests(auth()->id(), $request->input('status'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function remove(int $id)
    {
        $result = $this->servingRequestService->deleteRequest($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : $result['status'] ?? 500);
    }

    public function requestCompletion(int $id)
    {
        $result = $this->servingRequestService->requestCompletion($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function confirmCompletion(int $id)
    {
        $result = $this->servingRequestService->confirmCompletion($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function pendingConfirmations()
    {
        $result = $this->servingRequestService->getPendingConfirmations(auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function requestRevision(int $id)
    {
        $result = $this->servingRequestService->requestRevision($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function dispute(int $id)
    {
        $result = $this->servingRequestService->disputeRequest($id, auth()->id());

        if (isset($result['success']) && $result['success'] === false && isset($result['message']) && $result['message'] === 'Forbidden') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
