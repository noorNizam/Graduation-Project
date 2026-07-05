<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Infrastructure\Models\ServingRequest;
use App\Traits\HandlesDatabaseTransactions;

class ServingRequestService implements ServingRequestServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRequestRepositoryInterface $requestRepository,
        private ServingRepositoryInterface $servingRepository,
        private WalletRepositoryInterface $walletRepository
    ) {}

    public function createRequest(int $requesterId, int $servingId, ?string $message = null, ?int $automaticallyCancelAfter = null): array
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

        $transactionResult = $this->executeWithTransaction(function () use ($requesterId, $servingId, $message, $automaticallyCancelAfter) {
            return $this->requestRepository->create([
                'serving_id' => $servingId,
                'requester_id' => $requesterId,
                'message' => $message,
                'status' => ServingRequest::STATUS_PENDING,
                'automatically_cancel_after' => $automaticallyCancelAfter ?? 14,
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

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest, $serving) {
            if ($serving->isPaid() && $serving->cost_amount > 0) {
                $wallet = $this->walletRepository->findByUserAndUnit(
                    $servingRequest->requester_id,
                    $serving->unit_id
                );

                if (! $wallet) {
                    throw new \Exception('Beneficiary wallet not found for the required payment unit');
                }

                if ($wallet->balance < $serving->cost_amount) {
                    throw new \Exception('Insufficient balance to accept this request');
                }

                $wallet->balance -= $serving->cost_amount;
                $wallet->save();

                $servingRequest->held_amount = $serving->cost_amount;
                $servingRequest->held_at = now();
                $servingRequest->accepted_at = now();
                $servingRequest->status = ServingRequest::STATUS_ACCEPTED;
                $servingRequest->save();

                return $servingRequest;
            }

            $servingRequest->accepted_at = now();
            $servingRequest->status = ServingRequest::STATUS_ACCEPTED;
            $servingRequest->save();

            return $servingRequest;
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
                            'automatically_cancel_after' => $request->automatically_cancel_after,
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

    public function requestCompletion(int $requestId, int $ownerId): array
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

        if (! $servingRequest->isAccepted()) {
            return [
                'success' => false,
                'message' => 'Request is not in accepted status',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest) {
            $servingRequest->requestCompletion();

            return $servingRequest;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Completion requested successfully',
        ];
    }

    public function confirmCompletion(int $requestId, int $userId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        if ($servingRequest->requester_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        if (! $servingRequest->isCompletionRequested()) {
            return [
                'success' => false,
                'message' => 'Completion has not been requested for this request',
            ];
        }

        $serving = $this->servingRepository->findById($servingRequest->serving_id);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Associated serving not found',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest, $serving) {
            if ($servingRequest->held_amount > 0) {
                $ownerWallet = $this->walletRepository->findByUserAndUnit(
                    $serving->user_id,
                    $serving->unit_id
                );

                if (! $ownerWallet) {
                    throw new \Exception('Owner wallet not found for the required payment unit');
                }

                $ownerWallet->balance += $servingRequest->held_amount;
                $ownerWallet->save();

                $servingRequest->held_amount = null;
                $servingRequest->held_at = null;
            }

            $servingRequest->confirmCompletion();

            return $servingRequest;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Completion confirmed successfully',
        ];
    }

    public function getPendingConfirmations(int $userId): array
    {
        $requests = $this->requestRepository->findByRequesterId($userId, ServingRequest::STATUS_COMPLETION_REQUESTED);

        $requests->load(['serving' => fn ($q) => $q->select(['id', 'title'])]);

        return [
            'success' => true,
            'data' => $requests,
        ];
    }
}
