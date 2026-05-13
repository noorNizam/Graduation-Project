<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Infrastructure\Models\ServingRequest;
use Illuminate\Support\Collection;

class ServingRequestRepository implements ServingRequestRepositoryInterface
{
    public function create(array $data): ServingRequest
    {
        return ServingRequest::create($data);
    }

    public function findById(int $id): ?ServingRequest
    {
        return ServingRequest::find($id);
    }

    public function findByServingId(int $servingId, ?string $status = null): Collection
    {
        $query = ServingRequest::forServing($servingId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function findByRequesterId(int $requesterId, ?string $status = null): Collection
    {
        $query = ServingRequest::forRequester($requesterId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function updateStatus(int $id, string $status): ServingRequest
    {
        $request = ServingRequest::findOrFail($id);
        $request->status = $status;
        $request->save();

        return $request;
    }

    public function existsForServingAndRequester(int $servingId, int $requesterId, string $status): bool
    {
        return ServingRequest::where('serving_id', $servingId)
            ->where('requester_id', $requesterId)
            ->where('status', $status)
            ->exists();
    }

    public function findByServingOwnerId(int $ownerId, ?string $status = null): Collection
    {
        $query = ServingRequest::query()
            ->join('servings', 'servings.id', '=', 'serving_requests.serving_id')
            ->where('servings.user_id', $ownerId)
            ->select('serving_requests.*');

        if ($status !== null) {
            $query->where('serving_requests.status', $status);
        }

        return $query->get();
    }
}
