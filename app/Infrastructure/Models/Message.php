<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Casts\EncryptedChatText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'content',
    ];

    protected $casts = [
        'content' => EncryptedChatText::class,
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipientStatus(): HasMany
    {
        return $this->hasMany(MessageRecipient::class, 'message_id');
    }
}
