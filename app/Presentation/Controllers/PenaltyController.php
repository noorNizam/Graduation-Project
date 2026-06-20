<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\PenaltyServiceInterface;
use App\Presentation\Requests\PenaltyFilterRequest;

class PenaltyController
{
    public function __construct(
        private PenaltyServiceInterface $penaltyService
    ) {}

    public function index(PenaltyFilterRequest $request)
    {
        $filters = $request->validated();
        $perPage = $filters['per_page'] ?? 15;

        $result = $this->penaltyService->getAllPenalties($filters, $perPage);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function show(int $id)
    {
        $result = $this->penaltyService->getPenaltyById($id);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function getUserPenalties(int $userId, PenaltyFilterRequest $request)
    {
        $perPage = $request->validated()['per_page'] ?? 15;

        $result = $this->penaltyService->getUserPenalties($userId, $perPage);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function destroy(int $id)
    {
        $result = $this->penaltyService->deactivatePenalty($id);

        return response()->json($result, $result['success'] ? 200 : 404);
    }
}
