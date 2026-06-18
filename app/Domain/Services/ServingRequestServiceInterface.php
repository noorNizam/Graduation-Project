<?php

namespace App\Domain\Services;

interface ServingRequestServiceInterface
{
    public function createRequest(int $requesterId, int $servingId, ?string $message = null): array;

    public function acceptRequest(int $requestId, int $ownerId): array;

    public function rejectRequest(int $requestId, int $ownerId): array;

    public function getServingRequests(int $servingId, ?string $status = null): array;

    public function getRequesterRequests(int $requesterId, ?string $status = null): array;

    public function getReceivedRequests(int $ownerId, ?string $status = null): array;

    public function deleteRequest(int $requestId, int $userId): array;
}
