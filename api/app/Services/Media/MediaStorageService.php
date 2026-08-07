<?php

namespace App\Services\Media;

use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    public function storePublicImage(UploadedFile $file, string $directory, string $mediaKey): array
    {
        $extension = $file->guessExtension() ?: $file->extension() ?: 'bin';
        $filename = (string) Str::uuid() . '.' . $extension;
        $directory = trim($directory, '/');

        Storage::disk($this->publicDisk($mediaKey))->putFileAs($directory, $file, $filename);

        return [
            'path' => $directory . '/' . $filename,
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'updated_at' => now(),
        ];
    }

    public function deletePublicMedia(?string $path, string $mediaKey): void
    {
        if (!$path) {
            return;
        }

        $disk = Storage::disk($this->publicDisk($mediaKey));

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function hasPublicMedia(?string $path, mixed $legacyData = null, string $mediaKey = 'tenant'): bool
    {
        if ($path && Storage::disk($this->publicDisk($mediaKey))->exists($path)) {
            return true;
        }

        return !empty($legacyData);
    }

    public function publicMediaResponse(?string $path, mixed $legacyData, ?string $mime, string $mediaKey): Response|RedirectResponse
    {
        $headers = [
            'Content-Type' => $mime ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=' . $this->publicCacheSeconds() . ', immutable',
        ];

        if ($path && Storage::disk($this->publicDisk($mediaKey))->exists($path)) {
            if ($this->usesRemotePublicDisk($mediaKey) && $this->shouldRedirectToDirectPublicUrl($mediaKey)) {
                return redirect()->away(Storage::disk($this->publicDisk($mediaKey))->url($path), 302, [
                    'Cache-Control' => $headers['Cache-Control'],
                ]);
            }

            return response(Storage::disk($this->publicDisk($mediaKey))->get($path), 200, $headers);
        }

        if (!empty($legacyData)) {
            return response($legacyData, 200, $headers);
        }

        abort(404);
    }

    private function publicDisk(string $mediaKey): string
    {
        return (string) config('media.public_disks.' . $mediaKey, 'public');
    }

    private function publicCacheSeconds(): int
    {
        return (int) config('media.public_cache_seconds', 31536000);
    }

    private function useDirectPublicUrls(): bool
    {
        return (bool) config('media.use_direct_public_urls', false);
    }

    private function usesRemotePublicDisk(string $mediaKey): bool
    {
        return config('filesystems.disks.' . $this->publicDisk($mediaKey) . '.driver') === 's3';
    }

    private function shouldRedirectToDirectPublicUrl(string $mediaKey): bool
    {
        if (! $this->useDirectPublicUrls()) {
            return false;
        }

        return (string) config('filesystems.disks.' . $this->publicDisk($mediaKey) . '.url', '') !== '';
    }

}
