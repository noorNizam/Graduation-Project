<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Infrastructure\Models\User;


class PenaltyModel extends Model
{
    protected $table = 'penalties';

    protected $fillable = [
        'user_id',
        'complaint_id',
        'type',
        'hours_deducted',
        'suspended_days',
        'reason',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(ComplaintModel::class, 'complaint_id');
    }

    // دوال مساعدة
    public function isWarning(): bool
    {
        return $this->type === 'warning';
    }

    public function isDeductHours(): bool
    {
        return $this->type === 'deduct_hours';
    }

    public function isSuspend(): bool
    {
        return $this->type === 'suspend';
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isBan(): bool
    {
        return $this->type === 'ban';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}