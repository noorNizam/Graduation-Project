<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $chatId,
        public string $type,
        public array $data,
        public string $chatType = 'personal',
    ) {}

    public function broadcastOn(): array
    {
        if ($this->chatType === 'group') {
            return [new PresenceChannel("chat.{$this->chatId}")];
        }

        return [new PrivateChannel("chat.{$this->chatId}")];
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chatId,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
