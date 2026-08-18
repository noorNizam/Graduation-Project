<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardModel extends Model
{
    protected $table = 'rewards';

    protected $fillable = [
        'user_id',
        'hours_added',
        'type',
        'threshold',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
