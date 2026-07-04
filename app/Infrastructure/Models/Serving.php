<?php

namespace App\Infrastructure\Models;

use App\Models\ServingCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serving extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'servings';

    protected $fillable = [
        'title',
        'description',
        'user_id',
        'serving_type_id',
        'category_id',
        'cost_amount',
        'unit_id',
        'location_lat',
        'location_lng',
        'location_address',
        'image_url',
        'meeting_type',
        'status',
    ];

    /**
     * Category relation
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServingCategory::class, 'category_id');
    }

    /**
     * Payment unit relation
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(PaymentUnit::class, 'unit_id');
    }

    /**
     * Availability slots for the serving.
     */
    public function availabilitySlots()
    {
        return $this->hasMany(ServingAvailabilitySlot::class, 'serving_id');
    }

    /**
     * Owner (user) relation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Serving type relation
     */
    public function servingType(): BelongsTo
    {
        return $this->belongsTo(ServingType::class, 'serving_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServingRequest::class, 'serving_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'serving_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }
}
