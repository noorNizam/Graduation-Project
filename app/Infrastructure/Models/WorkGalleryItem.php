<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkGalleryItem extends Model
{
    protected $table = 'work_gallery_items';

    protected $fillable = [
        'title',
        'description',
        'date_of_achievement',
        'user_id',
    ];

    protected $casts = [
        'date_of_achievement' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkGalleryItemFile::class, 'work_gallery_item_id');
    }
}
