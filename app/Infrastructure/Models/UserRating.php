<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRating extends Model
{
    protected $table = 'user_ratings';

    protected $fillable = [
        'user_id',
        'serving_id',
        'rating',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function serving(): BelongsTo
    {
        return $this->belongsTo(Serving::class, 'serving_id');
    }
}
