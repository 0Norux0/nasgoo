<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active UI locale on every web request.
 *
 * Source priority:
 *   1. Authenticated user's `locale` column (if set)
 *   2. Session('locale') chosen via /locale/{code} POST
 *   3. APP_LOCALE config
 *
 * Only locales in config('marketplace.supported_locales') are honored;
 * anything else falls through to the app default. This stops users from
 * pinning unsupported locales and prevents lookup errors downstream.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('marketplace.supported_locales', ['en']);
        $default   = config('app.locale', 'en');

        $candidate = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $default;

        $locale = in_array($candidate, $supported, true) ? $candidate : $default;

        app()->setLocale($locale);

        return $next($request);
    }
}
