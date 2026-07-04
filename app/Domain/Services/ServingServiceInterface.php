<?php

namespace App\Domain\Services;

interface ServingServiceInterface
{
    /**
     * Create a new serving.
     *
     * @return array ['success' => bool, 'data' => mixed, 'message' => string|null]
     */
    public function createPaidServing(array $data, $image = null): array;

    public function updatePaidServing(int $id, array $data, $image = null): array;

    public function createVoluntaryServing(array $data, $image = null): array;

    public function approveServing(int $servingId): array;

    public function rejectServing(int $servingId): array;

    public function getPendingServings(): array;

    public function createComment(int $userId, int $servingId, string $content, ?int $parentId = null): array;

    public function getComments(int $servingId): array;

    public function getReplies(int $commentId): array;

    public function reactToComment(int $userId, int $commentId, string $type): array;

    public function getServings(?int $excludeUserId, ?int $servingTypeId, ?int $paymentUnitId, ?int $categoryId, ?int $skip, ?int $take, ?string $name): array;

    public function getMyServings(int $userId, ?int $skip, ?int $take): array;

    public function getNearbyServings(int $userId, float $lat, float $lng, ?int $skip, ?int $take): array;

    public function getServingById(int $id, ?int $userId = null): array;

    public function updateAvailabilitySlots(int $servingId, int $userId, array $slots): array;

    public function getAvailabilitySlots(int $servingId): array;

    public function deactivateServing(int $servingId, int $userId): array;

    public function getDeactivatedServings(int $userId): array;
}
