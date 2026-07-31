<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class MediaUrl
{
    public static function resolvePublic(?string $path, mixed $legacyData, string $fallbackPath, mixed $updatedAt, string $mediaKey): ?string
    {
        if ($path && self::shouldUseDirectPublicUrls()) {
            $url = Storage::disk((string) config('media.public_disks.' . $mediaKey, 'public'))->url($path);

            return self::appendVersion($url, $updatedAt);
        }

        if ($path || !empty($legacyData)) {
            return self::appendVersion(
                rtrim((string) config('app.url'), '/') . $fallbackPath,
                $updatedAt
            );
        }

        return null;
    }

    private static function appendVersion(string $url, mixed $updatedAt): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . self::resolveTimestamp($updatedAt);
    }

    private static function resolveTimestamp(mixed $updatedAt): int
    {
        if ($updatedAt instanceof CarbonInterface) {
            return $updatedAt->timestamp;
        }

        if (is_string($updatedAt) && $updatedAt !== '') {
            return Carbon::parse($updatedAt)->timestamp;
        }

        return 0;
    }

    private static function shouldUseDirectPublicUrls(): bool
    {
        return (bool) config('media.use_direct_public_urls', true);
    }
}
