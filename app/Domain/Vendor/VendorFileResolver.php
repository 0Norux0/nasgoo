<?php

declare(strict_types=1);

namespace App\Domain\Vendor;

use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 10 v10.7 — canonical vendor-file resolver.
 *
 * Why this exists: pre-v10.7, the VendorRegistrationController wrote ALL
 * uploads to the default disk (= 'local'), but VendorFileLinks::urlFor
 * read public kinds (logo, banner) from the 'public' disk via
 * Storage::url. The disk mismatch produced "File not found" for every
 * image document while PDFs (private kinds — license_document,
 * id_document — used a matching disk) continued to work. The dev's
 * v10.7 §3 demands one canonical resolver.
 *
 * Architecture going forward:
 *   - logo, banner (PUBLIC kinds)  → 'public' disk, served via Storage::url()
 *   - license_document, id_document (PRIVATE kinds) → 'vendors' disk
 *     (= storage/app/private), served only through the signed
 *     /admin/vendor-files/{id}/{kind} route
 *
 * Legacy compatibility: existing records may have logos on the 'local'
 * disk (the pre-v10.7 mistake). The resolver checks the canonical disk
 * first; if missing, falls back to other configured disks for that
 * kind in a defined order. Returns the actual disk a file was found
 * on, so callers can pick the right URL generation strategy.
 */
final class VendorFileResolver
{
    /** Vendor column holding the path for each kind. */
    private const FIELD_MAP = [
        'logo'             => 'logo_path',
        'banner'           => 'banner_path',
        'license_document' => 'license_document_path',
        'id_document'      => 'id_document_path',
    ];

    /** Kinds that are user-public (logo on storefront, banner on vendor page). */
    public const PUBLIC_KINDS = ['logo', 'banner'];

    /** Kinds that must stay behind the admin-only signed route. */
    public const PRIVATE_KINDS = ['license_document', 'id_document'];

    /** Image extensions that get inline thumbnail rendering. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Resolve where the file for $kind on $vendor actually lives.
     *
     * @return array{disk:string,path:string,is_image:bool,is_canonical:bool}|null
     *         null if no file is recorded OR the file doesn't exist on any
     *         configured disk for this kind.
     */
    public static function resolve(Vendor $vendor, string $kind): ?array
    {
        $field = self::FIELD_MAP[$kind] ?? null;
        if ($field === null) {
            return null;
        }

        $rawPath = (string) ($vendor->{$field} ?? '');
        if ($rawPath === '') {
            return null;
        }

        $path = self::normalizePath($rawPath);
        if ($path === null) {
            // Path traversal attempt or otherwise unsafe path. Treat as not
            // found rather than letting it through.
            return null;
        }

        // Probe disks in priority order. First hit wins. The first entry
        // is the canonical disk for this kind; subsequent are legacy
        // fallbacks for files written by pre-v10.7 upload code.
        $candidates = self::diskCandidates($kind);
        foreach ($candidates as $i => $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return [
                        'disk'         => $disk,
                        'path'         => $path,
                        'is_image'     => self::isImage($path),
                        'is_canonical' => $i === 0,
                    ];
                }
            } catch (\Throwable) {
                // A configured-but-broken disk (e.g. unreachable S3) should
                // not crash the admin UI. Move on to the next candidate.
                continue;
            }
        }

        return null;
    }

    /**
     * Disks to probe for a given kind, in priority order. First is canonical.
     *
     * @return list<string>
     */
    private static function diskCandidates(string $kind): array
    {
        if (in_array($kind, self::PUBLIC_KINDS, true)) {
            $canonical = config('marketplace.vendor_public_disk', 'public');
            // Legacy: pre-v10.7 logo/banner uploads went to the default disk
            // (= 'local' typically). Also check the private 'vendors' disk in
            // case of weird ordering bugs in older code. Deduplicate so we
            // don't probe the same disk twice when configs collapse.
            return array_values(array_unique([
                (string) $canonical,
                (string) config('marketplace.vendor_private_disk', 'vendors'),
                (string) config('filesystems.default', 'local'),
            ]));
        }

        // Private kinds: vendors disk (root storage/app/private) is canonical.
        // Fallback to 'local' which has the same root in the default install
        // (idempotent probe). Then to the public disk for the truly broken
        // case where someone misuploaded a license to the public bucket.
        $canonical = config('marketplace.vendor_private_disk', 'vendors');
        return array_values(array_unique([
            (string) $canonical,
            (string) config('filesystems.default', 'local'),
            (string) config('marketplace.vendor_public_disk', 'public'),
        ]));
    }

    /**
     * Normalize a stored path. Strips accidentally-leading prefixes
     * (`/storage/`, `storage/`, `public/`, leading `/`), converts
     * Windows backslashes to forward slashes, and rejects path
     * traversal segments.
     */
    public static function normalizePath(string $raw): ?string
    {
        // Normalize separators
        $p = str_replace('\\', '/', trim($raw));
        // Strip a leading slash so the path is disk-relative
        $p = ltrim($p, '/');
        // Strip common accidental prefixes (in order — `/storage/` strip
        // already removed the leading slash, so check the rest)
        foreach (['storage/app/public/', 'storage/app/private/', 'storage/app/', 'storage/', 'public/'] as $prefix) {
            if (str_starts_with($p, $prefix)) {
                $p = substr($p, strlen($prefix));
                break;
            }
        }
        // Collapse duplicated `vendors/vendors/` if a buggy migration
        // double-prefixed the path
        $p = preg_replace('#^(vendors/)+#', 'vendors/', $p) ?? $p;

        // Reject path traversal AFTER normalization
        if ($p === '' || str_contains($p, '../') || str_contains($p, '/..') || str_contains($p, "\0")) {
            return null;
        }

        return $p;
    }

    public static function isImage(string $path): bool
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::IMAGE_EXTENSIONS, true);
    }

    public static function isPdf(string $path): bool
    {
        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public static function fieldFor(string $kind): ?string
    {
        return self::FIELD_MAP[$kind] ?? null;
    }
}
