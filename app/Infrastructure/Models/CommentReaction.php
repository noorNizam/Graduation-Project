<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReaction extends Model
{
    protected $table = 'comment_reactions';

    public const TYPE_LIKE = 'like';

    public const TYPE_DISLIKE = 'dislike';

    protected $fillable = [
        'comment_id',
        'user_id',
        'type',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
