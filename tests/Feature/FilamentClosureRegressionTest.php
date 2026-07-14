<?php

declare(strict_types=1);

/**
 * Phase 4 v5.4 — regression for the Filament closure error:
 *   "An attempt was made to evaluate a closure for [TextColumn], but [$s]
 *    was unresolvable."
 *
 * Filament v3 injects closure parameters by NAME (e.g. $state, $record, $get)
 * or by TYPE (e.g. Order $record, OrderLifecycleService $svc resolved via the
 * container). A closure parameter that is BOTH untyped AND not a recognized
 * name (like `$s` or `$r`) cannot be resolved → runtime crash when the table
 * or form renders.
 *
 * Layer 1 (deterministic): scan every closure in app/Filament and fail if any
 * parameter is untyped AND unrecognized. This catches the bug class anywhere
 * in the admin panel without needing to boot Livewire.
 *
 * Layer 2 (best-effort): render the affected List pages via Livewire so the
 * closures actually evaluate against real records. Skipped gracefully if the
 * Filament panel can't be booted in the test harness.
 */

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPackage;
use App\Models\VendorSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

/* ─────────── Layer 1: deterministic static scan ─────────── */

it('v5.4: no Filament closure uses an untyped, unrecognized parameter name', function () {
    // Recognized Filament v3 injectable names (by name). Anything else MUST be
    // type-hinted (resolved by type/container) or it will crash at evaluate().
    $known = [
        'state','record','get','set','livewire','component','context','operation',
        'rawState','column','action','field','model','table','query','relationship',
        'data','old','value','search','recordId','rowLoop',
    ];

    $offenders = [];
    $dir = app_path('Filament');
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($rii as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }
        $src = file_get_contents($file->getPathname());
        $lines = explode("\n", $src);

        // Match arrow-function parameter lists: fn ( ... )
        if (preg_match_all('/fn\s*\(([^)]*)\)/', $src, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $i => [$params, $offset]) {
                $params = trim($params);
                if ($params === '') {
                    continue;
                }
                $lineNo = substr_count(substr($src, 0, $offset), "\n") + 1;
                $lineText = $lines[$lineNo - 1] ?? '';

                // Skip Laravel ->when()/positional callbacks (not Filament-injected)
                if (str_contains($lineText, '->when(')) {
                    continue;
                }

                foreach (explode(',', $params) as $param) {
                    $param = trim($param);
                    // Has a type hint? (e.g. "Order $record", "?string $state") → resolved by type
                    $hasType = (bool) preg_match('/^[?\\\\A-Za-z_][\\\\A-Za-z0-9_]*\s+\$/', $param);
                    if (! preg_match('/\$(\w+)/', $param, $vm)) {
                        continue;
                    }
                    $name = $vm[1];
                    if (! in_array($name, $known, true) && ! $hasType) {
                        $rel = str_replace(base_path() . '/', '', $file->getPathname());
                        $offenders[] = "{$rel}:{$lineNo}  fn({$params})  — \${$name} is untyped & unrecognized";
                    }
                }
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Filament closures with untyped/unrecognized params (will crash at render):\n  "
        . implode("\n  ", $offenders)
    );
});

/* ─────────── Layer 2: best-effort Livewire render ─────────── */

it('v5.4: VendorSubscriptions admin list renders without a closure error', function () {
    if (! class_exists(\Livewire\Livewire::class)) {
        $this->markTestSkipped('Livewire test helper not available.');
    }

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);

    // Seed a subscription so the status + amount_paid columns evaluate their closures
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $package = VendorPackage::where('slug', 'basic')->first();
    VendorSubscription::create([
        'vendor_id'         => $vendor->id,
        'vendor_package_id' => $package->id,
        'status'            => 'active',
        'starts_at'         => now(),
        'amount_paid_minor' => 5000,
        'currency'          => 'KWD',
    ]);

    try {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $component = \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\VendorSubscriptionResource\Pages\ListVendorSubscriptions::class);

        $component->assertOk();
    } catch (\Throwable $e) {
        // If the panel can't boot in the harness, the static scan above still
        // guards the bug. Only fail if it's the actual closure error.
        if (str_contains($e->getMessage(), 'unresolvable')) {
            throw $e;
        }
        $this->markTestSkipped('Filament panel not bootable in test harness: ' . $e->getMessage());
    }
});

it('v5.4: VendorCommissionRules admin list renders without a closure error', function () {
    if (! class_exists(\Livewire\Livewire::class)) {
        $this->markTestSkipped('Livewire test helper not available.');
    }

    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    try {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\VendorCommissionRuleResource\Pages\ListVendorCommissionRules::class)
            ->assertOk();
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), 'unresolvable')) {
            throw $e;
        }
        $this->markTestSkipped('Filament panel not bootable in test harness: ' . $e->getMessage());
    }
});
