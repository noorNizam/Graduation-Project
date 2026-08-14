<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopPerformer extends Model
{
    protected $table = 'top_performers';

    protected $fillable = [
        'user_id',
        'serving_type_id',
        'rank',
        'date',
    ];

    protected $casts = [
        'rank' => 'integer',
        'date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function servingType(): BelongsTo
    {
        return $this->belongsTo(ServingType::class, 'serving_type_id');
    }
}
