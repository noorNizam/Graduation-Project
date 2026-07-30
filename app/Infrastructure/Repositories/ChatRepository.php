<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ChatRepositoryInterface;
use App\Infrastructure\Models\Chat;
use Illuminate\Support\Collection;

class ChatRepository implements ChatRepositoryInterface
{
    public function findOrCreateBetween(int $userIdOne, int $userIdTwo): Chat
    {
        $chat = Chat::where('type', 'personal')
            ->whereHas('users', fn ($q) => $q->where('users.id', $userIdOne))
            ->whereHas('users', fn ($q) => $q->where('users.id', $userIdTwo))
            ->whereHas('users', null, '=', 2)
            ->first();

        if ($chat) {
            return $chat;
        }

        $chat = Chat::create(['type' => 'personal']);
        $chat->users()->attach([$userIdOne, $userIdTwo], ['joined_at' => now()]);

        return $chat;
    }

    public function findById(int $id): ?Chat
    {
        return Chat::find($id);
    }

    public function findByUserId(int $userId): Collection
    {
        return Chat::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with(['users:id,full_name,profile_picture', 'latestMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function findByUserIdAndType(int $userId, string $type): Collection
    {
        return Chat::where('type', $type)
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with(['users:id,full_name,profile_picture', 'latestMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function updateLastMessageAt(int $chatId): void
    {
        Chat::where('id', $chatId)->update(['last_message_at' => now()]);
    }

    public function getMembers(int $chatId): Collection
    {
        return Chat::findOrFail($chatId)->users()->get();
    }

    public function addMembers(int $chatId, array $userIds): void
    {
        $chat = Chat::findOrFail($chatId);
        $existing = $chat->users()->pluck('users.id')->all();
        $new = array_diff($userIds, $existing);

        if (! empty($new)) {
            $now = now();
            $chat->users()->attach(
                collect($new)->mapWithKeys(fn ($id) => [$id => ['joined_at' => $now]])->all()
            );
        }
    }

    public function removeMember(int $chatId, int $userId): void
    {
        Chat::findOrFail($chatId)->users()->detach($userId);
    }

    public function isMember(int $chatId, int $userId): bool
    {
        return Chat::where('id', $chatId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->exists();
    }
}
