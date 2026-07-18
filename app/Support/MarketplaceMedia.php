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

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        if (str_contains($normalized, '..')) {
            return null;
        }

        return $normalized !== '' ? $normalized : null;
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
}
