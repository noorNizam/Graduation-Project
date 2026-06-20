<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\PaymentUnitServiceInterface;
use App\Presentation\Requests\CreatePaymentUnitRequest;

class PaymentUnitController
{
    public function __construct(
        private PaymentUnitServiceInterface $paymentUnitService
    ) {}

    public function getAll()
    {
        $result = $this->paymentUnitService->getAll();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function create(CreatePaymentUnitRequest $request)
    {
        $result = $this->paymentUnitService->create($request->validated());

        return response()->json($result, $result['success'] ? 201 : 500);
    }
}
