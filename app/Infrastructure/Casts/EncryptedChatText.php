<?php

namespace App\Infrastructure\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EncryptedChatText implements CastsAttributes
{
    private static ?EncrypterContract $encrypter = null;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return static::encrypter()->decryptString($value);
        } catch (DecryptException $e) {
            Log::warning('Failed to decrypt chat message content, returning raw value', [
                'message_id' => $model->getKey(),
                'exception' => $e->getMessage(),
            ]);

            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return static::encrypter()->encryptString($value);
    }

    private static function encrypter(): EncrypterContract
    {
        if (static::$encrypter !== null) {
            return static::$encrypter;
        }

        $key = config('chat.encryption_key');

        if (blank($key)) {
            throw new RuntimeException(
                'CHAT_ENCRYPTION_KEY is not set. Chat message encryption requires a base64-encoded 32-byte key.'
            );
        }

        $decoded = base64_decode(preg_replace('/^base64:/', '', $key), true);

        if ($decoded === false || ! in_array(strlen($decoded), [16, 32], true)) {
            throw new RuntimeException(
                'CHAT_ENCRYPTION_KEY must be a base64-encoded 16-byte (AES-128) or 32-byte (AES-256) key.'
            );
        }

        return static::$encrypter = new Encrypter($decoded, config('app.cipher', 'AES-256-CBC'));
    }

    public static function resetEncrypter(): void
    {
        static::$encrypter = null;
    }

    public static function encryptForMigration(string $value): string
    {
        return static::encrypter()->encryptString($value);
    }

    public static function decryptForMigration(string $value): string
    {
        return static::encrypter()->decryptString($value);
    }
}
