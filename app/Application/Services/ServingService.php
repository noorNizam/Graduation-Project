<?php

namespace App\Application\Services;

use App\Domain\Services\ServingServiceInterface;
use App\Domain\Repositories\ServingRepositoryInterface;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ServingService implements ServingServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRepositoryInterface $repository
    ) {}

    public function createServing(array $data, $image = null): array
    {
        $transactionResult = $this->executeWithTransaction(function () use ($data, $image) {
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
}
