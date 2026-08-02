<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\WorkGalleryItemRepositoryInterface;
use App\Infrastructure\Models\WorkGalleryItem;
use Illuminate\Database\Eloquent\Collection;

class WorkGalleryItemRepository implements WorkGalleryItemRepositoryInterface
{
    public function findById(int $id): ?WorkGalleryItem
    {
        return WorkGalleryItem::with(['files', 'user'])->find($id);
    }

    public function findByUserId(int $userId, ?int $skip, ?int $take): Collection
    {
        $query = WorkGalleryItem::with(['files', 'user'])
            ->where('user_id', $userId)
            ->latest();

        if ($skip !== null) {
            $query->skip($skip);
        }

        if ($take !== null) {
            $query->take($take);
        }

        return $query->get();
    }

    public function create(array $data): WorkGalleryItem
    {
        return WorkGalleryItem::create($data);
    }

    public function update(int $id, array $data): WorkGalleryItem
    {
        $item = WorkGalleryItem::findOrFail($id);
        $item->fill($data);
        $item->save();

        return $item->load(['files', 'user']);
    }

    public function delete(int $id): void
    {
        WorkGalleryItem::findOrFail($id)->delete();
    }
}
