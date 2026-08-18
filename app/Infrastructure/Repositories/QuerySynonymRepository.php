<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\QuerySynonymRepositoryInterface;
use App\Infrastructure\Models\QuerySynonym;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuerySynonymRepository implements QuerySynonymRepositoryInterface
{
    public function findByWords(array $normalizedWords): Collection
    {
        if ($normalizedWords === []) {
            return new Collection;
        }

        return QuerySynonym::whereIn('word', $normalizedWords)->get();
    }

    public function truncate(): void
    {
        QuerySynonym::query()->delete();
    }

    public function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('query_synonyms')->insert($chunk);
        }
    }
}
