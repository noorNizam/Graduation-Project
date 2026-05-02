<?php

namespace App\Application\Services;

use App\Domain\Services\ServingServiceInterface;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Jobs\DeleteServingImageJob;

class ServingService implements ServingServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRepositoryInterface $repository
    ) {}

    public function createPaidServing(array $data, $image = null): array
    {
        $transactionResult = $this->executeWithTransaction(function () use ($data, $image) {
            // Ensure serving type exists for 'paid' if not provided
            if (empty($data['serving_type_id'])) {
                $type = \App\Infrastructure\Models\ServingType::firstOrCreate(['name' => 'paid']);
                $data['serving_type_id'] = $type->id;
            }

            // Handle image storage if provided
            if ($image) {
                $userId = $data['user_id'] ?? 'anonymous';
                $path = sprintf('servings/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $image->getClientOriginalExtension());

                $stored = $image->storeAs($path, $filename, 'public');

                $data['image_url'] = Storage::url($stored);
            }

            return $this->repository->create($data);
        });

        if (!$transactionResult['success']) {
            return $transactionResult;
        }

        $serving = $transactionResult['data'];
        return [
            'success' => true,
            'data' => $serving,
        ];
    }

    public function updatePaidServing(int $id, array $data, $image = null): array
    {
        // Load existing serving to capture previous image and verify ownership
        $existingServing = $this->repository->findById($id);
        if (! $existingServing) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $oldImageUrl = $existingServing->image_url;

        // Authorization: ensure the authenticated user owns this serving
        if ($existingServing->user_id !== $data['user_id']) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($id, $data, $image) {
            // Ensure serving type exists for 'paid' if not provided
            if (empty($data['serving_type_id'])) {
                $type = \App\Infrastructure\Models\ServingType::firstOrCreate(['name' => 'paid']);
                $data['serving_type_id'] = $type->id;
            }

            // Handle image storage if provided
            if ($image) {
                $userId = $data['user_id'] ?? 'anonymous';
                $path = sprintf('servings/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $image->getClientOriginalExtension());

                $stored = $image->storeAs($path, $filename, 'public');

                $data['image_url'] = Storage::url($stored);
            }

            return $this->repository->update($id, $data);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $updated = $transactionResult['data'];

        // If image changed, enqueue deletion of the old file
        if (! empty($oldImageUrl) && ! empty($updated->image_url) && $oldImageUrl !== $updated->image_url) {
            try {
                DeleteServingImageJob::dispatch($oldImageUrl);
            } catch (\Throwable $e) {
                // Log and continue; do not fail the user request because of deletion enqueue problems
                \Illuminate\Support\Facades\Log::error('Failed to dispatch DeleteServingImageJob job: ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'data' => $updated,
        ];
    }
}
