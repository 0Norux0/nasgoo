<?php

declare(strict_types=1);

/**
 * Phase 8 v8.2 — migration / index name regression test.
 *
 * This file does NOT add new feature coverage — its sole job is to
 * prevent the v8.1 → v8.2 bug from recurring: Laravel auto-generated
 * compound index names > 64 chars, MySQL rejected migrate:fresh.
 *
 * Strategy: use the DB schema-builder to inspect the actual indexes
 * that were created, and assert they exist by the explicit short names
 * we gave them. If a future contributor removes the explicit name arg,
 * the auto-generated name will be different and this test will fail
 * loud with a clear message about MySQL identifier limits.
 */

use App\Models\Product;
use App\Models\ServiceAvailability;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Helper: list every index on a table, regardless of DB engine.
 * Returns an array of index names (lowercased).
 */
function v82IndexNames(string $table): array
{
    // Schema::getIndexes() returns [{'name': '...', 'columns': [...]}, ...]
    // It works against any supported engine (Postgres, MySQL, SQLite).
    $indexes = Schema::getIndexes($table);
    return array_map(fn ($i) => strtolower($i['name']), $indexes);
}

it('Phase 8 v8.2: service_provider_assignments uses short explicit index names', function () {
    $names = v82IndexNames('service_provider_assignments');

    // Explicit names from migration 03 — guard against contributor edits
    // that strip the second arg and let auto-generation kick in.
    expect($names)->toContain('spa_provider_product_unique');
    expect($names)->toContain('spa_product_provider_idx');

    // The Laravel-auto-generated long names MUST NOT exist.
    expect($names)->not->toContain('service_provider_assignments_service_provider_id_product_id_unique');
    expect($names)->not->toContain('service_provider_assignments_product_id_service_provider_id_index');
});

it('Phase 8 v8.2: service_bookings uses short explicit index names', function () {
    $names = v82IndexNames('service_bookings');

    expect($names)->toContain('sb_provider_date_time_idx');
    expect($names)->toContain('sb_vendor_status_date_idx');
    expect($names)->toContain('sb_user_status_idx');

    expect($names)->not->toContain('service_bookings_service_provider_id_booked_for_date_booked_for_time_index');
});

it('Phase 8 v8.2: service_availabilities uses short explicit unique name', function () {
    $names = v82IndexNames('service_availabilities');

    expect($names)->toContain('sa_provider_dow_unique');
    expect($names)->not->toContain('service_availabilities_service_provider_id_day_of_week_unique');
});

it('Phase 8 v8.2: every index name on Phase 8 tables is ≤ 60 characters', function () {
    // Defensive: enumerate every index across every Phase 8 table and
    // assert it's safely under MySQL's 64-char limit. Catches any
    // future migration that adds a compound index without an explicit
    // name on a long-named table.
    $tables = [
        'service_details', 'service_providers', 'service_provider_assignments',
        'service_availabilities', 'service_blocked_dates', 'service_bookings',
    ];

    $longNames = [];
    foreach ($tables as $table) {
        foreach (Schema::getIndexes($table) as $idx) {
            if (strlen($idx['name']) > 60) {
                $longNames[] = $table . '.' . $idx['name'] . ' (' . strlen($idx['name']) . ' chars)';
            }
        }
    }

    expect($longNames)->toBe([], "Found index names > 60 chars: " . implode(', ', $longNames));
});

it('Phase 8 v8.2: the unique constraint on service_provider_assignments still rejects duplicates', function () {
    // Critical regression: shortening the name must NOT silently remove
    // the uniqueness rule. Build two providers under the same vendor,
    // attach both to the same product — first attach succeeds, second
    // identical attach must fail with a unique-constraint violation.
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $service = Product::factory()->create([
        'vendor_id' => $vendor->id, 'type' => Product::TYPE_SERVICE,
        'status'    => 'published',
    ]);
    $provider = ServiceProvider::factory()->create(['vendor_id' => $vendor->id]);

    // First attach — succeeds
    DB::table('service_provider_assignments')->insert([
        'service_provider_id' => $provider->id, 'product_id' => $service->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Second identical insert — must throw on the unique constraint
    expect(fn () => DB::table('service_provider_assignments')->insert([
        'service_provider_id' => $provider->id, 'product_id' => $service->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('Phase 8 v8.2: the unique constraint on service_availabilities still rejects duplicates', function () {
    // Same idea, this time for service_availabilities. Two rows for
    // (provider, day_of_week=1) must collide.
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);
    $provider = ServiceProvider::factory()->create(['vendor_id' => $vendor->id]);

    ServiceAvailability::create([
        'service_provider_id'   => $provider->id, 'day_of_week' => 1,
        'start_time'            => '10:00:00', 'end_time' => '20:00:00',
        'slot_duration_minutes' => 30, 'max_bookings_per_slot' => 1,
        'is_active'             => true,
    ]);

    expect(fn () => ServiceAvailability::create([
        'service_provider_id'   => $provider->id, 'day_of_week' => 1,
        'start_time'            => '11:00:00', 'end_time' => '19:00:00',
        'slot_duration_minutes' => 60, 'max_bookings_per_slot' => 2,
        'is_active'             => true,
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
