<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = [
        'serving_id',
        'user_id',
        'parent_id',
        'depth',
        'content',
    ];

    public function serving(): BelongsTo
    {
        return $this->belongsTo(Serving::class, 'serving_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class, 'comment_id');
    }

    public function scopeForServing($query, int $servingId)
    {
        return $query->where('serving_id', $servingId);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeRepliesTo($query, int $commentId)
    {
        return $query->where('parent_id', $commentId);
    }

    public function scopeMaxDepth($query, int $maxDepth)
    {
        return $query->where('depth', '<=', $maxDepth);
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}
