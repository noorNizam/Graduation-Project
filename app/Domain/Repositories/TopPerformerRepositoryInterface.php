<?php

namespace App\Domain\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface TopPerformerRepositoryInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Infrastructure\Models\TopPerformer>
     */
    public function findByMonthRange(string $from, string $to): Collection;

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function insertMany(array $entries): void;
}
