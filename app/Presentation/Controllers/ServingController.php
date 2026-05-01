<?php

namespace App\Presentation\Controllers;

use App\Presentation\Requests\AddServingRequest;
use App\Domain\Services\ServingServiceInterface;

class ServingController
{
    public function __construct(
        private ServingServiceInterface $servingService
    ) {}

    public function store(AddServingRequest $request)
    {
        $data = $request->validated();

        // Set owner from authenticated user (route protected by auth)
        $data['user_id'] = auth()->id();

        // Pass uploaded file to the service for handling
        $image = $request->file('image');

        $result = $this->servingService->createServing($data, $image);

        return response()->json($result, $result['success'] ? 201 : 500);
    }
}
