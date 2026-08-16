<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\Serving;
use Illuminate\Database\Eloquent\Collection;

interface ServingRepositoryInterface
{
    public function create(array $data): Serving;

    public function findById(int $id): ?Serving;

    public function update(int $id, array $data): Serving;

    public function updateStatus(int $id, string $status): Serving;

    public function findPendingServings(): Collection;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Serving>
     */
    public function findNearby(
        float $lat,
        float $lng,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
        ?int $excludeUserId
    ): \Illuminate\Database\Eloquent\Collection;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Serving>
     */
    public function findByTypeAndUnit(?int $servingTypeId, ?int $unitId): \Illuminate\Database\Eloquent\Collection;
}
