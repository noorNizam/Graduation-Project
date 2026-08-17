<?php

namespace App\Events;

use App\Infrastructure\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $chatId,
        public string $chatType = 'personal',
        public string $chatName = '',
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
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'chat_type' => $this->chatType,
            'chat_name' => $this->chatName,
            'sender_id' => $this->message->sender_id,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at->toISOString(),
            'sender' => [
                'id' => $this->message->sender->id,
                'full_name' => $this->message->sender->full_name,
                'profile_picture' => $this->message->sender->profile_picture,
            ],
        ];
    }
}
