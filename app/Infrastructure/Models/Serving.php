<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ServingCategory;
use App\Infrastructure\Models\ServingType;

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
}
