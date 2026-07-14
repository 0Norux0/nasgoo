<?php

declare(strict_types=1);

/**
 * Phase 7 v7.5 — Registration must not crash on mail-transport failures.
 *
 * Bug history (developer reports):
 *   v7.0-v7.4: registration crashed with HTTP 500 if MAIL_HOST=mailpit was
 *   configured but the Mailpit container wasn't running. The TransportException
 *   from Symfony Mailer bubbled out of event(new Registered($user)) and the
 *   user account — already inserted into the DB — was abandoned with a 500
 *   response.
 *
 * v7.5 ships TWO defenses (either one alone catches this):
 *   1. User::sendEmailVerificationNotification() override wraps the parent
 *      call in try/catch + logs at WARNING level
 *   2. RegisterController::store() wraps event(new Registered($user)) in
 *      try/catch as belt-and-suspenders for OTHER listeners
 *
 * These scenarios run with Mail::fake() to assert the happy path AND with
 * an actively broken mail transport to assert the defenses fire.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles so $user->assignRole('customer') works
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

/* ─────────────────────────────────────────────
   1. Happy path — registration with log mailer
   ───────────────────────────────────────────── */

it('Phase 7 v7.5: registration succeeds with MAIL_MAILER=log default and creates a customer', function () {
    config(['mail.default' => 'log']);
    Notification::fake();

    $response = $this->post('/register', [
        'name'                  => 'Test Customer',
        'email'                 => 'register.v75.happy@test.example',
        'password'              => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'terms'                 => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user = User::where('email', 'register.v75.happy@test.example')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
    expect(auth()->id())->toBe($user->id);

    Notification::assertSentTo($user, VerifyEmail::class);
});

/* ─────────────────────────────────────────────
   2. Registration with a forced mail-transport failure
   ───────────────────────────────────────────── */

it('Phase 7 v7.5: registration succeeds even when mail transport throws (the v7.0-v7.4 bug-repro)', function () {
    Log::spy();

    // Force the Mail manager to throw on send (the same behaviour as
    // Mailpit being unreachable). Notification::send uses the Mail
    // channel by default for MustVerifyEmail.
    Mail::shouldReceive('mailer')->andThrow(
        new \Symfony\Component\Mailer\Exception\TransportException(
            'Connection could not be established with host "mailpit:1025"'
        )
    );

    $response = $this->post('/register', [
        'name'                  => 'Resilient User',
        'email'                 => 'register.v75.broken@test.example',
        'password'              => 'StrongPass123',
        'password_confirmation' => 'StrongPass123',
        'terms'                 => true,
    ]);

    // CRITICAL: registration must NOT 500 — the v7.4 and earlier behaviour.
    expect($response->status())->toBeLessThan(500);
    $response->assertRedirect();

    // User row was still created
    $user = User::where('email', 'register.v75.broken@test.example')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();

    // Customer is logged in (registration flow completed)
    expect(auth()->id())->toBe($user->id);

    // The transport failure was logged at WARNING level for triage
    Log::shouldHaveReceived('warning')->atLeast()->once();
})->skip(
    // Mail::shouldReceive on a Facade can be fragile depending on the order
    // in which other tests touched the Mail manager. Skipped in environments
    // where the mock can't be installed cleanly. The User-model override is
    // separately tested below as the bulletproof defense.
    fn () => !class_exists(\Symfony\Component\Mailer\Exception\TransportException::class),
    'TransportException class not present in environment'
);

/* ─────────────────────────────────────────────
   3. User::sendEmailVerificationNotification override
   ───────────────────────────────────────────── */

it('Phase 7 v7.5: User::sendEmailVerificationNotification() catches transport exceptions and logs them', function () {
    Log::spy();

    $user = User::factory()->create([
        'email' => 'send-notify-test@test.example',
        'email_verified_at' => null,
    ]);

    // Mock the parent notification send to throw — the same pattern as a
    // failed SMTP connection. We use Notification::fake() THEN swap in a
    // mock that throws.
    Notification::shouldReceive('send')->andThrow(
        new \Symfony\Component\Mailer\Exception\TransportException(
            'getaddrinfo for mailpit failed: No such host is known'
        )
    );

    // This MUST NOT throw — the User override catches it
    $caught = null;
    try {
        $user->sendEmailVerificationNotification();
    } catch (\Throwable $e) {
        $caught = $e;
    }
    expect($caught)->toBeNull();

    // The failure was logged
    Log::shouldHaveReceived('warning')->atLeast()->once();
})->skip(
    fn () => !class_exists(\Symfony\Component\Mailer\Exception\TransportException::class),
    'TransportException class not present in environment'
);

/* ─────────────────────────────────────────────
   4. Static check — code has the safeguard
   ───────────────────────────────────────────── */

it('Phase 7 v7.5: User model overrides sendEmailVerificationNotification with try/catch', function () {
    $src = file_get_contents(app_path('Models/User.php'));
    expect($src)->toContain('public function sendEmailVerificationNotification()');
    expect($src)->toContain('parent::sendEmailVerificationNotification()');
    expect($src)->toMatch('/catch\s*\(\s*\\\\Throwable/');
    expect($src)->toContain('Log::warning');
});

it('Phase 7 v7.5: RegisterController wraps event(new Registered) in try/catch', function () {
    $src = file_get_contents(app_path('Http/Controllers/Auth/RegisterController.php'));
    expect($src)->toContain('event(new Registered($user))');
    expect($src)->toMatch('/try\s*\{\s*event\(new Registered/s');
    expect($src)->toMatch('/catch\s*\(\s*\\\\Throwable/');
});

it('Phase 7 v7.5: .env.example uses MAIL_MAILER=log as the default', function () {
    $src = file_get_contents(base_path('.env.example'));
    expect($src)->toMatch('/^MAIL_MAILER=log$/m');
});
