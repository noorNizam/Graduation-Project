<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintDocument extends Model
{
    protected $table = 'complaint_documents';

    protected $fillable = [
        'complaint_id',
        'uploader_id',
        'uploader_role',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
    ];

    protected $appends = ['url'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(ComplaintModel::class, 'complaint_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->stored_path);
    }
}
