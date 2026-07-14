<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loads the authenticated user's vendor profile into the request.
 * Status-aware: blocks vendors that are suspended/rejected from
 * action endpoints (only allows them to view their dashboard so
 * they can read the rejection/suspension reason).
 *
 *   Route::middleware(['auth', 'vendor'])->...               // any vendor profile
 *   Route::middleware(['auth', 'vendor:approved'])->...      // approved only
 */
class EnsureVendor
{
    public function handle(Request $request, Closure $next, string $required = 'any'): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect('/login');
        }

        /** @var Vendor|null $vendor */
        $vendor = $user->vendor()->first();

        if (! $vendor) {
            // No vendor profile — send them to apply
            return redirect('/vendor/apply');
        }

        $request->attributes->set('vendor', $vendor);

        if ($required === 'approved' && ! $vendor->isApproved()) {
            // Approved-only routes redirect back to dashboard where the
            // pending/rejected/suspended message is visible.
            return redirect('/vendor');
        }

        return $next($request);
    }
}
