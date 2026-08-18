<?php

namespace App\Console\Commands;

use App\Infrastructure\Casts\EncryptedChatText;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;

class EncryptExistingMessages extends Command
{
    protected $signature = 'messages:encrypt-existing
                            {--dry-run : Report how many messages would be encrypted without changing data}';

    protected $description = 'Encrypt plaintext message content left by legacy rows (idempotent)';

    public function handle(): int
    {
        $key = config('chat.encryption_key');

        if (blank($key)) {
            $this->error('CHAT_ENCRYPTION_KEY is not set. Add it to the environment first.');

            return Command::FAILURE;
        }

        $total = 0;
        $encrypted = 0;

        DB::table('messages')->select('id', 'content')->orderBy('id')->chunkById(200, function ($rows) use (&$total, &$encrypted) {
            foreach ($rows as $row) {
                $total++;
                $value = $row->content;

                if ($value === null || $this->isEncrypted($value)) {
                    continue;
                }

                if (! $this->option('dry-run')) {
                    DB::table('messages')->where('id', $row->id)->update([
                        'content' => EncryptedChatText::class::encryptForMigration($value),
                    ]);
                }

                $encrypted++;
            }
        });

        $verb = $this->option('dry-run') ? 'would be encrypted' : 'encrypted';
        $this->info("Scanned {$total} messages, {$encrypted} {$verb}.");

        return Command::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            EncryptedChatText::decryptForMigration($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
