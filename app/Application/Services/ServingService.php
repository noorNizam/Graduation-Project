<?php

namespace App\Application\Services;

use App\Domain\Repositories\ServingRepositoryInterface;
use App\Domain\Services\ServingServiceInterface;
use App\Jobs\DeleteServingImageJob;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServingService implements ServingServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ServingRepositoryInterface $repository
    ) {}

    public function createPaidServing(array $data, $image = null): array
    {
        $transactionResult = $this->executeWithTransaction(function () use ($data, $image) {
            // Ensure serving type exists for 'paid' if not provided
            if (empty($data['serving_type_id'])) {
                $type = \App\Infrastructure\Models\ServingType::firstOrCreate(['name' => 'paid']);
                $data['serving_type_id'] = $type->id;
            }

            $data['status'] = \App\Infrastructure\Models\Serving::STATUS_ACTIVE;

            // Handle image storage if provided
            if ($image) {
                $userId = $data['user_id'] ?? 'anonymous';
                $path = sprintf('servings/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $image->getClientOriginalExtension());

                $stored = $image->storeAs($path, $filename, 'public');

                $data['image_url'] = Storage::url($stored);
            }

            return $this->repository->create($data);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $serving = $transactionResult['data'];

        return [
            'success' => true,
            'data' => $serving,
        ];
    }

    public function updatePaidServing(int $id, array $data, $image = null): array
    {
        // Load existing serving to capture previous image and verify ownership
        $existingServing = $this->repository->findById($id);
        if (! $existingServing) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $oldImageUrl = $existingServing->image_url;

        // Authorization: ensure the authenticated user owns this serving
        if ($existingServing->user_id !== $data['user_id']) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($id, $data, $image) {
            // Ensure serving type exists for 'paid' if not provided
            if (empty($data['serving_type_id'])) {
                $type = \App\Infrastructure\Models\ServingType::firstOrCreate(['name' => 'paid']);
                $data['serving_type_id'] = $type->id;
            }

            // Handle image storage if provided
            if ($image) {
                $userId = $data['user_id'] ?? 'anonymous';
                $path = sprintf('servings/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $image->getClientOriginalExtension());

                $stored = $image->storeAs($path, $filename, 'public');

                $data['image_url'] = Storage::url($stored);
            }

            return $this->repository->update($id, $data);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $updated = $transactionResult['data'];

        // If image changed, enqueue deletion of the old file
        if (! empty($oldImageUrl) && ! empty($updated->image_url) && $oldImageUrl !== $updated->image_url) {
            try {
                DeleteServingImageJob::dispatch($oldImageUrl);
            } catch (\Throwable $e) {
                // Log and continue; do not fail the user request because of deletion enqueue problems
                \Illuminate\Support\Facades\Log::error('Failed to dispatch DeleteServingImageJob job: '.$e->getMessage());
            }
        }

        return [
            'success' => true,
            'data' => $updated,
        ];
    }

    public function createVoluntaryServing(array $data, $image = null): array
    {
        $transactionResult = $this->executeWithTransaction(function () use ($data, $image) {
            if (empty($data['serving_type_id'])) {
                $type = \App\Infrastructure\Models\ServingType::firstOrCreate(['name' => 'voluntary']);
                $data['serving_type_id'] = $type->id;
            }

            if ($image) {
                $userId = $data['user_id'] ?? 'anonymous';
                $path = sprintf('servings/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $image->getClientOriginalExtension());

                $stored = $image->storeAs($path, $filename, 'public');

                $data['image_url'] = Storage::url($stored);
            }

            $data['status'] = \App\Infrastructure\Models\Serving::STATUS_PENDING;

            return $this->repository->create($data);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Voluntary serving submitted for admin review',
        ];
    }

    public function approveServing(int $servingId): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return ['success' => false, 'message' => 'Serving not found'];
        }

        if (! $serving->isPending()) {
            return ['success' => false, 'message' => 'Serving is not pending'];
        }

        $updated = $this->repository->updateStatus($servingId, \App\Infrastructure\Models\Serving::STATUS_ACTIVE);

        return [
            'success' => true,
            'data' => $updated,
            'message' => 'Serving approved successfully',
        ];
    }

    public function rejectServing(int $servingId): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return ['success' => false, 'message' => 'Serving not found'];
        }

        if (! $serving->isPending()) {
            return ['success' => false, 'message' => 'Serving is not pending'];
        }

        $updated = $this->repository->updateStatus($servingId, \App\Infrastructure\Models\Serving::STATUS_REJECTED);

        return [
            'success' => true,
            'data' => $updated,
            'message' => 'Serving rejected',
        ];
    }

    public function getPendingServings(): array
    {
        $servings = $this->repository->findPendingServings();

        $dto = $servings->map(function ($serving) {
            return [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
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
            ];
        });

        return [
            'success' => true,
            'data' => $dto,
        ];
    }

    public function createComment(int $userId, int $servingId, string $content, ?int $parentId = null): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        if (! $serving->isActive()) {
            return [
                'success' => false,
                'message' => 'Cannot comment on a serving that is not active',
            ];
        }

        $depth = 0;
        if ($parentId !== null) {
            $parent = \App\Infrastructure\Models\Comment::find($parentId);
            if (! $parent) {
                return [
                    'success' => false,
                    'message' => 'Parent comment not found',
                ];
            }
            if ($parent->serving_id !== $servingId) {
                return [
                    'success' => false,
                    'message' => 'Parent comment does not belong to this serving',
                ];
            }
            if ($parent->depth >= 5) {
                return [
                    'success' => false,
                    'message' => 'Maximum reply depth reached',
                ];
            }
            $depth = $parent->depth + 1;
        }

        $transactionResult = $this->executeWithTransaction(function () use ($userId, $servingId, $content, $parentId, $depth) {
            return \App\Infrastructure\Models\Comment::create([
                'serving_id' => $servingId,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'depth' => $depth,
                'content' => $content,
            ]);
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $comment = $transactionResult['data']->load('user');

        return [
            'success' => true,
            'data' => $this->formatComment($comment),
            'message' => 'Comment created successfully',
        ];
    }

    public function getComments(int $servingId): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $comments = \App\Infrastructure\Models\Comment::forServing($servingId)
            ->topLevel()
            ->with(['user'])
            ->withCount('replies')
            ->latest()
            ->get();

        $comments->each(function ($comment) {
            $reactionCounts = $comment->reactions()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            $comment->reaction_counts = [
                'like' => $reactionCounts['like'] ?? 0,
                'dislike' => $reactionCounts['dislike'] ?? 0,
            ];
        });

        return [
            'success' => true,
            'data' => $comments->map(fn ($c) => $this->formatComment($c)),
        ];
    }

    public function getReplies(int $commentId): array
    {
        $parent = \App\Infrastructure\Models\Comment::find($commentId);
        if (! $parent) {
            return [
                'success' => false,
                'message' => 'Comment not found',
            ];
        }

        $replies = \App\Infrastructure\Models\Comment::repliesTo($commentId)
            ->with(['user'])
            ->withCount('replies')
            ->latest()
            ->get();

        $replies->each(function ($reply) {
            $reactionCounts = $reply->reactions()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            $reply->reaction_counts = [
                'like' => $reactionCounts['like'] ?? 0,
                'dislike' => $reactionCounts['dislike'] ?? 0,
            ];
        });

        return [
            'success' => true,
            'data' => $replies->map(fn ($r) => $this->formatComment($r)),
        ];
    }

    public function getServingById(int $id, ?int $userId = null): array
    {
        $serving = $this->repository->findById($id);

        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $serving->load(['user', 'category', 'unit', 'servingType']);

        $requested = false;
        if ($userId !== null) {
            $requested = \App\Infrastructure\Models\ServingRequest::where('serving_id', $id)
                ->where('requester_id', $userId)
                ->where('status', '!=', \App\Infrastructure\Models\ServingRequest::STATUS_COMPLETED)
                ->exists();
        }

        return [
            'success' => true,
            'data' => [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
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
                'category_name' => $serving->category->name ?? null,
                'unit_name' => $serving->unit->name ?? null,
                'user_id' => $serving->user_id,
                'serving_type_name' => $serving->servingType->name ?? null,
                'requested' => $requested,
                'isOwner' => $userId !== null && $serving->user_id === $userId,
            ],
        ];
    }

    public function getNearbyServings(int $userId, float $lat, float $lng, ?int $skip, ?int $take): array
    {
        $radius = 1;
        $latDelta = $radius / 111;
        $lngDelta = $radius / (111 * cos(deg2rad($lat)));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $servings = $this->repository->findNearby(
            $lat,
            $lng,
            $minLat,
            $maxLat,
            $minLng,
            $maxLng,
            $userId,
        );

        $page = $servings->slice($skip ?? 0, $take ?? 20);

        $servingIds = $servings->pluck('id')->toArray();
        $requestedServingIds = [];
        if ($userId !== null && ! empty($servingIds)) {
            $requestedServingIds = \App\Infrastructure\Models\ServingRequest::whereIn('serving_id', $servingIds)
                ->where('requester_id', $userId)
                ->where('status', '!=', \App\Infrastructure\Models\ServingRequest::STATUS_COMPLETED)
                ->pluck('serving_id')
                ->unique()
                ->toArray();
        }

        $dto = $page->map(function ($serving) use ($requestedServingIds, $userId) {
            return [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
                'image_url' => $serving->image_url,
                'location_lat' => $serving->location_lat,
                'location_lng' => $serving->location_lng,
                'location_address' => $serving->location_address,
                'meeting_type' => $serving->meeting_type,
                'status' => $serving->status,
                'distance' => round($serving->distance, 3),
                'created_at' => $serving->created_at,
                'updated_at' => $serving->updated_at,
                'user_full_name' => $serving->user->full_name ?? null,
                'user_id' => $serving->user_id,
                'user_email' => $serving->user->email ?? null,
                'category_name' => $serving->category->name ?? null,
                'unit_name' => $serving->unit->name ?? null,
                'serving_type_name' => $serving->servingType->name ?? null,
                'requested' => in_array($serving->id, $requestedServingIds),
                'isOwner' => $serving->user_id === $userId,
            ];
        })->values();

        return [
            'success' => true,
            'data' => $dto,
        ];
    }

    public function getServings(?int $excludeUserId, ?int $servingTypeId, ?int $paymentUnitId, ?int $categoryId, ?int $skip, ?int $take, ?string $name): array
    {
        $query = \App\Infrastructure\Models\Serving::with(['user', 'category', 'unit', 'servingType'])
            ->active()
            ->latest();

        // Exclude authenticated user's own servings
        if ($excludeUserId !== null) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        // Apply filters only if parameters are not null
        if ($servingTypeId !== null) {
            $query->where('serving_type_id', $servingTypeId);
        }

        if ($paymentUnitId !== null) {
            $query->where('unit_id', $paymentUnitId);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($name !== null) {
            $query->where('title', 'LIKE', "%{$name}%");
        }

        // Apply pagination
        if ($skip !== null) {
            $query->skip($skip);
        }

        if ($take !== null) {
            $query->take($take);
        }

        $servings = $query->get();

        $servingIds = $servings->pluck('id')->toArray();
        $requestedServingIds = [];
        if ($excludeUserId !== null && ! empty($servingIds)) {
            $requestedServingIds = \App\Infrastructure\Models\ServingRequest::whereIn('serving_id', $servingIds)
                ->where('requester_id', $excludeUserId)
                ->where('status', '!=', \App\Infrastructure\Models\ServingRequest::STATUS_COMPLETED)
                ->pluck('serving_id')
                ->unique()
                ->toArray();
        }

        $dto = $servings->map(function ($serving) use ($requestedServingIds, $excludeUserId) {
            return [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
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
                'requested' => in_array($serving->id, $requestedServingIds),
                'isOwner' => $serving->user_id === $excludeUserId,
            ];
        });

        return [
            'success' => true,
            'data' => $dto,
        ];
    }

    public function getMyServings(int $userId, ?int $skip, ?int $take): array
    {
        $query = \App\Infrastructure\Models\Serving::with(['user', 'category', 'unit', 'servingType'])
            ->where('user_id', $userId)
            ->latest();

        if ($skip !== null) {
            $query->skip($skip);
        }

        if ($take !== null) {
            $query->take($take);
        }

        $servings = $query->get();

        $dto = $servings->map(function ($serving) {
            return [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
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
                'isOwner' => true,
            ];
        });

        return [
            'success' => true,
            'data' => $dto,
        ];
    }

    public function reactToComment(int $userId, int $commentId, string $type): array
    {
        $comment = \App\Infrastructure\Models\Comment::find($commentId);
        if (! $comment) {
            return [
                'success' => false,
                'message' => 'Comment not found',
            ];
        }

        $existing = \App\Infrastructure\Models\CommentReaction::where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->type === $type) {
                $existing->delete();
            } else {
                $existing->type = $type;
                $existing->save();
            }
        } else {
            \App\Infrastructure\Models\CommentReaction::create([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'type' => $type,
            ]);
        }

        $reactionCounts = \App\Infrastructure\Models\CommentReaction::where('comment_id', $commentId)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->only(['like', 'dislike'])
            ->toArray();
        $reactionCounts['like'] = $reactionCounts['like'] ?? 0;
        $reactionCounts['dislike'] = $reactionCounts['dislike'] ?? 0;

        return [
            'success' => true,
            'data' => $reactionCounts,
            'message' => 'Reaction updated',
        ];
    }

    public function updateAvailabilitySlots(int $servingId, int $userId, array $slots): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        if ($serving->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($serving, $slots) {
            $serving->availabilitySlots()->delete();

            $created = [];
            foreach ($slots as $slot) {
                $created[] = \App\Infrastructure\Models\ServingAvailabilitySlot::create([
                    'serving_id' => $serving->id,
                    'day_of_week' => $slot['day_of_week'] ?? null,
                    'date' => $slot['date'] ?? null,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'notes' => $slot['notes'] ?? null,
                ]);
            }

            return $created;
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $transactionResult['data'],
            'message' => 'Availability slots updated successfully',
        ];
    }

    public function getAvailabilitySlots(int $servingId): array
    {
        $serving = $this->repository->findById($servingId);
        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        $slots = $serving->availabilitySlots()->get();

        return [
            'success' => true,
            'data' => $slots,
        ];
    }

    public function deactivateServing(int $servingId, int $userId): array
    {
        $serving = $this->repository->findById($servingId);

        if (! $serving) {
            return [
                'success' => false,
                'message' => 'Serving not found',
            ];
        }

        if ($serving->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'Forbidden',
            ];
        }

        $serving = $this->repository->updateStatus($servingId, \App\Infrastructure\Models\Serving::STATUS_INACTIVE);

        return [
            'success' => true,
            'data' => $serving,
            'message' => 'Serving deactivated',
        ];
    }

    public function getDeactivatedServings(int $userId): array
    {
        $servings = \App\Infrastructure\Models\Serving::with(['user', 'category', 'unit', 'servingType'])
            ->where('user_id', $userId)
            ->inactive()
            ->latest()
            ->get();

        $dto = $servings->map(function ($serving) {
            return [
                'id' => $serving->id,
                'title' => $serving->title,
                'description' => $serving->description,
                'cost_amount' => $serving->cost_amount,
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
            ];
        });

        return [
            'success' => true,
            'data' => $dto,
        ];
    }

    private function formatComment($comment): array
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user' => [
                'id' => $comment->user->id,
                'full_name' => $comment->user->full_name,
            ],
            'depth' => $comment->depth,
            'replies_count' => (int) $comment->replies_count,
            'reaction_counts' => [
                'like' => $comment->reaction_counts['like'] ?? 0,
                'dislike' => $comment->reaction_counts['dislike'] ?? 0,
            ],
            'can_add_reply' => $comment->depth < 5,
            'created_at' => $comment->created_at,
            'updated_at' => $comment->updated_at,
        ];
    }
}
