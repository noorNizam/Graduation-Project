<?php

namespace App\Domain\Repositories;

use Illuminate\Support\Collection;

interface QuerySynonymRepositoryInterface
{
    /**
     * @param  array<int, string>  $normalizedWords
     */
    public function findByWords(array $normalizedWords): Collection;

    public function truncate(): void;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertRows(array $rows): void;
}
