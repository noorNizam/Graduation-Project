<?php

namespace App\Domain\Services;

interface PaymentUnitServiceInterface
{
    public function getAll(): array;

    public function create(array $data): array;
}
