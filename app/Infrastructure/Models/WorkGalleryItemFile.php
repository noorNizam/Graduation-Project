<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkGalleryItemFile extends Model
{
    protected $table = 'work_gallery_item_files';

    protected $fillable = [
        'work_gallery_item_id',
        'file_url',
        'file_type',
    ];

    public function workGalleryItem(): BelongsTo
    {
        return $this->belongsTo(WorkGalleryItem::class, 'work_gallery_item_id');
    }
}
