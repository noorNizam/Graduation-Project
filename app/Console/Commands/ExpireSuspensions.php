<?php

namespace App\Console\Commands;

use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSuspensions extends Command
{
    protected $signature = 'penalties:expire-suspensions';

    protected $description = 'Deactivate expired suspensions and reactivate the suspended users';

    public function handle(): int
    {
        $expired = PenaltyModel::where('type', 'suspend')
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $penalty) {
            DB::transaction(function () use ($penalty) {
                $penalty->is_active = false;
                $penalty->save();

                $user = User::find($penalty->user_id);

                // Reactivate only when this suspension was what blocked the
                // account and nothing else still blocks it. Admin blocks
                // (block_source = 'admin') are never lifted here.
                $stillBlocked = PenaltyModel::where('user_id', $penalty->user_id)
                    ->where('is_active', true)
                    ->whereIn('type', ['suspend', 'ban'])
                    ->exists();

                if ($user
                    && ! $stillBlocked
                    && $user->block_source === User::BLOCK_SOURCE_SUSPENSION) {
                    $user->update([
                        'is_active' => true,
                        'block_source' => null,
                    ]);
                }
            });

            $this->info("Expired suspension #{$penalty->id} for user #{$penalty->user_id}.");
        }

        $this->info("Processed {$expired->count()} expired suspensions.");

        return Command::SUCCESS;
    }
}
