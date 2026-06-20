<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Infrastructure\Models\Serving;

class ServingRepository implements ServingRepositoryInterface
{
    public function create(array $data): Serving
    {
        // Create and return the Eloquent model
        return Serving::create($data);
    }

    public function findById(int $id): ?Serving
    {
        return Serving::find($id);
    }

    public function update(int $id, array $data): Serving
    {
        $serving = Serving::findOrFail($id);
        $serving->fill($data);
        $serving->save();

        return $serving;
    }

    public function findNearby(
        float $lat,
        float $lng,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
        int $excludeUserId
    ): \Illuminate\Database\Eloquent\Collection {
        return Serving::with(['user', 'category', 'unit', 'servingType'])
            ->select('*')
            ->selectRaw('
                (6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(location_lat))
                    * COS(RADIANS(location_lng) - RADIANS(?))
                    + SIN(RADIANS(?)) * SIN(RADIANS(location_lat))
                )) AS distance
            ', [$lat, $lng, $lat])
            ->where('user_id', '!=', $excludeUserId)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->whereBetween('location_lat', [$minLat, $maxLat])
            ->whereBetween('location_lng', [$minLng, $maxLng])
            ->havingRaw('distance <= 1')
            ->orderBy('distance')
            ->get();
    }
}
