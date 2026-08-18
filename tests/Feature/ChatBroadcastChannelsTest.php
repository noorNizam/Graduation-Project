<?php

namespace Tests\Feature;

use App\Events\ChatMessagesDeliveredEvent;
use App\Events\ChatMessagesReadEvent;
use App\Events\ChatUpdatedEvent;
use App\Events\NewMessageEvent;
use App\Events\UserTypingEvent;
use App\Infrastructure\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ChatBroadcastChannelsTest extends TestCase
{
    public function test_new_message_event_uses_correct_wire_channels(): void
    {
        $message = new Message(['id' => 1, 'chat_id' => 5, 'sender_id' => 2]);

        $group = new NewMessageEvent($message, 5, 'group', 'Team');
        $this->assertInstanceOf(PresenceChannel::class, $group->broadcastOn()[0]);
        $this->assertSame('presence-chat.5', $group->broadcastOn()[0]->name);
        $this->assertSame('message.new', $group->broadcastAs());

        $personal = new NewMessageEvent($message, 5, 'personal');
        $this->assertInstanceOf(PrivateChannel::class, $personal->broadcastOn()[0]);
        $this->assertSame('private-chat.5', $personal->broadcastOn()[0]->name);
        $this->assertSame('message.new', $personal->broadcastAs());
    }

    public function test_chat_updated_event_uses_correct_wire_channels(): void
    {
        $group = new ChatUpdatedEvent(5, 'group_created', [], 'group');
        $this->assertInstanceOf(PresenceChannel::class, $group->broadcastOn()[0]);
        $this->assertSame('presence-chat.5', $group->broadcastOn()[0]->name);
        $this->assertSame('chat.updated', $group->broadcastAs());

        $personal = new ChatUpdatedEvent(5, 'group_created', []);
        $this->assertInstanceOf(PrivateChannel::class, $personal->broadcastOn()[0]);
        $this->assertSame('private-chat.5', $personal->broadcastOn()[0]->name);
        $this->assertSame('chat.updated', $personal->broadcastAs());
    }

    public function test_messages_delivered_event_uses_correct_wire_channels(): void
    {
        $group = new ChatMessagesDeliveredEvent(5, 3, [1, 2], now(), 'group');
        $this->assertInstanceOf(PresenceChannel::class, $group->broadcastOn()[0]);
        $this->assertSame('presence-chat.5', $group->broadcastOn()[0]->name);
        $this->assertSame('messages.delivered', $group->broadcastAs());

        $personal = new ChatMessagesDeliveredEvent(5, 3, [1, 2], now());
        $this->assertInstanceOf(PrivateChannel::class, $personal->broadcastOn()[0]);
        $this->assertSame('private-chat.5', $personal->broadcastOn()[0]->name);
        $this->assertSame('messages.delivered', $personal->broadcastAs());
    }

    public function test_messages_read_event_uses_correct_wire_channels(): void
    {
        $group = new ChatMessagesReadEvent(5, 3, [1, 2], now(), 'group');
        $this->assertInstanceOf(PresenceChannel::class, $group->broadcastOn()[0]);
        $this->assertSame('presence-chat.5', $group->broadcastOn()[0]->name);
        $this->assertSame('messages.read', $group->broadcastAs());

        $personal = new ChatMessagesReadEvent(5, 3, [1, 2], now());
        $this->assertInstanceOf(PrivateChannel::class, $personal->broadcastOn()[0]);
        $this->assertSame('private-chat.5', $personal->broadcastOn()[0]->name);
        $this->assertSame('messages.read', $personal->broadcastAs());
    }

    public function test_user_typing_event_uses_correct_wire_channels(): void
    {
        $group = new UserTypingEvent(5, 2, 'User 1', true, 'group');
        $this->assertInstanceOf(PresenceChannel::class, $group->broadcastOn()[0]);
        $this->assertSame('presence-chat.5', $group->broadcastOn()[0]->name);
        $this->assertSame('user.typing', $group->broadcastAs());

        $personal = new UserTypingEvent(5, 2, 'User 1', true);
        $this->assertInstanceOf(PrivateChannel::class, $personal->broadcastOn()[0]);
        $this->assertSame('private-chat.5', $personal->broadcastOn()[0]->name);
        $this->assertSame('user.typing', $personal->broadcastAs());
    }
}
