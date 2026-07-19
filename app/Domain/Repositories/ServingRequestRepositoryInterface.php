<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\ServingRequest;
use Illuminate\Support\Collection;

interface ServingRequestRepositoryInterface
{
    public function create(array $data): ServingRequest;

    public function findById(int $id): ?ServingRequest;

    public function findByServingId(int $servingId, ?string $status = null): Collection;

    public function findByRequesterId(int $requesterId, ?string $status = null): Collection;

    public function updateStatus(int $id, string $status): ServingRequest;

    public function existsForServingAndRequester(int $servingId, int $requesterId, string $status): bool;

    public function findByServingOwnerId(int $ownerId, ?string $status = null): Collection;

    public function findExpiredCompletionRequests(): Collection;

    public function findStaleAcceptedRequests(): Collection;

    public function findExpiredRevisionRequests(): Collection;

    public function delete(int $id): bool;
}
