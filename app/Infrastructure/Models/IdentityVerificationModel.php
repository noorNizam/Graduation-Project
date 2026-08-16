<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerificationModel extends Model
{
    protected $table = 'identity_verifications';

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'verification_data',
        'verified_at',
    ];

    protected $casts = [
        'verification_data' => 'array',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}