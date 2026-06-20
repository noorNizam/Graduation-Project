<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Infrastructure\Models\ServingRequest;
use App\Traits\HandlesDatabaseTransactions;

class ServingRequestService implements ServingRequestServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRequestRepositoryInterface $requestRepository,
        private ServingRepositoryInterface $servingRepository
    ) {}

    public function createRequest(int $requesterId, int $servingId, ?string $message = null): array
    {
        $serving = $this->servingRepository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        if ($serving->user_id === $requesterId) {
            return [
                'success' => false,
                'message' => 'You cannot request your own serving',
            ];
        }

        if ($this->requestRepository->existsForServingAndRequester($servingId, $requesterId, ServingRequest::STATUS_PENDING)) {
            return [
                'success' => false,
                'message' => 'A pending request already exists for this serving',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($requesterId, $servingId, $message) {
            return $this->requestRepository->create([
                'serving_id' => $servingId,
                'requester_id' => $requesterId,
                'message' => $message,
                'status' => ServingRequest::STATUS_PENDING,
            ]);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Request created successfully',
        ];
    }

    public function acceptRequest(int $requestId, int $ownerId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        $serving = $this->servingRepository->findById($servingRequest->serving_id);
        if (! $serving || $serving->user_id !== $ownerId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        if (! $servingRequest->isPending()) {
            return [
                'success' => false,
                'message' => 'Request is not pending',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($requestId) {
            return $this->requestRepository->updateStatus($requestId, ServingRequest::STATUS_ACCEPTED);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Request accepted successfully',
        ];
    }

    public function rejectRequest(int $requestId, int $ownerId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        $serving = $this->servingRepository->findById($servingRequest->serving_id);
        if (! $serving || $serving->user_id !== $ownerId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        if (! $servingRequest->isPending()) {
            return [
                'success' => false,
                'message' => 'Request is not pending',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($requestId) {
            return $this->requestRepository->updateStatus($requestId, ServingRequest::STATUS_REJECTED);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Request rejected successfully',
        ];
    }

    public function getServingRequests(int $servingId, ?string $status = null): array
    {
        $serving = $this->servingRepository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $requests = $this->requestRepository->findByServingId($servingId, $status);

        return [
            'success' => true,
            'data' => $requests->load(['requester']),
        ];
    }

    public function getRequesterRequests(int $requesterId, ?string $status = null): array
    {
        $requests = $this->requestRepository->findByRequesterId($requesterId, $status);

        $requests->load(['serving.user' => fn ($q) => $q->select(['id', 'full_name'])]);
        $requests->each(fn ($r) => $r->removable = $r->isPending());

        return [
            'success' => true,
            'data' => $requests,
        ];
    }

    public function getReceivedRequests(int $ownerId, ?string $status = null): array
    {
        $requests = $this->requestRepository->findByServingOwnerId($ownerId, $status);

        if ($requests->isEmpty()) {
            return [
                'success' => true,
                'data' => [],
            ];
        }

        $requests->load(['serving', 'requester']);

        $grouped = $requests->filter(fn ($request) => $request->serving !== null && $request->requester !== null)
            ->groupBy('serving_id')
            ->map(function ($groupedRequests) {
                $serving = $groupedRequests->first()->serving;

                return [
                    'serving_id' => $serving->id,
                    'serving_title' => $serving->title,
                    'requests' => $groupedRequests->map(function ($request) {
                        return [
                            'id' => $request->id,
                            'requester_id' => $request->requester_id,
                            'requester_full_name' => $request->requester->full_name,
                            'message' => $request->message,
                            'status' => $request->status,
                            'created_at' => $request->created_at,
                        ];
                    })->values(),
                ];
            })->values();

        return [
            'success' => true,
            'data' => $grouped,
        ];
    }

    public function deleteRequest(int $requestId, int $userId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
                'status' => 404,
            ];
        }

        if ($servingRequest->requester_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
                'status' => 403,
            ];
        }

        if (! $servingRequest->isPending()) {
            return [
                'success' => false,
                'message' => 'Only pending requests can be deleted',
                'status' => 422,
            ];
        }

        $this->requestRepository->delete($requestId);

        return [
            'success' => true,
            'message' => 'Request deleted successfully',
        ];
    }
}
