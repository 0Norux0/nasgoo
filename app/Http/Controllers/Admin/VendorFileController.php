<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Vendor\VendorFileResolver;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 10 v10.1 — secure vendor file viewer.
 *
 * Serves vendor uploads (license_document, id_document, logo, banner) to
 * admin users only. The route is signed (URL::temporarySignedRoute) so
 * the link cannot be reused or shared past expiry. Admin authorization
 * is checked twice: once via the route middleware and once via the role
 * check below (defense in depth).
 *
 * Phase 10 v10.7 — uses VendorFileResolver to find files across any
 * configured vendor disk. Pre-v10.7 this controller hardcoded
 * `Storage::disk('vendors')`, which produced 404s for logo/banner files
 * that landed on the 'local' disk (the upload code's default-disk bug).
 * Now the resolver probes the canonical disk first, then legacy
 * fallbacks, so the admin can view both NEW uploads (correct disk) AND
 * LEGACY records (wrong disk).
 */
class VendorFileController extends Controller
{
    /** Kinds the route is allowed to serve. Order doesn't matter. */
    private const ALLOWED_KINDS = ['license_document', 'id_document', 'logo', 'banner'];

    public function show(Request $request, int $vendor, string $kind): Response
    {
        // Defense layer 1: signed URL check
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        // Defense layer 2: only admins
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['super_admin', 'admin_staff'])) {
            abort(403);
        }

        // Defense layer 3: only known kinds
        if (! in_array($kind, self::ALLOWED_KINDS, true)) {
            abort(404);
        }

        $v = Vendor::find($vendor);
        if (! $v) {
            abort(404);
        }

        // Resolver tells us the actual disk + normalized path. Returns null
        // when no file is recorded OR when the file is missing on every
        // configured disk for this kind.
        $resolved = VendorFileResolver::resolve($v, $kind);
        if ($resolved === null) {
            abort(404, 'File not recorded or no longer on disk.');
        }

        $disk = $resolved['disk'];
        $path = $resolved['path'];

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type'           => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

