<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Infrastructure\Models\ServingRequest;
use App\Traits\HandlesDatabaseTransactions;

class ServingRequestService implements ServingRequestServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRequestRepositoryInterface $requestRepository,
        private ServingRepositoryInterface $servingRepository,
        private WalletRepositoryInterface $walletRepository,
        private NotificationServiceInterface $notificationService,
        private ComplaintServiceInterface $complaintService
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

        if (! $serving->isRequestable()) {
            return [
                'success' => false,
                'message' => 'Requests are only allowed for paid servings priced in hours or voluntary servings',
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

        $this->notificationService->send(
            $serving->user_id,
            'serving_request',
            'طلب خدمة جديد',
            'لديك طلب جديد على خدمتك',
            [
                'serving_id' => $servingId,
                'request_id' => $transactionResult['data']->id,
                'requester_id' => $requesterId,
            ]
        );

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
            if ($serving->supportsEscrow()) {
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

        $this->notificationService->send(
            $servingRequest->requester_id,
            'serving_accepted',
            'تم قبول طلبك',
            "تم قبول طلبك على خدمة {$serving->title}",
            [
                'serving_id' => $serving->id,
                'request_id' => $servingRequest->id,
            ]
        );

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

        $this->notificationService->send(
            $servingRequest->requester_id,
            'serving_rejected',
            'تم رفض طلبك',
            "تم رفض طلبك على خدمة {$serving->title}",
            [
                'serving_id' => $serving->id,
                'request_id' => $servingRequest->id,
            ]
        );

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Request rejected successfully',
        ];
    }

    public function getServingRequests(int $servingId, int $userId, ?string $status = null): array
    {
        $serving = $this->servingRepository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $requests = $this->requestRepository->findByServingId($servingId, $status);

        $requests->load(['serving', 'requester']);

        $data = $requests->map(function ($r) use ($userId) {
            $flags = ServingRequest::computeActionFlags($r, $userId);

            return array_merge($r->toArray(), $flags);
        });

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function getRequesterRequests(int $requesterId, ?string $status = null): array
    {
        $requests = $this->requestRepository->findByRequesterId($requesterId, $status);

        $requests->load(['serving' => fn ($q) => $q->select(['id', 'title', 'user_id'])]);

        $data = $requests->map(function ($r) use ($requesterId) {
            $r->removable = $r->isPending();
            $flags = ServingRequest::computeActionFlags($r, $requesterId);

            return array_merge($r->toArray(), $flags);
        });

        return [
            'success' => true,
            'data' => $data,
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
            ->map(function ($groupedRequests) use ($ownerId) {
                $serving = $groupedRequests->first()->serving;

                return [
                    'serving_id' => $serving->id,
                    'serving_title' => $serving->title,
                    'requests' => $groupedRequests->map(function ($request) use ($ownerId) {
                        $flags = ServingRequest::computeActionFlags($request, $ownerId);

                        return [
                            'id' => $request->id,
                            'requester_id' => $request->requester_id,
                            'requester_full_name' => $request->requester->full_name,
                            'message' => $request->message,
                            'status' => $request->status,
                            'automatically_cancel_after' => $request->automatically_cancel_after,
                            'created_at' => $request->created_at,
                            ...$flags,
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

        $serving = $this->servingRepository->findById($servingRequest->serving_id);

        if ($serving) {
            $this->notificationService->send(
                $serving->user_id,
                'request_deleted',
                'تم حذف طلب',
                "قام طالب الخدمة بحذف طلبه على خدمة {$serving->title}",
                [
                    'serving_id' => $serving->id,
                    'request_id' => $servingRequest->id,
                ]
            );
        }

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

        $this->notificationService->send(
            $serving->user_id,
            'completion_confirmed',
            'تم تأكيد إتمام الخدمة',
            "قام طالب الخدمة بتأكيد إتمام خدمة {$serving->title}",
            [
                'serving_id' => $serving->id,
                'request_id' => $servingRequest->id,
            ]
        );

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Completion confirmed successfully',
        ];
    }

    public function getPendingConfirmations(int $userId): array
    {
        $requests = $this->requestRepository->findByRequesterId($userId, ServingRequest::STATUS_COMPLETION_REQUESTED);

        $requests->load(['serving' => fn ($q) => $q->select(['id', 'title', 'user_id'])]);

        $data = $requests->map(function ($r) use ($userId) {
            $flags = ServingRequest::computeActionFlags($r, $userId);

            return array_merge($r->toArray(), $flags);
        });

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function requestRevision(int $requestId, int $requesterId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        if ($servingRequest->requester_id !== $requesterId) {
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

        if ($servingRequest->revision_count >= 2) {
            return [
                'success' => false,
                'message' => 'Maximum revisions reached. You can dispute or confirm instead.',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest) {
            $servingRequest->requestRevision();

            return $servingRequest;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $serving = $this->servingRepository->findById($servingRequest->serving_id);

        $this->notificationService->send(
            $serving->user_id,
            'revision_requested',
            'طلب تعديل',
            'قام طالب الخدمة بطلب تعديل، يرجى إعادة إرسال الطلب',
            [
                'serving_id' => $servingRequest->serving_id,
                'request_id' => $servingRequest->id,
            ]
        );

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Revision requested successfully',
        ];
    }

    public function disputeRequest(int $requestId, int $requesterId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        if ($servingRequest->requester_id !== $requesterId) {
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

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest, $serving, $requesterId) {
            $servingRequest->dispute();

            $complaintResult = $this->complaintService->createComplaint([
                'serving_id' => $servingRequest->serving_id,
                'serving_request_id' => $servingRequest->id,
                'complainant_id' => $requesterId,
                'accused_user_id' => $serving->user_id,
                'reason' => 'Dispute on serving request',
                'description' => 'Requester opened a dispute on a completed serving request',
                'status' => 'pending',
            ]);

            if (! $complaintResult['success']) {
                throw new \Exception('Failed to create complaint: '.($complaintResult['message'] ?? 'Unknown error'));
            }

            return $servingRequest;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $this->notificationService->send(
            $serving->user_id,
            'dispute_opened',
            'تم فتح نزاع',
            'قام طالب الخدمة بفتح نزاع على طلبك، ينتظر حل المشرف',
            [
                'serving_id' => $servingRequest->serving_id,
                'request_id' => $servingRequest->id,
            ]
        );

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Dispute opened successfully',
        ];
    }

    public function resolveDispute(int $requestId, string $escrowAction, int $complaintId): array
    {
        $servingRequest = $this->requestRepository->findById($requestId);
        if (! $servingRequest) {
            return [
                'success' => false,
                'message' => 'Request not found',
            ];
        }

        if (! $servingRequest->isDisputed()) {
            return [
                'success' => false,
                'message' => 'Request is not disputed',
            ];
        }

        $serving = $this->servingRepository->findById($servingRequest->serving_id);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Associated serving not found',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($servingRequest, $serving, $escrowAction) {
            if ($escrowAction === 'release_to_owner') {
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
                }

                $servingRequest->held_amount = null;
                $servingRequest->held_at = null;
                $servingRequest->complete();
            } elseif ($escrowAction === 'refund_to_requester') {
                if ($servingRequest->held_amount > 0) {
                    $wallet = $this->walletRepository->findByUserAndUnit(
                        $servingRequest->requester_id,
                        $serving->unit_id
                    );

                    if (! $wallet) {
                        throw new \Exception('Requester wallet not found for the required payment unit');
                    }

                    $wallet->balance += $servingRequest->held_amount;
                    $wallet->save();
                }

                $servingRequest->held_amount = null;
                $servingRequest->held_at = null;
                $servingRequest->cancel();
            }

            return $servingRequest;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $notificationType = 'dispute_resolved';
        $ownerTitle = 'تم حل النزاع';
        $ownerBody = 'تم حل النزاع لصالحك وتم تحويل الساعات لمحفظتك';
        $requesterTitle = 'تم حل النزاع';
        $requesterBody = 'تم حل النزاع لصالحك وتم استرداد الساعات';

        if ($escrowAction === 'refund_to_requester') {
            $this->notificationService->send(
                $servingRequest->requester_id,
                $notificationType,
                $requesterTitle,
                $requesterBody,
                ['request_id' => $servingRequest->id, 'complaint_id' => $complaintId]
            );

            $this->notificationService->send(
                $serving->user_id,
                $notificationType,
                $ownerTitle,
                'تم حل النزاع ورد الساعات لطالب الخدمة',
                ['request_id' => $servingRequest->id, 'complaint_id' => $complaintId]
            );
        } else {
            $this->notificationService->send(
                $serving->user_id,
                $notificationType,
                $ownerTitle,
                $ownerBody,
                ['request_id' => $servingRequest->id, 'complaint_id' => $complaintId]
            );

            $this->notificationService->send(
                $servingRequest->requester_id,
                $notificationType,
                $requesterTitle,
                'تم حل النزاع وتم تحويل الساعات لمزود الخدمة',
                ['request_id' => $servingRequest->id, 'complaint_id' => $complaintId]
            );
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Dispute resolved successfully',
        ];
    }
}
