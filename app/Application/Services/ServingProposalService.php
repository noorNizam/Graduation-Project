<?php

namespace App\Application\Services;

use App\Domain\Repositories\QuerySynonymRepositoryInterface;
use App\Domain\Repositories\UserSearchHistoryRepositoryInterface;
use App\Domain\Services\ServingProposalServiceInterface;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TeamTNT\TNTSearch\TNTSearch;

class ServingProposalService implements ServingProposalServiceInterface
{
    public const INDEX_NAME = 'servings.index';

    public const INDEX_STORAGE = 'app/tntsearch';

    private const MAX_DISTINCT_QUERIES = 20;

    private const TOP_K = 50;

    private const HISTORY_WINDOW_DAYS = 30;

    private const TRENDING_WINDOW_DAYS = 30;

    public function __construct(
        private UserSearchHistoryRepositoryInterface $historyRepository,
        private QuerySynonymRepositoryInterface $synonymRepository
    ) {}

    public function rebuildIndex(): array
    {
        $storage = $this->storagePath();

        if (! is_dir($storage)) {
            mkdir($storage, 0777, true);
        }

        try {
            $indexer = $this->engine()->createIndex(self::INDEX_NAME, true);

            $servings = Serving::active()
                ->select('id', 'title', 'description')
                ->orderBy('id')
                ->get();

            foreach ($servings as $serving) {
                $indexer->insert([
                    'id' => $serving->id,
                    'text' => $serving->title.' '.$serving->title.' '.$serving->description,
                ]);
            }

            return [
                'success' => true,
                'data' => ['indexed' => $servings->count()],
            ];
        } catch (\Throwable $e) {
            Log::error('ServingProposalService::rebuildIndex failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to rebuild serving index',
            ];
        }
    }

    public function getProposedServings(int $userId, int $skip, int $take): array
    {
        $history = $this->historyRepository->findRecentByUserId(
            $userId,
            self::HISTORY_WINDOW_DAYS,
            self::MAX_DISTINCT_QUERIES * 25
        );
        $distinctQueries = $this->distinctQueries($history);

        $scores = $distinctQueries === [] ? [] : $this->scoreByHistory($distinctQueries);

        if ($scores === []) {
            return $this->trendingResponse($userId, $skip, $take);
        }

        $eligibleIds = $this->eligibleServingIds($userId, array_keys($scores));

        if ($eligibleIds === []) {
            return $this->trendingResponse($userId, $skip, $take);
        }

        usort($eligibleIds, fn ($a, $b) => ($scores[$b] ?? 0) <=> ($scores[$a] ?? 0));

        $pageIds = array_slice($eligibleIds, $skip, $take);

        if ($pageIds === []) {
            return ['success' => true, 'data' => collect()];
        }

        $servings = Serving::with(['user', 'category', 'unit', 'servingType'])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortByDesc(fn ($serving) => $scores[$serving->id] ?? 0)
            ->values();

        return [
            'success' => true,
            'data' => $servings->map(fn ($serving) => $this->toDto($serving, $scores[$serving->id] ?? 0, 'based_on_searches'))->values(),
        ];
    }

    private function eligibleServingIds(int $userId, array $ids): array
    {
        $requestedIds = ServingRequest::where('requester_id', $userId)->pluck('serving_id')->all();

        $query = Serving::whereIn('id', $ids)
            ->active()
            ->where('user_id', '!=', $userId);

        if ($requestedIds !== []) {
            $query->whereNotIn('id', $requestedIds);
        }

        return $query->pluck('id')->all();
    }

    private function scoreByHistory(array $distinctQueries): array
    {
        if (! $this->indexExists()) {
            return [];
        }

        $engine = $this->engine();
        $scores = [];

        foreach ($distinctQueries as $entry) {
            $expandedQuery = $this->buildExpandedQuery($entry['query']);

            if ($expandedQuery === '') {
                continue;
            }

            try {
                $engine->selectIndex(self::INDEX_NAME);
                $result = $engine->search($expandedQuery, self::TOP_K);
            } catch (\Throwable $e) {
                Log::error('ServingProposalService search failed: '.$e->getMessage());

                continue;
            }

            $daysAgo = max(0, (float) now()->diffInDays($entry['searched_at'], false));
            $weight = 1.0 - 0.5 * min(1, $daysAgo / self::HISTORY_WINDOW_DAYS);

            foreach (($result['docScores'] ?? []) as $id => $score) {
                $scores[$id] = ($scores[$id] ?? 0) + ($score * $weight);
            }
        }

        arsort($scores);

        return $scores;
    }

    public function indexStatus(): array
    {
        $path = $this->storagePath().DIRECTORY_SEPARATOR.self::INDEX_NAME;

        if (! file_exists($path)) {
            return [
                'success' => true,
                'data' => [
                    'exists' => false,
                    'path' => $path,
                    'indexed_docs' => null,
                    'active_servings' => Serving::active()->count(),
                    'message' => 'Index not built yet — proposed servings will fall back to trending',
                ],
            ];
        }

        $indexedDocs = null;
        try {
            $engine = $this->engine();
            $engine->selectIndex(self::INDEX_NAME);
            $indexedDocs = (int) $engine->getValueFromInfoTable('total_documents');
        } catch (\Throwable $e) {
            Log::error('ServingProposalService::indexStatus failed: '.$e->getMessage());
        }

        $activeServings = Serving::active()->count();

        return [
            'success' => true,
            'data' => [
                'exists' => true,
                'path' => $path,
                'size_bytes' => (int) filesize($path),
                'modified_at' => date('Y-m-d H:i:s', filemtime($path)),
                'indexed_docs' => $indexedDocs,
                'active_servings' => $activeServings,
                'up_to_date' => $indexedDocs !== null && $indexedDocs === $activeServings,
            ],
        ];
    }

    private function trendingResponse(int $userId, int $skip, int $take): array
    {
        $requestedIds = ServingRequest::where('requester_id', $userId)->pluck('serving_id')->all();

        $volume = ServingRequest::where('created_at', '>=', now()->subDays(self::TRENDING_WINDOW_DAYS))
            ->selectRaw('serving_id, COUNT(*) as request_count')
            ->groupBy('serving_id');

        $query = Serving::with(['user', 'category', 'unit', 'servingType'])
            ->active()
            ->leftJoinSub($volume, 'volumes', fn ($join) => $join->on('servings.id', '=', 'volumes.serving_id'))
            ->where('servings.user_id', '!=', $userId);

        if ($requestedIds !== []) {
            $query->whereNotIn('servings.id', $requestedIds);
        }

        $servings = $query->orderByDesc(DB::raw('COALESCE(volumes.request_count, 0)'))
            ->orderByDesc('servings.rate')
            ->orderByDesc('servings.created_at')
            ->skip($skip)
            ->take($take)
            ->get();

        return [
            'success' => true,
            'data' => $servings->map(fn ($serving) => $this->toDto($serving, null, 'trending'))->values(),
        ];
    }

    private function distinctQueries(Collection $history): array
    {
        $queries = [];

        foreach ($history as $record) {
            $key = $this->normalizeQueryKey($record->query);

            if ($key === '') {
                continue;
            }

            if (! isset($queries[$key])) {
                $queries[$key] = [
                    'query' => $record->query,
                    'searched_at' => $record->searched_at,
                ];
            }
        }

        return array_slice(array_values($queries), 0, self::MAX_DISTINCT_QUERIES);
    }

    private function normalizeQueryKey(string $query): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $query)));
    }

    private function buildExpandedQuery(string $query): string
    {
        $cleanQuery = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query));

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $cleanQuery) ?: [],
            fn ($token) => $token !== ''
        ));

        if ($tokens === []) {
            return '';
        }

        $normalized = array_map(fn ($token) => $this->normalizeToken($token), $tokens);

        $synonyms = $this->synonymRepository->findByWords($normalized)
            ->mapWithKeys(fn ($row) => [$row->word => $row->synonyms]);

        $terms = [];
        foreach ($tokens as $index => $token) {
            $terms[] = $token;
            $terms[] = $token;

            foreach (($synonyms[$normalized[$index]] ?? []) as $synonym) {
                $terms[] = $synonym;
            }
        }

        return implode(' ', $terms);
    }

    private function normalizeToken(string $token): string
    {
        return preg_match('/\p{Arabic}/u', $token)
            ? BilingualStemmer::normalizeArabic($token)
            : mb_strtolower($token);
    }

    private function engine(): TNTSearch
    {
        $engine = new TNTSearch;
        $engine->loadConfig([
            'storage' => $this->storagePath(),
            'driver' => 'sqlite',
            'database' => ':memory:',
            'stemmer' => BilingualStemmer::class,
        ]);

        return $engine;
    }

    private function storagePath(): string
    {
        return storage_path(self::INDEX_STORAGE);
    }

    private function indexExists(): bool
    {
        return file_exists($this->storagePath().DIRECTORY_SEPARATOR.self::INDEX_NAME);
    }

    private function toDto(Serving $serving, ?float $score, string $reason): array
    {
        return [
            'id' => $serving->id,
            'title' => $serving->title,
            'description' => $serving->description,
            'cost_amount' => $serving->cost_amount,
            'rate' => (float) $serving->rate,
            'image_url' => $serving->image_url,
            'location_lat' => $serving->location_lat,
            'location_lng' => $serving->location_lng,
            'location_address' => $serving->location_address,
            'meeting_type' => $serving->meeting_type,
            'status' => $serving->status,
            'created_at' => $serving->created_at,
            'updated_at' => $serving->updated_at,
            'user_full_name' => $serving->user->full_name ?? null,
            'user_email' => $serving->user->email ?? null,
            'user_id' => $serving->user_id,
            'category_name' => $serving->category->name ?? null,
            'unit_name' => $serving->unit->name ?? null,
            'serving_type_name' => $serving->servingType->name ?? null,
            'requested' => false,
            'isOwner' => false,
            'score' => $score === null ? null : round($score, 4),
            'reason' => $reason,
        ];
    }
}
