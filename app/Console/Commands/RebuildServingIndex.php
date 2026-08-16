<?php

namespace App\Console\Commands;

use App\Domain\Services\ServingProposalServiceInterface;
use Illuminate\Console\Command;

class RebuildServingIndex extends Command
{
    protected $signature = 'serving-index:rebuild';

    protected $description = 'Rebuild the search index for active servings';

    public function __construct(
        private ServingProposalServiceInterface $proposalService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->proposalService->rebuildIndex();

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Failed to rebuild serving index');

            return Command::FAILURE;
        }

        $this->info("Serving index rebuilt ({$result['data']['indexed']} servings indexed).");

        return Command::SUCCESS;
    }
}
