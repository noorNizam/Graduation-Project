<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\WorkGalleryItemFileRepositoryInterface;
use App\Infrastructure\Models\WorkGalleryItemFile;
use Illuminate\Database\Eloquent\Collection;

class WorkGalleryItemFileRepository implements WorkGalleryItemFileRepositoryInterface
{
    public function findByItemId(int $itemId): Collection
    {
        return WorkGalleryItemFile::where('work_gallery_item_id', $itemId)->get();
    }

    public function create(array $data): WorkGalleryItemFile
    {
        return WorkGalleryItemFile::create($data);
    }

    public function deleteByItemId(int $itemId): void
    {
        WorkGalleryItemFile::where('work_gallery_item_id', $itemId)->delete();
    }
}
