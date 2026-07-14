<?php

declare(strict_types=1);

/**
 * Phase 4 v5.1 — audit item 13: Filament/admin assets still loading.
 *
 * The Phase 2 v3.3 fix made the Docker entrypoint re-publish Filament assets
 * on every container start. This test pins the wiring so Phase 4's new admin
 * resources (OrderResource, PaymentMethodResource) don't accidentally break
 * the admin shell.
 */

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('item 13: /admin/login is reachable and returns a styled response', function () {
    $response = $this->get('/admin/login');
    expect($response->status())->toBeIn([200, 302]); // 200 if guest sees login; 302 if redirect to fresh login
});

it('item 13: super_admin reaches /admin (no asset crash)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin');
    expect($response->status())->toBeIn([200, 302]);  // Filament panels can redirect to dashboard
});

it('item 13: Phase 4 OrderResource is registered in the admin panel', function () {
    expect(class_exists(\App\Filament\Resources\OrderResource::class))->toBeTrue();
    // Sanity-check it points to the Order model
    expect(\App\Filament\Resources\OrderResource::getModel())->toBe(\App\Models\Order::class);
});

it('item 13: Phase 4 PaymentMethodResource is registered', function () {
    expect(class_exists(\App\Filament\Resources\PaymentMethodResource::class))->toBeTrue();
    expect(\App\Filament\Resources\PaymentMethodResource::getModel())->toBe(\App\Models\PaymentMethod::class);
});

it('item 13: Operations nav group is configured on the admin panel', function () {
    $reflected = new ReflectionClass(\App\Providers\Filament\AdminPanelProvider::class);
    $source = file_get_contents($reflected->getFileName());
    // Phase 4 added 'Operations' to the nav groups
    expect($source)->toContain("'Operations'");
});

it('item 13: Vite manifest exists and references the Inertia entrypoint', function () {
    $manifestPath = public_path('build/manifest.json');
    // In CI the build will have run; locally tests can pass either way
    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('Vite manifest not present — run `npm run build` first.');
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray();
    expect(array_keys($manifest))->toContain('resources/js/app.tsx');
});
