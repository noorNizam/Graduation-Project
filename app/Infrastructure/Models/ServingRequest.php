<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServingRequest extends Model
{
    protected $table = 'serving_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETION_REQUESTED = 'completion_requested';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'serving_id',
        'requester_id',
        'message',
        'status',
        'held_amount',
        'held_at',
        'automatically_cancel_after',
        'accepted_at',
        'completion_requested_at',
        'completed_at',
        'canceled_at',
        'disputed_at',
        'revision_count',
    ];

    protected $casts = [
        'status' => 'string',
        'held_amount' => 'decimal:2',
        'held_at' => 'datetime',
        'automatically_cancel_after' => 'integer',
        'accepted_at' => 'datetime',
        'completion_requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'disputed_at' => 'datetime',
        'revision_count' => 'integer',
    ];

    public function serving(): BelongsTo
    {
        return $this->belongsTo(Serving::class, 'serving_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCompletionRequested($query)
    {
        return $query->where('status', self::STATUS_COMPLETION_REQUESTED);
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', self::STATUS_DISPUTED);
    }

    public function scopeForServing($query, int $servingId)
    {
        return $query->where('serving_id', $servingId);
    }

    public function scopeForRequester($query, int $requesterId)
    {
        return $query->where('requester_id', $requesterId);
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELED;
        $this->canceled_at = now();
        $this->save();
    }

    public function reject(): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isCompletionRequested(): bool
    {
        return $this->status === self::STATUS_COMPLETION_REQUESTED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isDisputed(): bool
    {
        return $this->status === self::STATUS_DISPUTED;
    }

    public function requestRevision(): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->completion_requested_at = null;
        $this->revision_count++;
        $this->save();
    }

    public function dispute(): void
    {
        $this->status = self::STATUS_DISPUTED;
        $this->disputed_at = now();
        $this->save();
    }

    public function requestCompletion(): void
    {
        $this->status = self::STATUS_COMPLETION_REQUESTED;
        $this->completion_requested_at = now();
        $this->save();
    }

    public function confirmCompletion(): void
    {
        $this->complete();
    }

    public static function computeActionFlags(self $request, int $userId): array
    {
        $isOwner = $request->relationLoaded('serving') && $request->serving && $request->serving->user_id === $userId;
        $isRequester = $request->requester_id === $userId;
        $status = $request->status;

        return [
            'canAccept' => $status === self::STATUS_PENDING && $isOwner,
            'canReject' => $status === self::STATUS_PENDING && $isOwner,
            'canDelete' => $status === self::STATUS_PENDING && $isRequester,
            'canRequestCompletion' => $status === self::STATUS_ACCEPTED && $isOwner,
            'canConfirmCompletion' => $status === self::STATUS_COMPLETION_REQUESTED && $isRequester,
            'canRequestRevision' => $status === self::STATUS_COMPLETION_REQUESTED && $isRequester && $request->revision_count < 2,
            'canDispute' => $status === self::STATUS_COMPLETION_REQUESTED && $isRequester,
        ];
    }
}
