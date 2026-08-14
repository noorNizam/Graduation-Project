<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\TopPerformerRepositoryInterface;
use App\Infrastructure\Models\TopPerformer;
use Illuminate\Database\Eloquent\Collection;

class TopPerformerRepository implements TopPerformerRepositoryInterface
{
    public function findByMonthRange(string $from, string $to): Collection
    {
        return TopPerformer::with('user')
            ->where('date', '>=', $from)
            ->where('date', '<', $to)
            ->orderBy('rank')
            ->get();
    }

    public function insertMany(array $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $now = now();
        $entries = array_map(function (array $entry) use ($now) {
            $entry['created_at'] = $now;
            $entry['updated_at'] = $now;

            return $entry;
        }, $entries);

        TopPerformer::insert($entries);
    }
}
