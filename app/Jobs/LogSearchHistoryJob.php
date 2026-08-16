<?php

namespace App\Jobs;

use App\Domain\Repositories\UserSearchHistoryRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogSearchHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $userId, private string $query) {}

    public function handle(UserSearchHistoryRepositoryInterface $repository): void
    {
        $query = trim($this->query);

        if ($query === '') {
            return;
        }

        try {
            $repository->create([
                'user_id' => $this->userId,
                'query' => mb_substr($query, 0, 255),
                'searched_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LogSearchHistoryJob failed: '.$e->getMessage());
            throw $e;
        }
    }
}
