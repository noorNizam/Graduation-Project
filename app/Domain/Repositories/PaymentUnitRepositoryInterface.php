<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\PaymentUnit;
use Illuminate\Support\Collection;

interface PaymentUnitRepositoryInterface
{
    public function findAll(): Collection;

    public function findById(int $id): ?PaymentUnit;

    public function create(array $data): PaymentUnit;
}
