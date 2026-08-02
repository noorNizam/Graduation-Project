<?php

namespace App\Domain\Repositories;

use App\Infrastructure\Models\WorkGalleryItemFile;
use Illuminate\Database\Eloquent\Collection;

interface WorkGalleryItemFileRepositoryInterface
{
    public function findByItemId(int $itemId): Collection;

    public function create(array $data): WorkGalleryItemFile;

    public function deleteByItemId(int $itemId): void;
}
