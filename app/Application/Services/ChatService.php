<?php

namespace App\Application\Services;

use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Services\ChatServiceInterface;
use App\Infrastructure\Models\Chat;
use App\Infrastructure\Models\Message;
use App\Infrastructure\Models\MessageRecipient;
use App\Infrastructure\Models\User;
use App\Traits\HandlesDatabaseTransactions;

class ChatService implements ChatServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private ChatRepositoryInterface $chatRepository
    ) {}

    public function createChat(int $userId, array $data): array
    {
        if ($data['type'] === 'personal') {
            return $this->createPersonalChat($userId, $data);
        }

        if ($data['type'] === 'group') {
            return $this->createGroupChat($userId, $data);
        }

        return ['success' => false, 'message' => 'Invalid chat creation data'];
    }

    private function createPersonalChat(int $userId, array $data): array
    {
        $receiverId = $data['receiver_id'];

        if ($userId === $receiverId) {
            return ['success' => false, 'message' => 'Cannot create a chat with yourself'];
        }

        if (! User::where('id', $receiverId)->exists()) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $receiverId, $data) {
            $chat = $this->chatRepository->findOrCreateBetween($userId, $receiverId);

            $message = null;
            if (! empty($data['content'])) {
                $message = Message::create([
                    'chat_id' => $chat->id,
                    'sender_id' => $userId,
                    'content' => $data['content'],
                ]);

                $message->recipientStatus()->create(['user_id' => $receiverId]);

                $this->chatRepository->updateLastMessageAt($chat->id);
                $message->load('sender:id,full_name,profile_picture');
            }

            return [
                'chat' => $chat->load('users:id,full_name,profile_picture'),
                'message' => $message,
            ];
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Chat created',
        ];
    }

    private function createGroupChat(int $userId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Group name is required'];
        }

        $memberIds = array_unique($data['member_ids'] ?? []);
        if (count($memberIds) < 2) {
            return ['success' => false, 'message' => 'A group must have at least 2 members'];
        }
        if (count($memberIds) > 49) {
            return ['success' => false, 'message' => 'Maximum 50 members per group'];
        }
        if (in_array($userId, $memberIds)) {
            return ['success' => false, 'message' => 'You are already included as creator'];
        }

        $existingCount = User::whereIn('id', $memberIds)->count();
        if ($existingCount !== count($memberIds)) {
            return ['success' => false, 'message' => 'One or more members not found'];
        }

        $result = $this->executeWithTransaction(function () use ($userId, $data, $memberIds) {
            $chat = Chat::create([
                'type' => 'group',
                'name' => $data['name'],
                'created_by' => $userId,
            ]);

            $allMembers = array_merge([$userId], $memberIds);
            $now = now();
            $chat->users()->attach(
                collect($allMembers)->mapWithKeys(fn($id) => [$id => ['joined_at' => $now]])->all()
            );

            return ['chat' => $chat->load('users:id,full_name,profile_picture')];
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Group created',
        ];
    }

    public function sendMessage(int $chatId, int $senderId, string $content): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if (! $this->chatRepository->isMember($chatId, $senderId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        $result = $this->executeWithTransaction(function () use ($chatId, $senderId, $content, $chat) {
            $message = Message::create([
                'chat_id' => $chatId,
                'sender_id' => $senderId,
                'content' => $content,
            ]);

            $recipientIds = $chat->users()
                ->where('users.id', '!=', $senderId)
                ->pluck('users.id');

            $message->recipientStatus()->createMany(
                $recipientIds->map(fn($id) => ['user_id' => $id])->all()
            );

            $this->chatRepository->updateLastMessageAt($chatId);

            return ['message' => $message->load('sender:id,full_name,profile_picture')];
        });

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'data' => $result['data'],
            'message' => 'Message sent successfully',
        ];
    }

    public function getChats(int $userId): array
    {
        $chats = $this->chatRepository->findByUserId($userId);

        $chats->each(function ($chat) use ($userId) {
            if ($chat->type === 'personal') {
                $otherUser = $chat->users->firstWhere('id', '!=', $userId);
                $chat->setRelation('otherUser', $otherUser);
            }

            $chat->unread_count = Message::where('chat_id', $chat->id)
                ->where('sender_id', '!=', $userId)
                ->whereHas('recipientStatus', fn($q) => $q->where('user_id', $userId)->whereNull('read_at'))
                ->count();
        });

        return [
            'success' => true,
            'data' => $chats,
        ];
    }

    public function getMessages(int $chatId, int $userId, ?int $afterId = null, ?int $beforeId = null): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        MessageRecipient::whereIn('message_id',
            Message::where('chat_id', $chatId)->select('id')
        )
            ->where('user_id', $userId)
            ->whereNull('received_at')
            ->update(['received_at' => now()]);

        $load = [
            'sender:id,full_name,profile_picture',
            'recipientStatus' => fn($q) => $q->where('user_id', $userId),
        ];

        $counts = [
            'recipientStatus as total_recipients',
            'recipientStatus as received_count' => fn($q) => $q->whereNotNull('received_at'),
            'recipientStatus as read_count' => fn($q) => $q->whereNotNull('read_at'),
        ];

        if ($afterId !== null) {
            $messages = $chat->messages()
                ->with($load)
                ->withCount($counts)
                ->where('id', '>', $afterId)
                ->orderBy('id', 'asc')
                ->get();
        } elseif ($beforeId !== null) {
            $messages = $chat->messages()
                ->with($load)
                ->withCount($counts)
                ->where('id', '<', $beforeId)
                ->orderBy('id', 'desc')
                ->take(50)
                ->get()
                ->reverse()
                ->values();
        } else {
            $messages = $chat->messages()
                ->with($load)
                ->withCount($counts)
                ->orderBy('id', 'desc')
                ->take(50)
                ->get()
                ->reverse()
                ->values();
        }

        $messages->each(function ($message) use ($userId) {
            $myStatus = $message->recipientStatus->first();
            $message->my_received_at = $myStatus?->received_at;
            $message->my_read_at = $myStatus?->read_at;
            unset($message->recipientStatus);
        });

        $hasMore = false;
        if ($beforeId !== null || ($afterId === null && $beforeId === null)) {
            $lastMsg = $messages->first();
            if ($lastMsg) {
                $hasMore = $chat->messages()->where('id', '<', $lastMsg->id)->exists();
            }
        }

        return [
            'success' => true,
            'data' => $messages,
            'has_more' => $hasMore,
        ];
    }

    public function markAsRead(int $chatId, int $userId): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        $messageIds = Message::where('chat_id', $chatId)->pluck('id');

        MessageRecipient::whereIn('message_id', $messageIds)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['success' => true, 'message' => 'Messages marked as read'];
    }

    public function markAsReceived(int $chatId, int $userId): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        $messageIds = Message::where('chat_id', $chatId)->pluck('id');

        MessageRecipient::whereIn('message_id', $messageIds)
            ->where('user_id', $userId)
            ->whereNull('received_at')
            ->update(['received_at' => now()]);

        return ['success' => true, 'message' => 'Messages marked as received'];
    }

    public function getMembers(int $chatId, int $userId): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        $members = $this->chatRepository->getMembers($chatId);

        return [
            'success' => true,
            'data' => $members,
        ];
    }

    public function addMembers(int $chatId, int $userId, array $memberIds): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if ($chat->type !== 'group') {
            return ['success' => false, 'message' => 'Can only add members to group chats'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a participant of this chat'];
        }

        $currentCount = $chat->users()->count();
        if ($currentCount + count($memberIds) > 50) {
            return ['success' => false, 'message' => 'Maximum 50 members per group'];
        }

        $this->chatRepository->addMembers($chatId, $memberIds);

        return ['success' => true, 'message' => 'Members added'];
    }

    public function removeMember(int $chatId, int $userId, int $targetUserId): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if ($chat->type !== 'group') {
            return ['success' => false, 'message' => 'Can only remove members from group chats'];
        }

        if ($chat->created_by !== $userId) {
            return ['success' => false, 'message' => 'Only the creator can remove members'];
        }

        if ($targetUserId === $userId) {
            return ['success' => false, 'message' => 'Use leave instead'];
        }

        $this->chatRepository->removeMember($chatId, $targetUserId);

        return ['success' => true, 'message' => 'Member removed'];
    }

    public function leaveGroup(int $chatId, int $userId): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if ($chat->type !== 'group') {
            return ['success' => false, 'message' => 'Not a group chat'];
        }

        if (! $this->chatRepository->isMember($chatId, $userId)) {
            return ['success' => false, 'message' => 'You are not a member of this group'];
        }

        $result = $this->executeWithTransaction(function () use ($chatId, $userId, $chat) {
            $this->chatRepository->removeMember($chatId, $userId);

            if ($chat->users()->count() === 0) {
                $chat->delete();
            }

            return true;
        });

        if (! $result['success']) {
            return $result;
        }

        return ['success' => true, 'message' => 'Left group'];
    }

    public function updateGroup(int $chatId, int $userId, array $data): array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return ['success' => false, 'message' => 'Chat not found'];
        }

        if ($chat->type !== 'group') {
            return ['success' => false, 'message' => 'Not a group chat'];
        }

        if ($chat->created_by !== $userId) {
            return ['success' => false, 'message' => 'Only the creator can update the group'];
        }

        if (isset($data['name'])) {
            $chat->update(['name' => $data['name']]);
        }

        return [
            'success' => true,
            'message' => 'Group updated',
            'data' => $chat,
        ];
    }
}
