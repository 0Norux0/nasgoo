<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 12.3 — license gate.
 *
 * Runs on every web request AFTER auth. Delegates the decision to
 * LicenseManager::shouldBlockRequest — this middleware is just glue.
 *
 * When blocking:
 *   - Authenticated super-admin → redirect to /admin/license (they can fix it)
 *   - Any other authenticated user → 403 with an Inertia "License required" page
 *   - Guest → redirect to the public storefront (which is still visible)
 *     unless block_public_storefront=true, in which case → license page
 *
 * NEVER destroys data. NEVER logs sensitive payloads to storage/logs.
 */
class EnsureValidLicense
{
    public function __construct(private readonly LicenseManager $license)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        [$shouldBlock, $reason] = $this->license->shouldBlockRequest($request);
        if (! $shouldBlock) {
            return $next($request);
        }

        Log::channel(config('logging.default'))->info('license.blocked', [
            'path'   => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
            'reason' => $reason,
            'ip'     => $request->ip(),
        ]);

        $user = $request->user();

        // Super-admin: send them to the activation UI where they can paste a token.
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return Inertia::location(route('admin.license.index'));
            }
            return redirect()->route('admin.license.index');
        }

        // Other authenticated users: 403 page (Inertia) with a friendly message.
        if ($user) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'The marketplace is unavailable. Please contact your administrator.',
                    'status'  => 'license_required',
                ], Response::HTTP_FORBIDDEN);
            }
            return Inertia::render('License/Blocked', [
                'reason' => 'The marketplace is unavailable while its license is being renewed.',
            ])->toResponse($request)->setStatusCode(Response::HTTP_FORBIDDEN);
        }

        // Guest: send them to the storefront (public content is still visible by default)
        // unless block_public_storefront is true, in which case send to license page.
        if ((bool) config('license.block_public_storefront', false)) {
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return Inertia::location(route('license.status'));
            }
            return redirect()->route('license.status');
        }

        // Guest + public storefront allowed → let them see /  (the middleware only fires
        // if the requested path is NOT already an exempt public prefix, so we redirect home).
        return redirect('/');
    }
}
