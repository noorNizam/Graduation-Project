<?php

namespace App\Infrastructure\Models;

use App\Models\ServingCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serving extends Model
{
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
}
