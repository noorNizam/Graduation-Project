<?php

namespace App\Console\Commands;

use App\Application\Services\WordNetImportService;
use Illuminate\Console\Command;

class ImportWordNetData extends Command
{
    protected $signature = 'wordnet:import {directory? : Directory containing *.tsv WordNet files (default: storage/app/wordnet)}';

    protected $description = 'Import cross-lingual WordNet synonyms into the query_synonyms table';

    public function __construct(
        private WordNetImportService $wordNetImportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $directory = $this->argument('directory') ?? storage_path('app/wordnet');

        $result = $this->wordNetImportService->importFromDirectory($directory);

        if (! $result['success']) {
            $this->error($result['message']);

            return Command::FAILURE;
        }

        $this->info("Imported {$result['data']['words']} synonym words across {$result['data']['synsets']} synsets.");

        return Command::SUCCESS;
    }
}
