<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\WorkGalleryItem;
use Illuminate\Database\Eloquent\Collection;

interface WorkGalleryItemRepositoryInterface
{
    public function findById(int $id): ?WorkGalleryItem;

    public function findByUserId(int $userId, ?int $skip, ?int $take): Collection;

    public function create(array $data): WorkGalleryItem;

    public function update(int $id, array $data): WorkGalleryItem;

    public function delete(int $id): void;
}
