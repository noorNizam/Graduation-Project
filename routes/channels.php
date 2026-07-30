<?php

use App\Infrastructure\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, int $chatId) {
    $chat = Chat::find($chatId);

    if (! $chat) {
        return false;
    }

    if (! $chat->users()->where('users.id', $user->id)->exists()) {
        return false;
    }

    return true;
});

Broadcast::channel('presence-chat.{chatId}', function ($user, int $chatId) {
    $chat = Chat::find($chatId);

    if (! $chat) {
        return false;
    }

    if (! $chat->users()->where('users.id', $user->id)->exists()) {
        return false;
    }

    return [
        'id' => $user->id,
        'full_name' => $user->full_name,
        'profile_picture' => $user->profile_picture,
    ];
});
