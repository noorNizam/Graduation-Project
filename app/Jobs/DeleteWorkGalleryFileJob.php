<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteWorkGalleryFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $fileUrl) {}

    public function handle(): void
    {
        $fileUrl = $this->fileUrl;

        if (empty($fileUrl)) {
            return;
        }

        try {
            $path = $this->urlToStoragePath($fileUrl);

            if (! $path) {
                Log::warning('DeleteWorkGalleryFileJob: could not determine storage path for URL: '.$fileUrl);

                return;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info('DeleteWorkGalleryFileJob: deleted '.$path);
            } else {
                Log::info('DeleteWorkGalleryFileJob: file not found, skipping '.$path);
            }
        } catch (\Throwable $e) {
            Log::error('DeleteWorkGalleryFileJob failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function urlToStoragePath(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? $url;

        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        $pos = strpos($path, '/storage/');
        if ($pos !== false) {
            return ltrim(substr($path, $pos + strlen('/storage/')), '/');
        }

        return ltrim($path, '/');
    }
}
