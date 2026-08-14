<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\TopPerformerRepositoryInterface;
use App\Domain\Services\TopPerformerServiceInterface;
use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\TopPerformer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class TopPerformerService implements TopPerformerServiceInterface
{
    private const DEFAULT_RATING = 2.5;

    private const MAX_RANKED_USERS = 10;

    public function __construct(
        private TopPerformerRepositoryInterface $topPerformerRepository,
        private ServingRepositoryInterface $servingRepository,
        private ServingRequestRepositoryInterface $requestRepository
    ) {}

    public function getTopPerformers(int $servingTypeId, ?string $month = null): array
    {
        $month = $month ?? now()->subMonth()->format('Y-m');

        $cached = Cache::get(self::cacheKey($month));

        if (is_array($cached)) {
            $performers = collect($cached)->where('serving_type_id', $servingTypeId)->values();
        } else {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEndExclusive = $monthStart->copy()->addMonth();

            $performers = collect(
                $this->buildMonthPayload($monthStart->toDateTimeString(), $monthEndExclusive->toDateTimeString())
            )->where('serving_type_id', $servingTypeId)->values();
        }

        return [
            'success' => true,
            'data' => $performers,
        ];
    }

    public function calculateAndStore(CarbonInterface $executionAt): array
    {
        $monthStart = $executionAt->copy()->startOfMonth();
        $monthEndExclusive = $monthStart->copy()->addMonth();

        $paidType = ServingType::where('name', 'paid')->first();
        $hourUnit = PaymentUnit::where('name', PaymentUnit::NAME_HOUR)->first();

        if (! $paidType || ! $hourUnit) {
            return [
                'success' => false,
                'message' => 'Paid serving type or Hour payment unit not found',
            ];
        }

        $servings = $this->servingRepository->findByTypeAndUnit($paidType->id, $hourUnit->id);

        if ($servings->isEmpty()) {
            $this->cacheMonth($monthStart, $monthEndExclusive);

            return [
                'success' => true,
                'data' => [
                    'ranked_users' => [],
                    'month' => $monthStart->format('Y-m'),
                ],
            ];
        }

        $ratings = $servings->groupBy('user_id')
            ->map(fn ($group) => (float) $group->avg('rate'));

        $completed = $this->requestRepository->findCompletedByServingIds(
            $servings->pluck('id')->toArray(),
            $monthStart->toDateTimeString(),
            $monthEndExclusive->toDateTimeString()
        );

        $hoursByUser = [];
        foreach ($completed as $request) {
            $serving = $request->serving;
            if ($serving) {
                $hoursByUser[$serving->user_id] = ($hoursByUser[$serving->user_id] ?? 0) + (float) $serving->cost_amount;
            }
        }

        $scores = [];
        foreach ($hoursByUser as $userId => $hours) {
            $rating = $ratings[$userId] ?? 0;
            if ($rating <= 0) {
                $rating = self::DEFAULT_RATING;
            }

            $scores[$userId] = $hours * $rating;
        }

        arsort($scores);

        $entries = [];
        $rank = 1;
        foreach (array_slice($scores, 0, self::MAX_RANKED_USERS, true) as $userId => $score) {
            $entries[] = [
                'user_id' => (int) $userId,
                'serving_type_id' => $paidType->id,
                'rank' => $rank,
                'date' => $executionAt->toDateTimeString(),
            ];
            $rank++;
        }

        $this->topPerformerRepository->insertMany($entries);

        $this->cacheMonth($monthStart, $monthEndExclusive);

        return [
            'success' => true,
            'data' => [
                'ranked_users' => $entries,
                'month' => $monthStart->format('Y-m'),
            ],
        ];
    }

    private function cacheMonth(CarbonInterface $monthStart, CarbonInterface $monthEndExclusive): void
    {
        Cache::put(
            self::cacheKey($monthStart->format('Y-m')),
            $this->buildMonthPayload($monthStart->toDateTimeString(), $monthEndExclusive->toDateTimeString()),
            now()->addMonth()
        );
    }

    private function buildMonthPayload(string $from, string $to): array
    {
        return $this->topPerformerRepository
            ->findByMonthRange($from, $to)
            ->map(fn (TopPerformer $performer) => $this->formatPerformer($performer))
            ->toArray();
    }

    private function formatPerformer(TopPerformer $performer): array
    {
        return [
            'rank' => $performer->rank,
            'user_id' => $performer->user_id,
            'full_name' => $performer->user->full_name ?? null,
            'profile_picture' => $performer->user->profile_picture ?? null,
            'serving_type_id' => $performer->serving_type_id,
            'date' => $performer->date?->toISOString(),
        ];
    }

    private static function cacheKey(string $month): string
    {
        return 'top_performers:'.$month;
    }
}
