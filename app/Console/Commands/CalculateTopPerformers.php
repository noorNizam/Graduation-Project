<?php

namespace App\Console\Commands;

use App\Domain\Services\TopPerformerServiceInterface;
use Illuminate\Console\Command;

class CalculateTopPerformers extends Command
{
    protected $signature = 'top-performers:calculate';

    protected $description = 'Calculate and store the monthly top performers';

    public function __construct(
        private TopPerformerServiceInterface $topPerformerService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->topPerformerService->calculateAndStore(now());
        } catch (\Throwable $e) {
            $this->error("Failed to calculate top performers: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Failed to calculate top performers');

            return Command::FAILURE;
        }

        $ranked = count($result['data']['ranked_users'] ?? []);
        $this->info("Top performers calculated for {$result['data']['month']} ({$ranked} users ranked).");

        return Command::SUCCESS;
    }
}
