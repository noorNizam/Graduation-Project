<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\PaymentUnitRepositoryInterface;
use App\Infrastructure\Models\PaymentUnit;
use Illuminate\Support\Collection;

class PaymentUnitRepository implements PaymentUnitRepositoryInterface
{
    public function findAll(): Collection
    {
        return PaymentUnit::all();
    }

    public function findById(int $id): ?PaymentUnit
    {
        return PaymentUnit::find($id);
    }

    public function create(array $data): PaymentUnit
    {
        return PaymentUnit::create($data);
    }
}
