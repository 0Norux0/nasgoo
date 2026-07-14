<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;


/**
 * Customer-only registration.
 *
 * Admins are provisioned manually by a super_admin in the Filament panel;
 * there is no public path to admin role assignment. New self-registered
 * users always get the 'customer' role.
 *
 * v3.3 — accepts an optional ?redirect= query parameter, whitelisted to
 * the same set of safe internal paths as LoginController, so the
 * "Become a vendor" CTA can route new registrants straight to
 * /vendor/apply after they finish signing up.
 */
class RegisterController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'redirect' => $request->query('redirect'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:40', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms'    => ['accepted'],
            'redirect' => ['nullable', 'string', 'max:200'],
        ]);

        $user = User::create([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'phone'            => $data['phone'] ?? null,
            'password'         => Hash::make($data['password']),
            'locale'           => app()->getLocale(),
            'default_currency' => config('marketplace.default_currency', 'KWD'),
            'status'           => 'active',
        ]);

        // Public registration always yields a customer.
        $user->assignRole('customer');

        // Phase 7 v7.5 — even though User::sendEmailVerificationNotification()
        // is now self-graceful (catches transport errors and logs them),
        // we wrap the event dispatch here too. Other listeners may be
        // registered against Registered — any one of them failing must
        // NOT crash registration after the user row has already been
        // created. This is belt-and-suspenders defense, not redundancy.
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'A listener on Registered failed — registration will continue. '
                . 'Error: ' . $e->getMessage(),
                ['user_id' => $user->id, 'exception' => get_class($e)]
            );
        }

        Auth::login($user);
        $request->session()->regenerate();

        $destination = $request->session()->pull('url.intended')
            ?? $this->resolveRedirectParam($data['redirect'] ?? null)
            ?? '/';

        return redirect($destination)
            ->with('success', 'Welcome! Please check your email to verify your account.');
    }

    /**
     * Whitelist matches LoginController::resolveRedirectParam() — these
     * are the only post-auth destinations we trust from user-supplied input.
     */
    protected function resolveRedirectParam(?string $param): ?string
    {
        if (! is_string($param) || $param === '') {
            return null;
        }
        $allowed = ['/vendor/apply', '/vendor', '/account'];
        return in_array($param, $allowed, true) ? $param : null;
    }
}
