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

/* ─────────────────── Vendor application accessibility ─────────────────── */

it('redirects guests visiting /vendor/apply to login with the intended URL stored', function () {
    get('/vendor/apply')->assertRedirect('/login');
    expect(session('url.intended'))->toBe(config('app.url') . '/vendor/apply');
});

it('lets a logged-in customer view /vendor/apply (the actual form)', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    actingAs($user)->get('/vendor/apply')->assertSuccessful();
});

it('redirects an existing vendor away from /vendor/apply to the dashboard', function () {
    $user = User::factory()->create();
    Vendor::factory()->approved()->for($user)->create();

    actingAs($user)->get('/vendor/apply')->assertRedirect('/vendor');
});

it('completes the full guest → /vendor/apply → login → /vendor/apply flow', function () {
    $user = User::factory()->create(['email' => 'wanttovend@x.test', 'password' => bcrypt('secret123')]);
    $user->assignRole('customer');

    // 1. Guest is bounced to /login
    get('/vendor/apply')->assertRedirect('/login');

    // 2. Login completes with the intended URL preserved
    post('/login', ['email' => 'wanttovend@x.test', 'password' => 'secret123'])
        ->assertRedirect('/vendor/apply');

    // 3. The intended-URL fetch now actually renders the form
    get('/vendor/apply')->assertSuccessful();
});

/* ─────────────────── "Become a Vendor" link presence ─────────────────── */

it('shows a Become a Vendor link on the homepage to guests', function () {
    get('/')
        ->assertSuccessful()
        ->assertSee('Become a vendor', false);   // case-insensitive label match
});

it('shows a Become a Vendor link on the homepage to logged-in customers', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    actingAs($user)->get('/')
        ->assertSuccessful()
        ->assertSee('Become a vendor', false);
});

it('shows a Vendor dashboard link (not Become a vendor) to existing vendors', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->approved()->for($user)->create();

    actingAs($user)->get('/')
        ->assertSuccessful()
        ->assertSee('Vendor dashboard', false);
});

it('shows a Become a Vendor link on the login page', function () {
    get('/login')
        ->assertSuccessful()
        ->assertSee('Become a vendor', false);
});

it('shows a Become a Vendor link on the register page', function () {
    get('/register')
        ->assertSuccessful()
        ->assertSee('Become a vendor', false);
});

/* ─────────────────── Language switcher must be hidden ─────────────────── */

it('does NOT render the language switcher (translations not wired yet — v3.2)', function () {
    // The LangSwitcher rendered ?locale= toggle buttons without actually
    // translating content. v3.2 removes it to avoid misleading users.
    get('/')
        ->assertSuccessful()
        ->assertDontSee('العربية', false)
        ->assertDontSee('اردو', false);
});

/* ─────────────────── Vendor application submission ─────────────────── */

it('creates a pending vendor when a customer submits the application', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $basic = \App\Models\VendorPackage::where('slug', 'basic')->firstOrFail();

    actingAs($user)
        ->post('/vendor/apply', [
            'business_name'     => 'New Shop',
            'business_email'    => 'shop@x.test',
            'business_type'     => 'individual',
            'country'           => 'KW',
            'vendor_package_id' => $basic->id,
            'agree_terms'       => true,
        ])
        ->assertRedirect('/vendor');

    $vendor = Vendor::where('user_id', $user->id)->first();
    expect($vendor)->not->toBeNull()
        ->and($vendor->status)->toBe(Vendor::STATUS_PENDING);
});
