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
}
