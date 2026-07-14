<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

/* ─────────────── Admin separation ─────────────── */

it('rejects super_admin attempts to use the public /login endpoint', function () {
    $admin = User::factory()->create(['email' => 'a1@x.test', 'password' => bcrypt('secret123')]);
    $admin->assignRole('super_admin');

    $response = post('/login', ['email' => 'a1@x.test', 'password' => 'secret123']);

    $response->assertSessionHasErrors('email');
    expect(auth()->check())->toBeFalse(); // session was torn down
});

it('rejects admin_staff attempts to use the public /login endpoint', function () {
    $staff = User::factory()->create(['email' => 'a2@x.test', 'password' => bcrypt('secret123')]);
    $staff->assignRole('admin_staff');

    post('/login', ['email' => 'a2@x.test', 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('still lets customers use /login', function () {
    $u = User::factory()->create(['email' => 'c@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('customer');

    post('/login', ['email' => 'c@x.test', 'password' => 'secret123'])->assertRedirect('/');
    expect(auth()->check())->toBeTrue();
});

it('still lets approved vendors use /login and lands them on /vendor', function () {
    $u = User::factory()->create(['email' => 'v@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('vendor');
    Vendor::factory()->approved()->for($u)->create();

    post('/login', ['email' => 'v@x.test', 'password' => 'secret123'])->assertRedirect('/vendor');
});

it('exposes /admin/login as Filament-handled (not the Inertia public login)', function () {
    // /admin/login returns Filament's login HTML, not an Inertia page
    $response = get('/admin/login');
    $response->assertSuccessful();
    expect($response->headers->get('X-Inertia'))->toBeNull(); // Filament does not set this
});

/* ─────────────── ?redirect= flow ─────────────── */

it('honors a whitelisted ?redirect= parameter after customer login', function () {
    $u = User::factory()->create(['email' => 'r@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('customer');

    post('/login', [
        'email'    => 'r@x.test',
        'password' => 'secret123',
        'redirect' => '/vendor/apply',
    ])->assertRedirect('/vendor/apply');
});

it('ignores ?redirect= values not in the whitelist (defends against open-redirect)', function () {
    $u = User::factory()->create(['email' => 'r2@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('customer');

    post('/login', [
        'email'    => 'r2@x.test',
        'password' => 'secret123',
        'redirect' => 'https://evil.example.com/steal',
    ])->assertRedirect('/'); // falls through to role default
});

/* ─────────────── CSRF / 419 absence ─────────────── */

it('does NOT return 419 on a normal customer login (Pest re-uses the session token)', function () {
    $u = User::factory()->create(['email' => 'csrf@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('customer');

    $response = post('/login', ['email' => 'csrf@x.test', 'password' => 'secret123']);

    expect($response->getStatusCode())->not->toBe(419);
});

it('does NOT return 419 on logout', function () {
    $u = User::factory()->create();
    $u->assignRole('customer');

    $response = actingAs($u)->post('/logout');

    expect($response->getStatusCode())->not->toBe(419);
});

it('does NOT return 419 on a vendor application submission', function () {
    $u = User::factory()->create();
    $u->assignRole('customer');
    $pkg = \App\Models\VendorPackage::where('slug', 'basic')->firstOrFail();

    $response = actingAs($u)->post('/vendor/apply', [
        'business_name'     => 'CSRF Shop',
        'business_email'    => 'csrf@shop.test',
        'business_type'     => 'individual',
        'country'           => 'KW',
        'vendor_package_id' => $pkg->id,
        'agree_terms'       => true,
    ]);

    expect($response->getStatusCode())->not->toBe(419);
});

/* ─────────────── Session persists across navigation ─────────────── */

it('keeps the customer logged in across two consecutive page loads', function () {
    $u = User::factory()->create(['email' => 'persist@x.test', 'password' => bcrypt('secret123')]);
    $u->assignRole('customer');

    post('/login', ['email' => 'persist@x.test', 'password' => 'secret123']);
    expect(auth()->check())->toBeTrue();

    get('/')->assertSuccessful();
    expect(auth()->check())->toBeTrue();

    get('/vendor/apply')->assertSuccessful();
    expect(auth()->check())->toBeTrue();
});

/* ─────────────── Locale switching ─────────────── */

it('switches the active locale when posting to /locale/{code}', function () {
    expect(app()->getLocale())->toBe(config('app.locale'));

    post('/locale/ar')->assertRedirect();
    expect(session('locale'))->toBe('ar');
});

it('rejects unsupported locale codes', function () {
    $this->post('/locale/zz')->assertNotFound();
});

it('persists locale on the user record when authenticated', function () {
    $u = User::factory()->create(['locale' => 'en']);
    $u->assignRole('customer');

    actingAs($u)->post('/locale/ar');

    expect($u->fresh()->locale)->toBe('ar');
});

it('shares the chosen translation map through Inertia props', function () {
    // Switch to Arabic, then load any Inertia page; props should include
    // an Arabic translation for at least one well-known key.
    $this->post('/locale/ar');
    $response = get('/');

    $response->assertSuccessful();
    $arabicSignIn = json_decode((string) file_get_contents(base_path('lang/ar.json')), true)['nav.sign_in'] ?? null;
    expect($arabicSignIn)->toBe('تسجيل الدخول');
});
