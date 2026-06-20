<?php

namespace App\Application\Services;

use App\Domain\Repositories\PaymentUnitRepositoryInterface;
use App\Domain\Services\PaymentUnitServiceInterface;

class PaymentUnitService implements PaymentUnitServiceInterface
{
    public function __construct(
        private PaymentUnitRepositoryInterface $repository
    ) {}

    public function getAll(): array
    {
        $units = $this->repository->findAll();

        return [
            'success' => true,
            'data' => $units,
        ];
    }

    public function create(array $data): array
    {
        $unit = $this->repository->create($data);

        return [
            'success' => true,
            'data' => $unit,
            'message' => 'Payment unit created successfully',
        ];
    }
}
