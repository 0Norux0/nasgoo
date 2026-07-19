<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceMedia
{
    public static function publicUrl(?string $path): ?string
    {
        $trimmed = trim((string) $path);

        if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_URL) && ! self::isStorageUrl($trimmed)) {
            return $trimmed;
        }

        $normalized = self::publicPath($path);

        return $normalized ? '/storage/' . $normalized : null;
    }

    public static function publicPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = trim($path);

        if ($normalized === '') {
            return null;
        }

        if (filter_var($normalized, FILTER_VALIDATE_URL)) {
            $normalized = (string) parse_url($normalized, PHP_URL_PATH);
        }

        $normalized = ltrim($normalized, '/');

        foreach ([
            'storage/app/public/',
            'public/storage/',
            'storage/',
            'public/',
        ] as $prefix) {
            if (Str::startsWith($normalized, $prefix)) {
                $normalized = Str::after($normalized, $prefix);
                break;
            }
        }

        if (str_contains($normalized, '..')) {
            return null;
        }

        return $normalized !== '' ? $normalized : null;
    }

    public static function publicPathOrExternalUrl(?string $path): ?string
    {
        $normalized = trim((string) $path);

        if ($normalized === '') {
            return null;
        }

        if (filter_var($normalized, FILTER_VALIDATE_URL) && ! self::isStorageUrl($normalized)) {
            return $normalized;
        }

        return self::publicPath($normalized);
    }

    public static function storePublicPreservingExtension(UploadedFile $file, string $directory): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $filename = Str::uuid()->toString() . ($extension !== '' ? ".{$extension}" : '');
        $path = $file->storeAs($directory, $filename, 'public');

        if (! $path) {
            return null;
        }

        Storage::disk('public')->setVisibility($path, 'public');

        return $path;
    }

    private static function isStorageUrl(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return Str::startsWith(ltrim($path, '/'), 'storage/');
    }
}
