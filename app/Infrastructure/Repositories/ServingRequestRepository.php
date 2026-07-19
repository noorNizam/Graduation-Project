<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Infrastructure\Models\ServingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function findExpiredCompletionRequests(): Collection
    {
        return ServingRequest::with('serving')
            ->where('status', ServingRequest::STATUS_COMPLETION_REQUESTED)
            ->where('completion_requested_at', '<=', now()->subDays(2))
            ->get();
    }

    public function findStaleAcceptedRequests(): Collection
    {
        $query = ServingRequest::with('serving')
            ->where('status', ServingRequest::STATUS_ACCEPTED)
            ->whereNotNull('accepted_at');

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $query->whereRaw('DATE_ADD(updated_at, INTERVAL automatically_cancel_after DAY) <= NOW()');
        } else {
            $query->whereRaw("datetime(updated_at, '+' || automatically_cancel_after || ' days') <= datetime('now')");
        }

        return $query->get();
    }

    public function findExpiredRevisionRequests(): Collection
    {
        $query = ServingRequest::with('serving')
            ->where('status', ServingRequest::STATUS_ACCEPTED)
            ->where('revision_count', '>', 0)
            ->whereNotNull('accepted_at');

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $query->whereRaw('DATE_ADD(updated_at, INTERVAL automatically_cancel_after DAY) <= NOW()');
        } else {
            $query->whereRaw("datetime(updated_at, '+' || automatically_cancel_after || ' days') <= datetime('now')");
        }

        return $query->get();
    }

    public function delete(int $id): bool
    {
        return ServingRequest::destroy($id) > 0;
    }
}
