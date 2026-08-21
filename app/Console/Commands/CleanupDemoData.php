<?php

namespace App\Console\Commands;

use App\Infrastructure\Models\Chat;
use App\Infrastructure\Models\ComplaintDocument;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\IdentityVerificationModel;
use App\Infrastructure\Models\Message;
use App\Infrastructure\Models\MessageRecipient;
use App\Infrastructure\Models\PenaltyModel;
use App\Infrastructure\Models\RewardModel;
use App\Infrastructure\Models\Serving;
use App\Infrastructure\Models\ServingRequest;
use App\Infrastructure\Models\TopPerformer;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\UserSearchHistory;
use App\Infrastructure\Models\WalletModel;
use App\Infrastructure\Models\WorkGalleryItem;
use App\Infrastructure\Models\WorkGalleryItemFile;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupDemoData extends Command
{
    protected $signature = 'demo:cleanup {--force : Recover the demo dataset from the database when the manifest file is missing}';

    protected $description = 'Remove all data created by the DemoDataSeeder';

    public function handle(): int
    {
        $marker = User::where('email', DemoDataSeeder::markerEmail())->first();

        if (! $marker) {
            $this->info('No demo data found (marker user missing). Nothing to clean.');

            return self::SUCCESS;
        }

        $manifestPath = 'manifests/demo-seed-manifest.json';

        if (! Storage::disk('local')->exists($manifestPath)) {
            if (! $this->option('force')) {
                $this->error('Demo seed manifest not found at storage/app/manifests/demo-seed-manifest.json. Aborting to protect real data.');

                return self::FAILURE;
            }

            $this->warn('Manifest missing — recovering the demo dataset from the database.');
            $manifest = $this->recoverManifest();
        } else {
            $manifest = json_decode(Storage::disk('local')->get($manifestPath), true);
        }

        MessageRecipient::whereIn('id', $manifest['message_recipients'] ?? [])->delete();
        Message::whereIn('id', $manifest['messages'] ?? [])->delete();
        Chat::whereIn('id', $manifest['chats'] ?? [])->delete();

        WorkGalleryItemFile::whereIn('id', $manifest['gallery_files'] ?? [])->delete();
        WorkGalleryItem::whereIn('id', $manifest['gallery_items'] ?? [])->delete();

        UserSearchHistory::whereIn('id', $manifest['search_history'] ?? [])->delete();
        IdentityVerificationModel::whereIn('id', $manifest['identity_verifications'] ?? [])->delete();

        $complaintIds = $manifest['complaints'] ?? [];
        ComplaintDocument::whereIn('id', $manifest['complaint_documents'] ?? [])->delete();
        PenaltyModel::whereIn('complaint_id', $complaintIds)->delete();
        ComplaintModel::whereIn('id', $complaintIds)->delete();

        foreach ($complaintIds as $complaintId) {
            Storage::disk('public')->deleteDirectory('complaints/documents/'.$complaintId);
        }

        ServingRequest::whereIn('id', $manifest['serving_requests'] ?? [])->delete();
        Serving::whereIn('id', $manifest['servings'] ?? [])->delete();
        TopPerformer::whereIn('id', $manifest['top_performers'] ?? [])->delete();

        $castIds = $manifest['cast_user_ids'] ?? [];
        RewardModel::whereIn('user_id', $castIds)->delete();

        WalletModel::whereIn('user_id', $manifest['wallet_reset_user_ids'] ?? [])->update(['balance' => 0]);

        User::whereIn('id', $castIds)->update([
            'current_job' => null,
            'address' => null,
            'gender' => 'not specified',
            'birth_date' => null,
            'phone_number' => null,
            'services_requested_count' => 0,
            'weekly_service_count' => 0,
            'weekly_reset_at' => null,
            'monthly_service_count' => 0,
            'monthly_reset_at' => null,
            'is_identity_verified' => false,
            'identity_verified_at' => null,
        ]);

        $originalNames = $manifest['original_names'] ?? [];

        foreach ($castIds as $id) {
            $user = User::find($id);

            if (! $user) {
                continue;
            }

            $user->update(['full_name' => $originalNames[$user->email] ?? preg_replace('/^user(\d*)@system\.com$/', 'systemUser$1', $user->email)]);
        }

        $marker->delete();

        Storage::disk('public')->deleteDirectory('servings/demo');
        Storage::disk('public')->deleteDirectory('work_gallery/demo');
        Storage::disk('local')->delete($manifestPath);

        $this->info('Demo data removed. Users restored to their original names, wallets reset to 0.');

        return self::SUCCESS;
    }

    private function recoverManifest(): array
    {
        $castIds = User::all()
            ->filter(function (User $user) {
                return preg_match('/^user\d*@system\.com$/', $user->email)
                    && preg_match('/[^\x00-\x7F]/u', (string) $user->full_name);
            })
            ->pluck('id')
            ->all();

        if (empty($castIds)) {
            throw new \RuntimeException('Could not identify demo cast users (no @system.com accounts with a renamed name). Aborting.');
        }

        $complaintIds = ComplaintModel::whereIn('complainant_id', $castIds)
            ->orWhereIn('accused_user_id', $castIds)
            ->pluck('id')
            ->all();

        $servingIds = Serving::whereIn('user_id', $castIds)->pluck('id')->all();

        $galleryItemIds = WorkGalleryItem::whereIn('user_id', $castIds)->pluck('id')->all();
        $galleryFileIds = WorkGalleryItemFile::whereIn('work_gallery_item_id', $galleryItemIds)->pluck('id')->all();

        $chatIds = Chat::whereIn('created_by', $castIds)->pluck('id')->all();
        $messageIds = Message::whereIn('chat_id', $chatIds)->pluck('id')->all();
        $recipientIds = MessageRecipient::whereIn('message_id', $messageIds)->pluck('id')->all();

        return [
            'cast_user_ids' => $castIds,
            'wallet_reset_user_ids' => $castIds,
            'original_names' => [],
            'complaints' => $complaintIds,
            'complaint_documents' => ComplaintDocument::whereIn('complaint_id', $complaintIds)->pluck('id')->all(),
            'servings' => $servingIds,
            'serving_requests' => ServingRequest::whereIn('serving_id', $servingIds)->pluck('id')->all(),
            'gallery_items' => $galleryItemIds,
            'gallery_files' => $galleryFileIds,
            'chats' => $chatIds,
            'messages' => $messageIds,
            'message_recipients' => $recipientIds,
            'search_history' => UserSearchHistory::whereIn('user_id', $castIds)->pluck('id')->all(),
            'identity_verifications' => IdentityVerificationModel::whereIn('user_id', $castIds)->pluck('id')->all(),
            'top_performers' => TopPerformer::whereIn('user_id', $castIds)->pluck('id')->all(),
        ];
    }
}
