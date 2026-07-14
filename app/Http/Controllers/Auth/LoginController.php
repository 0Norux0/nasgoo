<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;


/**
 * Public (customer + vendor) authentication.
 *
 * Admin authentication is intentionally handled by a SEPARATE Filament
 * panel login at /admin/login. Admins who attempt to use /login get a
 * clear ValidationException pointing them at the correct URL — they do
 * NOT get authenticated as side-effect. This keeps the two auth flows
 * cleanly separated as required by v3.3.
 */
class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            // Forward an optional ?redirect=… so the page can preserve it
            // through the POST. Whitelisted in store() for safety.
            'redirect' => $request->query('redirect'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = strtolower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // v3.3 — admins must use /admin/login. Reject + tear down session.
        // We DO NOT log them in via this endpoint; immediately invalidate.
        if ($user->hasAnyRole(['super_admin', 'admin_staff'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => 'Admin users must sign in via /admin/login.',
            ]);
        }

        // Block suspended/banned accounts
        if (in_array($user->status, ['suspended', 'banned'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => "Your account is {$user->status}. Please contact support.",
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Update last-login bookkeeping (columns exist from Phase 0 schema)
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return $this->redirectAfterLogin($request, $user);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * v3.3 — three-tier destination resolution, all whitelisted:
     *   1) session('url.intended') if set by auth middleware (preferred)
     *   2) ?redirect= query param if it points at a known safe path
     *   3) role-based default
     *
     * Customer + vendor flows are entirely Inertia, so the standard
     * redirect() is fine. We no longer need Inertia::location() here
     * because admins can no longer reach this endpoint at all (rejected
     * above). That removes the cross-app navigation problem that bit us
     * in v3.1 and v3.2.
     */
    protected function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $destination = $request->session()->pull('url.intended')
            ?? $this->resolveRedirectParam($request)
            ?? $this->defaultRedirectFor($user);

        return redirect($destination);
    }

    /**
     * Read ?redirect= from the request, only honoring it if it matches
     * a small whitelist of safe internal paths. Prevents open-redirects.
     */
    protected function resolveRedirectParam(Request $request): ?string
    {
        $param = $request->input('redirect');
        if (! is_string($param) || $param === '') {
            return null;
        }

        $allowedPrefixes = ['/vendor/apply', '/vendor', '/account'];
        foreach ($allowedPrefixes as $allowed) {
            if ($param === $allowed) {
                return $param;
            }
        }
        return null;
    }

    /**
     * Default post-login destination per role.
     * - vendor (any status) → /vendor (status-aware dashboard)
     * - customer / no role  → /
     *
     * Admin defaults intentionally NOT listed — admins are blocked above.
     */
    protected function defaultRedirectFor(User $user): string
    {
        if ($user->hasRole('vendor') || $user->vendor()->exists()) {
            return '/vendor';
        }
        return '/';
    }
}
