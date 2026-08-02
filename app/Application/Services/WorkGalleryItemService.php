<?php

namespace App\Application\Services;

use App\Domain\Repositories\WorkGalleryItemFileRepositoryInterface;
use App\Domain\Repositories\WorkGalleryItemRepositoryInterface;
use App\Domain\Services\WorkGalleryItemServiceInterface;
use App\Infrastructure\Models\User;
use App\Jobs\DeleteWorkGalleryFileJob;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkGalleryItemService implements WorkGalleryItemServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private WorkGalleryItemRepositoryInterface $repository,
        private WorkGalleryItemFileRepositoryInterface $fileRepository
    ) {}

    public function createItem(int $userId, array $data, ?array $files): array
    {
        $transactionResult = $this->executeWithTransaction(function () use ($userId, $data, $files) {
            $item = $this->repository->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'date_of_achievement' => $data['date_of_achievement'] ?? null,
                'user_id' => $userId,
            ]);

            if (! empty($files)) {
                $this->storeFiles($item->id, $userId, $files);
            }

            return $item->load(['files', 'user']);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $this->formatItem($transactionResult['data']),
            'message' => 'Work item created successfully',
        ];
    }

    public function updateItem(int $userId, int $itemId, array $data, ?array $files): array
    {
        $item = $this->repository->findById($itemId);
        if (! $item) {
            return [
                'success' => false,
                'message' => 'Work item not found',
            ];
        }

        if ($item->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        // Capture existing file URLs before replacement for cleanup after commit
        $oldFileUrls = $files !== null
            ? $this->fileRepository->findByItemId($itemId)->pluck('file_url')->toArray()
            : [];

        $transactionResult = $this->executeWithTransaction(function () use ($item, $data, $files, $userId) {
            // Only modify properties that are not null
            $updateData = array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'date_of_achievement' => $data['date_of_achievement'] ?? null,
            ], fn ($value) => $value !== null);

            if (! empty($updateData)) {
                $this->repository->update($item->id, $updateData);
            }

            // If a new files set was provided, replace all existing files
            if ($files !== null) {
                $this->fileRepository->deleteByItemId($item->id);

                if (! empty($files)) {
                    $this->storeFiles($item->id, $userId, $files);
                }
            }

            return $this->repository->findById($item->id);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        // Queue cleanup of replaced files after commit
        if ($files !== null && ! empty($oldFileUrls)) {
            foreach ($oldFileUrls as $url) {
                $this->dispatchFileCleanup($url);
            }
        }

        return [
            'success' => true,
            'data' => $this->formatItem($transactionResult['data']),
            'message' => 'Work item updated successfully',
        ];
    }

    public function deleteItem(int $userId, int $itemId): array
    {
        $item = $this->repository->findById($itemId);
        if (! $item) {
            return [
                'success' => false,
                'message' => 'Work item not found',
            ];
        }

        if ($item->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        $fileUrls = $this->fileRepository->findByItemId($itemId)->pluck('file_url')->toArray();

        $transactionResult = $this->executeWithTransaction(function () use ($itemId) {
            $this->repository->delete($itemId);

            return true;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        foreach ($fileUrls as $url) {
            $this->dispatchFileCleanup($url);
        }

        return [
            'success' => true,
            'message' => 'Work item deleted successfully',
        ];
    }

    public function getMyItems(int $userId, ?int $skip, ?int $take): array
    {
        $items = $this->repository->findByUserId($userId, $skip, $take);

        return [
            'success' => true,
            'data' => $items->map(fn ($item) => $this->formatItem($item))->values(),
        ];
    }

    public function getUserItems(int $userId, ?int $skip, ?int $take): array
    {
        if (! User::whereKey($userId)->exists()) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $items = $this->repository->findByUserId($userId, $skip, $take);

        return [
            'success' => true,
            'data' => $items->map(fn ($item) => $this->formatItem($item))->values(),
        ];
    }

    private function storeFiles(int $itemId, int $userId, array $files): void
    {
        foreach ($files as $file) {
            $path = sprintf('work_gallery/%s/%s', $userId, date('Y/m/d'));
            $filename = sprintf('%s_%s.%s', time(), Str::random(8), $file->getClientOriginalExtension());

            $stored = $file->storeAs($path, $filename, 'public');

            $this->fileRepository->create([
                'work_gallery_item_id' => $itemId,
                'file_url' => Storage::url($stored),
                'file_type' => $file->getMimeType(),
            ]);
        }
    }

    private function dispatchFileCleanup(string $url): void
    {
        try {
            DeleteWorkGalleryFileJob::dispatch($url);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch DeleteWorkGalleryFileJob: '.$e->getMessage());
        }
    }

    private function formatItem($item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'date_of_achievement' => $item->date_of_achievement ? $item->date_of_achievement->format('Y-m-d') : null,
            'user_id' => $item->user_id,
            'user_full_name' => $item->user->full_name ?? null,
            'files' => $item->files->map(fn ($file) => [
                'id' => $file->id,
                'file_url' => $file->file_url ? asset($file->file_url) : null,
                'file_type' => $file->file_type,
            ])->values(),
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
