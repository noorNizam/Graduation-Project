<?php

namespace App\Events;

use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessagesDeliveredEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $chatId,
        public int $userId,
        public array $messageIds,
        public Carbon $receivedAt,
        public string $chatType = 'personal',
    ) {}

    public function broadcastOn(): array
    {
        if ($this->chatType === 'group') {
            return [new PresenceChannel("presence-chat.{$this->chatId}")];
        }

        return [new PrivateChannel("chat.{$this->chatId}")];
    }

    public function broadcastAs(): string
    {
        return 'messages.delivered';
    }

    public function broadcastWith(): array
    {
        return [
            'message_ids' => $this->messageIds,
            'user_id' => $this->userId,
            'received_at' => $this->receivedAt->toISOString(),
        ];
    }
}
