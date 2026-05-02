<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DeleteServingImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $imageUrl)
    {
    }

    public function handle(): void
    {
        $imageUrl = $this->imageUrl;

        if (empty($imageUrl)) {
            return;
        }

        try {
            // Convert public Storage URL to relative path
            $path = $this->urlToStoragePath($imageUrl);

            if (! $path) {
                Log::warning('DeleteServingImageJob: could not determine storage path for URL: ' . $imageUrl);
                return;
            }

            // Check if any Serving still references this URL
            $exists = \App\Infrastructure\Models\Serving::where('image_url', $imageUrl)->exists();
            if ($exists) {
                // Someone still references it; skip deletion
                Log::info('DeleteServingImageJob: image still referenced, skipping delete: ' . $imageUrl);
                return;
            }

            // Delete from the public disk
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info('DeleteServingImageJob: deleted ' . $path);
            } else {
                Log::info('DeleteServingImageJob: file not found, skipping ' . $path);
            }
        } catch (\Throwable $e) {
            Log::error('DeleteServingImageJob failed: ' . $e->getMessage());
            // Let the job be retried according to the queue configuration
            throw $e;
        }
    }

    private function urlToStoragePath(string $url): ?string
    {
        // Try to parse path portion
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? $url;

        // Typical local public disk URL is /storage/<path>
        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        // If contains /storage/ somewhere
        $pos = strpos($path, '/storage/');
        if ($pos !== false) {
            return ltrim(substr($path, $pos + strlen('/storage/')), '/');
        }

        // Fallback: if the URL ends with a known storage path pattern, try to extract last segments
        return ltrim($path, '/');
    }
}
