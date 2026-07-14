<?php

declare(strict_types=1);

namespace App\Domain\Customization;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 7 — CustomizationFileStorage.
 *
 * Stores customization uploads on the `local` (private) disk:
 *   storage/app/private/customizations/{customer_id}/{random_name}.{ext}
 *
 * Filenames are randomized via Str::random(40) so even if the path leaks,
 * the URL is non-guessable. The ORIGINAL filename is preserved on the
 * customization row for display, not in the storage path.
 *
 * Access is brokered by signed-URL endpoints in
 * App\Http\Controllers\Customer\CustomizationFileController and
 * App\Http\Controllers\Vendor\VendorCustomizationFileController; the
 * files are NEVER directly URL-accessible.
 */
class CustomizationFileStorage
{
    public const DISK = 'local';
    public const ROOT = 'customizations';
    public const PROOFS_ROOT = 'customization-proofs';

    /**
     * Store a customer upload.
     * Returns the storage-relative path.
     */
    public function storeCustomerUpload(UploadedFile $file, int $userId): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = Str::random(40) . '.' . $ext;
        $dir  = self::ROOT . '/' . $userId;
        $file->storeAs($dir, $name, self::DISK);
        return $dir . '/' . $name;
    }

    public function storeVendorProof(UploadedFile $file, int $vendorId, int $orderItemId): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = Str::random(40) . '.' . $ext;
        $dir  = self::PROOFS_ROOT . '/' . $vendorId . '/' . $orderItemId;
        $file->storeAs($dir, $name, self::DISK);
        return $dir . '/' . $name;
    }

    public function delete(string $path): void
    {
        if ($path === '' || $path === null) return;
        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }
        return Storage::disk(self::DISK)->readStream($path);
    }

    public function mimeTypeOf(string $path): ?string
    {
        if (! Storage::disk(self::DISK)->exists($path)) return null;
        return Storage::disk(self::DISK)->mimeType($path) ?: null;
    }

    public function sizeOf(string $path): ?int
    {
        if (! Storage::disk(self::DISK)->exists($path)) return null;
        return Storage::disk(self::DISK)->size($path) ?: null;
    }
}
