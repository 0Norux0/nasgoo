<?php

declare(strict_types=1);

namespace App\Domain\Vendor;

use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Phase 10 v10.1 — Vendor file preview helper.
 *
 * The admin Filament VendorResource used to display raw paths like
 * "vendors/123/logo.jpg" with no way to view the actual file. This
 * helper renders proper preview HTML:
 *   - public files (logo, banner)         → direct Storage::url thumbnail
 *   - private files (license, id document)→ signed URL through
 *                                            /admin/vendor-files/{id}/{kind}
 *                                            which re-checks admin auth and
 *                                            streams the file (never exposes
 *                                            the raw path publicly).
 * Image MIMEs get a thumbnail. Non-image files get a download link.
 */
final class VendorFileLinks
{
    /**
     * Returns a column on the Vendor model holding the path for `$kind`.
     */
    private const FIELD_MAP = [
        'logo'             => 'logo_path',
        'banner'           => 'banner_path',
        'license_document' => 'license_document_path',
        'id_document'      => 'id_document_path',
    ];

    /**
     * `logo` + `banner` are intentionally public (storefront usage).
     * `license_document` + `id_document` are private.
     */
    private const PRIVATE_KINDS = ['license_document', 'id_document'];

    public static function previewHtml(Vendor $vendor, string $kind): \Illuminate\Support\HtmlString
    {
        $field = self::FIELD_MAP[$kind] ?? null;
        if (! $field) {
            return new \Illuminate\Support\HtmlString('—');
        }

        $path = $vendor->{$field};
        if (empty($path)) {
            return new \Illuminate\Support\HtmlString(
                '<span class="text-slate-400 italic text-sm">Not uploaded</span>'
            );
        }

        // Resolve a viewing URL based on the kind
        $url = self::urlFor($vendor, $kind, $path);
        if ($url === null) {
            // File configured but no longer exists on disk
            return new \Illuminate\Support\HtmlString(sprintf(
                '<span class="text-amber-700 text-sm">⚠ File not found: %s</span>',
                e(basename($path))
            ));
        }

        $isImage = self::isImageExtension($path);
        $filename = e(basename($path));

        if ($isImage) {
            return new \Illuminate\Support\HtmlString(sprintf(
                '<div class="space-y-1">'
                . '<img src="%s" alt="%s" loading="lazy" '
                . 'style="max-width:200px;max-height:120px;border:1px solid #e5e7eb;border-radius:0.375rem;" />'
                . '<div><a href="%s" target="_blank" rel="noopener" '
                . 'class="text-indigo-600 hover:underline text-xs">Open full size →</a></div>'
                . '<div class="text-xs text-slate-500">%s</div>'
                . '</div>',
                $url, $filename, $url, $filename
            ));
        }

        // Non-image: just a download link with the original filename
        return new \Illuminate\Support\HtmlString(sprintf(
            '<div class="space-y-1">'
            . '<a href="%s" target="_blank" rel="noopener" '
            . 'class="inline-flex items-center gap-1 text-indigo-600 hover:underline text-sm">'
            . '📄 View %s'
            . '</a>'
            . '<div class="text-xs text-slate-500">Opens in new tab</div>'
            . '</div>',
            $url, $filename
        ));
    }

    /**
     * Resolve the URL the admin should use to view the file.
     *
     * Phase 10 v10.7 — delegates to VendorFileResolver to find the actual
     * disk a file lives on. Pre-v10.7 this method hardcoded public/private
     * disk reads, producing "File not found" for logo/banner image
     * uploads that the upload code wrote to the 'local' disk instead of
     * the 'public' disk. Now:
     *   - Private kinds → always signed admin route
     *   - Public kinds → Storage::url IF found on a publicly-served disk
     *                    (so storefront-facing usage works), otherwise
     *                    fall back to the signed admin route so the
     *                    admin Filament page can at least view it
     * Returns null only if the file truly doesn't exist on any configured
     * disk.
     */
    public static function urlFor(Vendor $vendor, string $kind, string $path): ?string
    {
        // Path arg kept for backward signature compatibility; the resolver
        // re-reads from the Vendor record directly so it sees normalized
        // paths and exists() checks.
        unset($path);

        $resolved = \App\Domain\Vendor\VendorFileResolver::resolve($vendor, $kind);
        if ($resolved === null) {
            return null;
        }

        // Private kinds ALWAYS go through the signed admin-only route, even
        // if the file happens to be on a public disk (defense in depth —
        // we don't accidentally hand out a public URL for an ID document).
        if (in_array($kind, self::PRIVATE_KINDS, true)) {
            return URL::temporarySignedRoute(
                'admin.vendor-files.show',
                now()->addMinutes(30),
                ['vendor' => $vendor->id, 'kind' => $kind],
            );
        }

        // Public kinds: if the resolver found the file on the canonical
        // public disk, return its public URL. If it found the file on a
        // legacy non-public disk (the pre-v10.7 bug case), fall back to
        // the signed admin route so the admin Filament page can still
        // view the file. Storefront pages that read raw `logo_path` will
        // still see the path string (separate concern, separate code path).
        $publicDisk = (string) config('marketplace.vendor_public_disk', 'public');
        if ($resolved['disk'] === $publicDisk) {
            return Storage::disk($resolved['disk'])->url($resolved['path']);
        }

        // Legacy file on a non-public disk — admin-only viewable
        return URL::temporarySignedRoute(
            'admin.vendor-files.show',
            now()->addMinutes(30),
            ['vendor' => $vendor->id, 'kind' => $kind],
        );
    }

    private static function isImageExtension(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}
