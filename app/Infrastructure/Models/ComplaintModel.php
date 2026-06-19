<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintModel extends Model
{
    protected $table = 'complaints';

    protected $fillable = [
        'serving_id',
        'complainant_id',
        'accused_user_id',
        'reason',
        'description',
        'status',
        'admin_note',
        'attachment_path',   // 🔥 جديد
        'attachment_name',   // 🔥 جديد
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected $appends = ['attachment_url'];
    // العلاقات
    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_id');
    }

    public function accusedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accused_user_id');
    }

    // دوال مساعدة
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    public function getAttachmentUrlAttribute()
{
    if ($this->attachment_path) {
        return asset('storage/' . $this->attachment_path);
    }
    return null;
}
}