<?php

declare(strict_types=1);

use App\Jobs\SendVendorIntelligenceDigest;
use App\Mail\VendorIntelligenceDigestMail;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;
use App\Models\VendorIntelligenceSummary;
use App\Services\Settings\SiteSettingsService;
use App\Services\VendorIntelligence\VendorIntelligenceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ═══════════════════════════════════════════════════════════════════════════
// v11B.4.3 FINAL REPAIR — Pest suite covering three remaining issues
// ═══════════════════════════════════════════════════════════════════════════

function p11b43_seed(): void
{
    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b43_vendor(string $status = Vendor::STATUS_APPROVED, ?string $email = null): Vendor
{
    p11b43_seed();
    $u = User::factory()->create([
        'email' => 'p11b43-v-' . uniqid() . '@p11b43.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('vendor');
    return Vendor::create([
        'user_id' => $u->id,
        'business_name' => 'V43-' . uniqid(),
        'business_email' => $email ?? ('biz-' . uniqid() . '@p11b43.test'),
        'business_type' => 'company', 'country' => 'KW', 'status' => $status,
    ])->fresh();
}

function p11b43_super_admin(): User
{
    p11b43_seed();
    $u = User::factory()->create([
        'email' => 'p11b43-a-' . uniqid() . '@p11b43.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p11b43_product(Vendor $vendor, array $overrides = []): Product
{
    unset($overrides['images']);
    return Product::factory()->create(array_merge([
        'vendor_id' => $vendor->id,
        'status' => 'published',
        'type' => 'simple',
        'track_stock' => true,
        'stock' => 100,
        'price_minor' => 1500,
        'currency' => 'KWD',
        'short_description' => 'sd', 'description' => 'd',
    ], $overrides))->fresh();
}

// ═══════════════════════════════════════════════════════════════════════════
// FIX 1 — Admin Vendor Intelligence tab appears + updates persist
// ═══════════════════════════════════════════════════════════════════════════

it('§Fix1.1 admin Site Settings Inertia payload includes vendor_intelligence settings', function () {
    $a = p11b43_super_admin();
    $r = test()->actingAs($a)->get('/admin/site-settings')->assertOk();
    $r->assertInertia(fn ($pg) => $pg
        ->component('Admin/SiteSettings/Index')
        ->has('settings.vendor_intelligence')
        ->has('settings.vendor_intelligence.enabled')
        ->has('settings.vendor_intelligence.low_stock_threshold')
        ->etc()
    );
});

it('§Fix1.2 admin POST /admin/site-settings/vendor_intelligence returns 302 (was 422)', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)
        ->post('/admin/site-settings/vendor_intelligence', [
            'enabled' => true, 'low_stock_threshold' => 8,
        ])->assertStatus(302);
});

it('§Fix1.3 admin can update low_stock_threshold and value persists', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)->post('/admin/site-settings/vendor_intelligence', [
        'low_stock_threshold' => 12,
    ]);
    expect((int) app(SiteSettingsService::class)->get('vendor_intelligence.low_stock_threshold'))->toBe(12);
});

it('§Fix1.4 admin can update fast_moving_days', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)->post('/admin/site-settings/vendor_intelligence', [
        'fast_moving_days' => 45,
    ]);
    expect((int) app(SiteSettingsService::class)->get('vendor_intelligence.fast_moving_days'))->toBe(45);
});

it('§Fix1.5 admin can toggle enabled flag', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)->post('/admin/site-settings/vendor_intelligence', [
        'enabled' => false,
    ]);
    Cache::flush();
    expect(app(SiteSettingsService::class)->get('vendor_intelligence.enabled'))->toBeFalse();
});

it('§Fix1.6 admin can update scheduler_enabled', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)->post('/admin/site-settings/vendor_intelligence', [
        'scheduler_enabled' => false,
    ]);
    Cache::flush();
    expect(app(SiteSettingsService::class)->get('vendor_intelligence.scheduler_enabled'))->toBeFalse();
});

it('§Fix1.7 negative low_stock_threshold rejected with validation error', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)
        ->post('/admin/site-settings/vendor_intelligence', ['low_stock_threshold' => -5])
        ->assertSessionHasErrors('low_stock_threshold');
});

it('§Fix1.8 fast_moving_days above sane max rejected', function () {
    $a = p11b43_super_admin();
    test()->actingAs($a)
        ->post('/admin/site-settings/vendor_intelligence', ['fast_moving_days' => 5000])
        ->assertSessionHasErrors('fast_moving_days');
});

it('§Fix1.9 non-admin (customer) cannot open site settings', function () {
    p11b43_seed();
    $c = User::factory()->create();
    $c->assignRole('customer');
    test()->actingAs($c)->get('/admin/site-settings')->assertForbidden();
});

it('§Fix1.10 non-admin cannot POST vendor_intelligence settings', function () {
    p11b43_seed();
    $c = User::factory()->create();
    $c->assignRole('customer');
    test()->actingAs($c)
        ->post('/admin/site-settings/vendor_intelligence', ['low_stock_threshold' => 3])
        ->assertForbidden();
});

it('§Fix1.11 threshold update affects generation output', function () {
    $a = p11b43_super_admin();
    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 8]);   // above default (5), below new (10)

    // Baseline: at default threshold=5, stock=8 → NO low_stock
    Artisan::call('vendor-intelligence:generate', ['--vendor' => $v->id]);
    $before = Alert::where('vendor_id', $v->id)
        ->where('alert_type', Alert::TYPE_LOW_STOCK)->count();
    expect($before)->toBe(0);

    // Raise threshold to 10 → stock=8 becomes low
    test()->actingAs($a)->post('/admin/site-settings/vendor_intelligence', [
        'low_stock_threshold' => 10,
    ]);
    Cache::flush();
    Artisan::call('vendor-intelligence:generate', ['--vendor' => $v->id]);
    $after = Alert::where('vendor_id', $v->id)
        ->where('alert_type', Alert::TYPE_LOW_STOCK)
        ->where('status', 'active')->count();
    expect($after)->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════
// FIX 2 — Email digest
// ═══════════════════════════════════════════════════════════════════════════

it('§Fix2.1 vendor with active alerts receives digest email (with digest_emails_enabled=true)', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);   // creates a critical alert
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);

    Mail::assertSent(VendorIntelligenceDigestMail::class,
        fn ($mail) => $mail->hasTo($v->business_email));
});

it('§Fix2.2 vendor with NO alerts receives no email', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 100]);   // healthy — no critical alerts
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);

    Mail::assertNothingSent();
});

it('§Fix2.3 suspended vendor receives no email even with alerts', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor(Vendor::STATUS_SUSPENDED);
    // Even manually plant a summary + critical alert
    $summary = VendorIntelligenceSummary::create([
        'vendor_id' => $v->id, 'active_alerts_count' => 1,
    ]);
    Alert::create([
        'vendor_id' => $v->id, 'alert_type' => 'out_of_stock',
        'entity_type' => 'product', 'entity_id' => 1,
        'priority' => 'critical', 'status' => 'active',
        'evidence' => [], 'active_dedupe_key' => 'x' . $v->id,
    ]);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertNothingSent();
});

it('§Fix2.4 pending vendor receives no email', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor(Vendor::STATUS_PENDING);
    VendorIntelligenceSummary::create(['vendor_id' => $v->id, 'active_alerts_count' => 5]);
    Alert::create([
        'vendor_id' => $v->id, 'alert_type' => 'out_of_stock',
        'entity_type' => 'product', 'entity_id' => 1,
        'priority' => 'critical', 'status' => 'active',
        'evidence' => [], 'active_dedupe_key' => 'y' . $v->id,
    ]);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertNothingSent();
});

it('§Fix2.5 vendor opted out receives no email', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);
    VendorIntelligenceSummary::where('vendor_id', $v->id)
        ->update(['email_opted_out' => true]);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertNothingSent();
});

it('§Fix2.6 digest suppressed when digest_emails_enabled=false (master switch off)', function () {
    Mail::fake();
    // No explicit set — default false
    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertNothingSent();
});

it('§Fix2.7 digest suppressed when vendor_intelligence.enabled=false', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    $svc->set('vendor_intelligence.enabled', false, 1);
    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertNothingSent();
});

it('§Fix2.8 throttle prevents duplicate digest within window', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);
    $svc->set('vendor_intelligence.digest_throttle_hours', 24, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertSent(VendorIntelligenceDigestMail::class, 1);

    // Second dispatch immediately — throttled
    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertSent(VendorIntelligenceDigestMail::class, 1);   // still 1
});

it('§Fix2.9 last_digest_sent_at recorded after successful send', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    $s = VendorIntelligenceSummary::where('vendor_id', $v->id)->first();
    expect($s->last_digest_sent_at)->not->toBeNull();
});

it('§Fix2.10 --send-emails command flag dispatches jobs (queue driver)', function () {
    Queue::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v1 = p11b43_vendor();
    $v2 = p11b43_vendor();
    p11b43_product($v1, ['stock' => 0]);
    p11b43_product($v2, ['stock' => 0]);

    Artisan::call('vendor-intelligence:generate', ['--send-emails' => true]);

    Queue::assertPushed(SendVendorIntelligenceDigest::class, 2);
});

it('§Fix2.11 job is ShouldQueue (async by design)', function () {
    $reflection = new \ReflectionClass(SendVendorIntelligenceDigest::class);
    expect($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
});

it('§Fix2.12 digest content excludes customer PII (evidence PII-filtered)', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);

    $v = p11b43_vendor();
    $p = p11b43_product($v, ['stock' => 0]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    // Poison the alert evidence with a customer field
    Alert::where('vendor_id', $v->id)->update([
        'evidence' => [
            'product_name' => 'Widget',
            'stock' => 0,
            'customer_email' => 'leak@example.com',   // must be stripped
            'customer_name' => 'Alice Doe',
        ],
    ]);

    SendVendorIntelligenceDigest::dispatchSync($v->id);

    Mail::assertSent(VendorIntelligenceDigestMail::class, function ($mail) {
        $topAlerts = $mail->data['top_alerts'] ?? [];
        foreach ($topAlerts as $a) {
            $ev = $a['evidence'] ?? [];
            if (isset($ev['customer_email']) || isset($ev['customer_name'])) return false;
        }
        return true;
    });
});

it('§Fix2.13 digest_min_critical=0 sends even without critical alerts', function () {
    Mail::fake();
    $svc = app(SiteSettingsService::class);
    $svc->set('vendor_intelligence.digest_emails_enabled', true, 1);
    $svc->set('vendor_intelligence.digest_min_critical', 0, 1);

    $v = p11b43_vendor();
    p11b43_product($v, ['stock' => 3]);   // low_stock (not critical)
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    SendVendorIntelligenceDigest::dispatchSync($v->id);
    Mail::assertSent(VendorIntelligenceDigestMail::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// FIX 3 — Product translation stale observer
// ═══════════════════════════════════════════════════════════════════════════

it('§Fix3.1 product translation create marks vendor stale', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->toBeNull();

    ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'اسم عربي', 'status' => ProductTranslation::STATUS_PENDING,
    ]);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->not->toBeNull();
});

it('§Fix3.2 product translation value update marks vendor stale', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    $t = ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v1', 'status' => ProductTranslation::STATUS_PENDING,
    ]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->toBeNull();

    $t->update(['value' => 'v2']);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->not->toBeNull();
});

it('§Fix3.3 product translation approval marks vendor stale', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    $t = ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'description',
        'value' => 'وصف', 'status' => ProductTranslation::STATUS_PENDING,
    ]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    $t->update(['status' => ProductTranslation::STATUS_APPROVED]);
    $stale = VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at');
    $reason = VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_reason');
    expect($stale)->not->toBeNull();
    expect($reason)->toContain('translation');
});

it('§Fix3.4 product translation deletion marks vendor stale', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    $t = ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v', 'status' => ProductTranslation::STATUS_APPROVED,
    ]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    $t->delete();
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->not->toBeNull();
});

it('§Fix3.5 timestamp-only translation touch does NOT mark stale', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    $t = ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v', 'status' => ProductTranslation::STATUS_APPROVED,
    ]);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    $t->touch();   // updates updated_at only — not value/status/reviewed_by
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->toBeNull();
});

it('§Fix3.6 orphaned translation (product deleted) does not crash', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    $t = ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v', 'status' => ProductTranslation::STATUS_APPROVED,
    ]);
    $p->forceDelete();

    // Update translation whose product no longer exists — must not throw
    $threw = false;
    try {
        $t->update(['value' => 'v2']);
    } catch (\Throwable) { $threw = true; }
    expect($threw)->toBeFalse();
});

it('§Fix3.7 stale reason recorded for translation change', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v', 'status' => ProductTranslation::STATUS_PENDING,
    ]);
    $reason = VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_reason');
    expect($reason)->toContain('translation');
});

it('§Fix3.8 --stale-only regenerates the translation-marked vendor', function () {
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);

    // Mark stale via translation edit
    ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'v', 'status' => ProductTranslation::STATUS_PENDING,
    ]);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->not->toBeNull();

    Artisan::call('vendor-intelligence:generate', ['--stale-only' => true]);
    // Regeneration clears stale_at
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->toBeNull();
});

// ═══════════════════════════════════════════════════════════════════════════
// REGRESSION — v11B.4.2 fixes still intact
// ═══════════════════════════════════════════════════════════════════════════

it('§Reg.1 approved vendor still gets 200 on /vendor/intelligence', function () {
    $v = p11b43_vendor();
    test()->actingAs($v->user)->get('/vendor/intelligence')->assertOk();
});

it('§Reg.2 pending vendor still blocked', function () {
    $v = p11b43_vendor(Vendor::STATUS_PENDING);
    test()->actingAs($v->user)->get('/vendor/intelligence')->assertForbidden();
});

it('§Reg.3 scheduler still lists both vendor-intelligence commands', function () {
    Artisan::call('schedule:list');
    $out = Artisan::output();
    expect($out)->toContain('vendor-intelligence:generate');
    expect($out)->toContain('vendor-intelligence:prune');
});

it('§Reg.4 UNIQUE dedupe index still enforced', function () {
    $indexes = collect(Schema::getIndexes('vendor_intelligence_alerts'))
        ->firstWhere('name', 'via_active_dedupe_uniq');
    expect($indexes['unique'] ?? false)->toBeTrue();
});

it('§Reg.5 v11B.4.2 observers (Product/Order/Vendor) still registered', function () {
    // Trigger ProductObserver material update — should still mark stale
    $v = p11b43_vendor();
    $p = p11b43_product($v);
    app(VendorIntelligenceManager::class)->regenerateForVendor($v);
    $p->update(['stock' => 5]);
    expect(VendorIntelligenceSummary::where('vendor_id', $v->id)->value('stale_at'))->not->toBeNull();
});

it('§Reg.6 v11B.4.2 variant alert types still exist', function () {
    expect(Alert::TYPE_VARIANT_OUT_OF_STOCK)->toBe('variant_out_of_stock');
});
