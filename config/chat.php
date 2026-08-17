<?php

use Illuminate\Support\Env;

return [
    // Dedicated key for encrypting chat message content at rest (AES-256-CBC).
    // Must be a base64-encoded 32-byte key. Never commit the real value;
    // set it in the production environment only.
    // Outside production, falls back to APP_KEY so local and Docker dev work
    // out of the box; production fails closed when the key is missing.
    // NOTE: cannot use app()->environment() here — the container 'env'
    // binding is not set until after config files load.
    'encryption_key' => env('CHAT_ENCRYPTION_KEY') ?: (Env::get('APP_ENV') === 'production' ? null : config('app.key')),
];
