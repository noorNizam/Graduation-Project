<?php

namespace Tests\Feature;

use App\Events\NewMessageEvent;
use App\Infrastructure\Casts\EncryptedChatText;
use App\Infrastructure\Models\Chat;
use App\Infrastructure\Models\Message;
use App\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = User::factory()->create(['role' => 'user']);
        $this->receiver = User::factory()->create(['role' => 'user']);
    }

    public function test_message_content_is_stored_encrypted_in_database(): void
    {
        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/chats', [
                'type' => 'personal',
                'receiver_id' => $this->receiver->id,
                'content' => 'Hello encrypted world',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', ['sender_id' => $this->sender->id]);

        $raw = DB::table('messages')->where('sender_id', $this->sender->id)->first();
        $this->assertNotSame('Hello encrypted world', $raw->content);
        $this->assertStringNotContainsString('encrypted', $raw->content);
    }

    public function test_messages_endpoint_returns_decrypted_content(): void
    {
        $chat = $this->createPersonalChat();

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", ['content' => 'Secret plan']);

        $response = $this->actingAs($this->receiver, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $messages = $response->json('data');
        $this->assertSame('Secret plan', end($messages)['content']);
    }

    public function test_chat_list_preview_decrypts_latest_message(): void
    {
        $chat = $this->createPersonalChat();

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", ['content' => 'Preview me']);

        $response = $this->actingAs($this->receiver, 'sanctum')
            ->getJson('/api/chats/personal');

        $response->assertStatus(200);

        $chats = $response->json('data');
        $this->assertNotEmpty($chats);

        $latest = collect($chats)->firstWhere('id', $chat->id);
        $this->assertNotNull($latest);
        $this->assertSame('Preview me', $latest['latest_message']['content'] ?? $latest['latestMessage']['content'] ?? null);
    }

    public function test_new_message_event_broadcasts_decrypted_content(): void
    {
        $chat = $this->createPersonalChat();

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", ['content' => 'Broadcast me']);

        $message = Message::latest('id')->first();
        $event = new NewMessageEvent($message, $chat->id, 'personal', '');

        $this->assertSame('Broadcast me', $event->broadcastWith()['content']);
    }

    public function test_cast_uses_dedicated_key_not_app_key(): void
    {
        $plaintext = 'Dedicated key check';

        $ciphertext = EncryptedChatText::encryptForMigration($plaintext);

        $this->assertNotSame($plaintext, $ciphertext);
        $this->assertSame($plaintext, EncryptedChatText::decryptForMigration($ciphertext));

        $this->expectExceptionMessage('The MAC is invalid.');
        Crypt::decryptString($ciphertext);
    }

    public function test_encrypt_existing_command_encrypts_plaintext_rows(): void
    {
        $chat = $this->createPersonalChat();

        DB::table('messages')->insert([
            'chat_id' => $chat->id,
            'sender_id' => $this->sender->id,
            'content' => 'Legacy plaintext',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('messages:encrypt-existing')->assertSuccessful();

        $stored = DB::table('messages')->where('chat_id', $chat->id)->latest('id')->value('content');
        $this->assertNotSame('Legacy plaintext', $stored);
        $this->assertSame('Legacy plaintext', Message::latest('id')->first()->content);
    }

    public function test_encrypt_existing_command_is_idempotent(): void
    {
        $chat = $this->createPersonalChat();

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", ['content' => 'Already encrypted']);

        $this->artisan('messages:encrypt-existing')->assertSuccessful();
        $this->artisan('messages:encrypt-existing')->assertSuccessful();

        $this->assertSame('Already encrypted', Message::latest('id')->first()->content);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_encrypt_existing_command_fails_without_key(): void
    {
        config(['chat.encryption_key' => null]);

        $this->artisan('messages:encrypt-existing')->assertFailed();
    }

    public function test_legacy_plaintext_row_reads_without_error(): void
    {
        $chat = $this->createPersonalChat();

        DB::table('messages')->insert([
            'chat_id' => $chat->id,
            'sender_id' => $this->sender->id,
            'content' => 'Legacy plaintext',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('Legacy plaintext', Message::latest('id')->first()->content);
    }

    public function test_invalid_key_format_throws_clear_error(): void
    {
        config(['chat.encryption_key' => 'not-base64']);
        EncryptedChatText::resetEncrypter();

        $this->expectExceptionMessage('CHAT_ENCRYPTION_KEY must be a base64-encoded 16-byte (AES-128) or 32-byte (AES-256) key.');
        EncryptedChatText::encryptForMigration('x');
    }

    private function createPersonalChat(): Chat
    {
        $response = $this->actingAs($this->sender, 'sanctum')
            ->postJson('/api/chats', [
                'type' => 'personal',
                'receiver_id' => $this->receiver->id,
                'content' => 'First message',
            ]);

        $response->assertStatus(201);

        return Chat::first();
    }
}
