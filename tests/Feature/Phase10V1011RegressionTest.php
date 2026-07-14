<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1011_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1011_admin(): User
{
    p1011_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p1011_vendor_user(): array
{
    p1011_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    $v = Vendor::create([
        'user_id'        => $u->id,
        'business_name'  => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p1011.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => Vendor::STATUS_APPROVED,
    ]);
    return [$u->fresh(), $v->fresh()];
}

function p1011_customer(): User
{
    p1011_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    return $u->fresh();
}

// ─── §5 — Reports payout query uses correct column ────────────────────

it('admin /admin/reports loads without SQL error on empty payouts', function () {
    $admin = p1011_admin();
    // No payout rows; query must still return zeros, NOT a column-not-found error
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
    // The error was: SQLSTATE[42S22] Column not found: 1054 'amount_minor'
    // If 200, the SQL ran clean.
});

it('admin /admin/reports loads when payout rows exist (pending + approved + paid + rejected)', function () {
    $admin = p1011_admin();
    [, $vendor] = p1011_vendor_user();

    // Seed one row per status
    foreach ([
        ['status' => 'pending',  'amount' => 50_000],
        ['status' => 'approved', 'amount' => 30_000],
        ['status' => 'paid',     'amount' => 20_000],
        ['status' => 'rejected', 'amount' => 10_000],
    ] as $r) {
        VendorPayoutRequest::create([
            'vendor_id'              => $vendor->id,
            'requested_amount_minor' => $r['amount'],
            'currency'               => 'KWD',
            'status'                 => $r['status'],
            'payout_method'          => 'bank_transfer',
            'requested_at'           => now(),
        ]);
    }

    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
});

it('payout amount_sum uses requested_amount_minor (verified via the service)', function () {
    [, $vendor] = p1011_vendor_user();
    VendorPayoutRequest::create([
        'vendor_id'              => $vendor->id,
        'requested_amount_minor' => 99_999,
        'currency'               => 'KWD',
        'status'                 => 'pending',
        'payout_method'          => 'bank_transfer',
        'requested_at'           => now(),
    ]);

    $svc = app(\App\Domain\Reports\ReportsService::class);
    $summary = $svc->adminPayoutSummary(now()->subDay(), now()->addDay());

    expect($summary['pending_amount_minor'])->toBe(99_999);
    expect($summary['pending_count'])->toBe(1);
});

it('ReportsService.php source no longer contains SUM(amount_minor) on payouts', function () {
    $src = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    // The dev-reported failing query was: COALESCE(SUM(amount_minor), 0)
    // The fix uses SUM(requested_amount_minor). Regression guard:
    expect($src)->not->toContain('SUM(amount_minor)');
    expect(substr_count($src, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2);
});

// ─── §3 — Vendor order dropdown availability ─────────────────────────

it('vendor order show returns status_options as a server-computed prop', function () {
    [$u, $vendor] = p1011_vendor_user();

    // Create an order with one item belonging to this vendor
    $order = Order::create([
        'number'         => 'TST-' . uniqid(),
        'user_id'        => User::factory()->create()->id,
        'status'         => Order::STATUS_PAID,
        'payment_status' => 'paid',
        'fulfillment_status' => Order::FUL_UNFULFILLED,
        'currency'       => 'KWD',
        'subtotal_minor' => 10000,
        'shipping_minor' => 0,
        'tax_minor'      => 0,
        'discount_minor' => 0,
        'total_minor'    => 10000,
    ]);
    OrderItem::create([
        'order_id'           => $order->id,
        'vendor_id'          => $vendor->id,
        'product_id'         => null,
        'product_name'       => 'Test',
        'quantity'           => 1,
        'unit_price_minor'   => 10000,
        'line_total_minor'   => 10000,
        'commission_amount_minor' => 2000,
        'vendor_earning_minor'    => 8000,
        'commission_percent' => 20,
        'fulfillment_status' => OrderItem::FUL_UNFULFILLED,
    ]);

    $resp = $this->actingAs($u)->get("/vendor/orders/{$order->id}");
    $resp->assertOk();
    $resp->assertInertia(fn ($page) => $page->has('status_options', 4));
});

it('status_options marks confirm available when order.status is paid', function () {
    [$u, $vendor] = p1011_vendor_user();
    $order = Order::create([
        'number'                 => 'TST-' . uniqid(),
        'user_id'                => User::factory()->create()->id,
        'status'                 => Order::STATUS_PAID,
        'payment_status'         => 'paid',
        'fulfillment_status'     => Order::FUL_UNFULFILLED,
        'currency'               => 'KWD',
        'subtotal_minor'         => 1000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor'         => 0,    'total_minor'    => 1000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id, 'product_id' => null,
        'product_name' => 'X', 'quantity' => 1, 'unit_price_minor' => 1000,
        'line_total_minor' => 1000, 'commission_amount_minor' => 200,
        'vendor_earning_minor' => 800, 'commission_percent' => 20,
        'fulfillment_status' => OrderItem::FUL_UNFULFILLED,
    ]);

    $resp = $this->actingAs($u)->get("/vendor/orders/{$order->id}");
    $resp->assertInertia(fn ($page) => $page
        ->where('status_options.1.value', 'confirm')
        ->where('status_options.1.available', true)
    );
});

it('status_options marks ship available when vendor has unfulfilled items', function () {
    [$u, $vendor] = p1011_vendor_user();
    $order = Order::create([
        'number'             => 'TST-' . uniqid(),
        'user_id'            => User::factory()->create()->id,
        'status'             => Order::STATUS_CONFIRMED,
        'payment_status'     => 'paid',
        'fulfillment_status' => Order::FUL_UNFULFILLED,
        'currency'           => 'KWD',
        'subtotal_minor'     => 1000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor'     => 0,    'total_minor'    => 1000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id, 'product_id' => null,
        'product_name' => 'X', 'quantity' => 1, 'unit_price_minor' => 1000,
        'line_total_minor' => 1000, 'commission_amount_minor' => 200,
        'vendor_earning_minor' => 800, 'commission_percent' => 20,
        'fulfillment_status' => OrderItem::FUL_UNFULFILLED,
    ]);

    $resp = $this->actingAs($u)->get("/vendor/orders/{$order->id}");
    $resp->assertInertia(fn ($page) => $page
        ->where('status_options.2.value', 'ship')
        ->where('status_options.2.available', true)
    );
});

it('status_options marks deliver available when order.status is shipped', function () {
    [$u, $vendor] = p1011_vendor_user();
    $order = Order::create([
        'number'             => 'TST-' . uniqid(),
        'user_id'            => User::factory()->create()->id,
        'status'             => Order::STATUS_SHIPPED,
        'payment_status'     => 'paid',
        'fulfillment_status' => Order::FUL_FULFILLED,
        'currency'           => 'KWD',
        'subtotal_minor'     => 1000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor'     => 0,    'total_minor'    => 1000,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id, 'product_id' => null,
        'product_name' => 'X', 'quantity' => 1, 'unit_price_minor' => 1000,
        'line_total_minor' => 1000, 'commission_amount_minor' => 200,
        'vendor_earning_minor' => 800, 'commission_percent' => 20,
        'fulfillment_status' => OrderItem::FUL_FULFILLED,
    ]);

    $resp = $this->actingAs($u)->get("/vendor/orders/{$order->id}");
    $resp->assertInertia(fn ($page) => $page
        ->where('status_options.3.value', 'deliver')
        ->where('status_options.3.available', true)
    );
});

it('Show.tsx source no longer references non-existent shipped fulfillment_status', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Show.tsx'));
    // Pre-v10.11 had `order.fulfillment_status === 'shipped'` — 'shipped' is
    // an ORDER status, not a fulfillment status. Regression guard:
    expect($src)->not->toContain("fulfillment_status === 'shipped'");
    expect($src)->toContain('status_options');
});

// ─── §4 — Support ticket reply lazy-load defenses ────────────────────

it('customer reply redirects to show URL explicitly (not back())', function () {
    $customer = p1011_customer();
    $ticket = SupportTicket::create([
        'user_id'     => $customer->id,
        'number'      => SupportTicket::generateNumber(),
        'ticket_type' => 'general_inquiry',
        'subject'     => 'Hi',
        'priority'    => 'normal',
        'status'      => 'open',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $customer->id,
        'body' => 'first', 'author_role' => 'customer', 'is_internal' => false,
        'attachments' => [],
    ]);

    $resp = $this->actingAs($customer)
        ->from("/tickets/{$ticket->id}")
        ->post("/tickets/{$ticket->id}/reply", ['body' => 'reply 1']);

    $resp->assertRedirect("/tickets/{$ticket->id}");
});

it('vendor reply redirects to vendor show URL explicitly', function () {
    [$u, $vendor] = p1011_vendor_user();
    $ticket = SupportTicket::create([
        'user_id'     => $u->id,
        'vendor_id'   => $vendor->id,
        'number'      => SupportTicket::generateNumber(),
        'ticket_type' => 'general_inquiry',
        'subject'     => 'Vendor hi',
        'priority'    => 'normal',
        'status'      => 'open',
    ]);

    $resp = $this->actingAs($u)
        ->from("/vendor/tickets/{$ticket->id}")
        ->post("/vendor/tickets/{$ticket->id}/reply", ['body' => 'vendor reply']);

    $resp->assertRedirect("/vendor/tickets/{$ticket->id}");
});

it('customer ticket show eager-loads messages.user (regression guard for lazy-load violation)', function () {
    $customer = p1011_customer();
    $ticket = SupportTicket::create([
        'user_id'     => $customer->id,
        'number'      => SupportTicket::generateNumber(),
        'ticket_type' => 'general_inquiry',
        'subject'     => 'Hi',
        'priority'    => 'normal',
        'status'      => 'open',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $customer->id,
        'body' => 'msg1', 'author_role' => 'customer', 'is_internal' => false,
        'attachments' => [],
    ]);

    // Hard-enforce no lazy loading globally — this mirrors prod assertion
    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    try {
        $resp = $this->actingAs($customer)->get("/tickets/{$ticket->id}");
        $resp->assertOk();
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

it('vendor ticket show eager-loads messages.user', function () {
    [$u, $vendor] = p1011_vendor_user();
    $ticket = SupportTicket::create([
        'user_id'     => $u->id,
        'vendor_id'   => $vendor->id,
        'number'      => SupportTicket::generateNumber(),
        'ticket_type' => 'general_inquiry',
        'subject'     => 'Vh',
        'priority'    => 'normal',
        'status'      => 'open',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $u->id,
        'body' => 'v msg', 'author_role' => 'vendor', 'is_internal' => false,
        'attachments' => [],
    ]);

    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    try {
        $resp = $this->actingAs($u)->get("/vendor/tickets/{$ticket->id}");
        $resp->assertOk();
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

it('Filament ViewSupportTicket action callbacks load messages.user defensively (source check)', function () {
    $src = file_get_contents(app_path('Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php'));
    // Each mutating action (reply, changeStatus, changePriority, assign)
    // must include an explicit $record->load(['messages.user...]) call so
    // the post-action Livewire re-render of the Infolist doesn't lazy-load.
    $count = substr_count($src, "messages.user:id,name,email");
    expect($count)->toBeGreaterThanOrEqual(4);
});

// ─── §2 — Performance: permissions removed from default Inertia share ──

it('HandleInertiaRequests no longer calls getAllPermissions on every request', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    // The dev-reported source of slowness: $request->user()->getAllPermissions()
    // pluck('name')->toArray() ran on every Inertia render. Removed in v10.11.
    expect($src)->not->toMatch('/[\'"]permissions[\'"]\s*=>\s*\$request->user\(\)->getAllPermissions/');
});

it('admin can still read auth.user.is_admin from shared props (regression guard)', function () {
    $admin = p1011_admin();
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
    $resp->assertInertia(fn ($page) => $page
        ->where('auth.user.is_admin', true)
        ->where('auth.user.email', $admin->email)
        ->has('auth.user.roles')
    );
});

// ─── Cross-cutting ──

it('VERSION reports Phase 10 v10.11', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.11');
});
