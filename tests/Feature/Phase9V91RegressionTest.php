<?php

declare(strict_types=1);

use App\Domain\Order\OrderLifecycleService;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// v8.5 — every helper prefixed `p91_` to avoid collision with v9.0's `p9_`
// and the 22 existing helpers.

function p91Customer(string $email = 'p91-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

function p91Vendor(string $email = 'p91-vendor@test'): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p91Product(Vendor $v, string $slug = 'p91-prod'): Product
{
    return Product::factory()->create([
        'vendor_id'   => $v->id,
        'slug'        => $slug . '-' . $v->id,
        'name'        => 'Phase 9.1 test product',
        'price_minor' => 50000,
        'currency'    => 'KWD',
    ]);
}

//
// BUG #1 — Filament coupon form must open without BindingResolutionException
//

it('the CouponResource code field uses a properly-typed closure (no $s)', function () {
    // Static-source assertion — the offending closure was `fn ($s) => ...`.
    // The fix is `fn (?string $state): string => ...`. Confirm the file
    // no longer contains the bad pattern.
    $src = file_get_contents(base_path('app/Filament/Resources/CouponResource.php'));
    expect($src)->not->toMatch('/fn\s*\(\s*\$s\s*\)/');
    expect($src)->toContain('?string $state');
});

it('admin can create a coupon via mass assignment (proves the model + columns are wired)', function () {
    $c = Coupon::create([
        'code'           => 'P91NEW',
        'discount_type'  => 'percentage',
        'discount_value' => 15,
        'is_active'      => true,
        'currency'       => 'KWD',
        'per_user_limit' => 1,
    ]);
    expect($c->fresh()->code)->toBe('P91NEW');
});

//
// BUG #3 — Cart page exposes coupon UI
//

it('the cart presenter exposes coupon + payable fields', function () {
    $user = p91Customer();
    $cart = Cart::create([
        'user_id'        => $user->id,
        'currency'       => 'KWD',
        'subtotal_minor' => 100000,
        'items_count'    => 1,
    ]);
    $coupon = Coupon::create([
        'code'           => 'P91SHOW',
        'discount_type'  => 'percentage',
        'discount_value' => 10,
        'is_active'      => true,
        'currency'       => 'KWD',
        'per_user_limit' => 5,
    ]);

    $this->actingAs($user);
    $this->post('/cart/coupon', ['code' => 'P91SHOW'])->assertRedirect();

    $cart->refresh();
    expect($cart->coupon_id)->toBe($coupon->id);
    expect($cart->discount_minor)->toBe(10000);
    expect($cart->payableMinor())->toBe(90000);
});

it('the cart page contains a coupon input form (UI smoke)', function () {
    // Static check — the Cart/Show.tsx file must reference the coupon
    // form sub-component AND the data-testid markers used by manual QA.
    $src = file_get_contents(resource_path('js/Pages/Cart/Show.tsx'));
    expect($src)->toContain('CartCouponForm');
    expect($src)->toContain('data-testid="cart-coupon-input"');
    expect($src)->toContain('data-testid="cart-coupon-apply"');
});

//
// BUG #5 — Vendor confirm + deliver actions exist
//

it('vendor has confirm + deliver + ship route actions', function () {
    expect(\Illuminate\Support\Facades\Route::has('vendor.orders.confirm'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('vendor.orders.deliver'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('vendor.orders.ship'))->toBeTrue();
});

//
// BUG #6 — Write Review eligibility on delivered items
//

it('a delivered item exposes can_review=true; an undelivered one does not', function () {
    [, $vendor] = p91Vendor();
    $product = p91Product($vendor, 'p91-rev');
    $customer = p91Customer('p91-rev-cust@test');

    // 1. Undelivered order — NOT eligible
    $undelivered = Order::create([
        'number'             => 'P91-UND-001',
        'user_id'            => $customer->id,
        'status'             => 'paid',
        'payment_status'     => 'paid',
        'fulfillment_status' => 'shipped',
        'currency'           => 'KWD',
        'subtotal_minor'     => 50000,
        'shipping_minor'     => 0,
        'tax_minor'          => 0,
        'discount_minor'     => 0,
        'total_minor'        => 50000,
        'delivered_at'       => null,
    ]);
    OrderItem::factory()->create([
        'order_id'         => $undelivered->id,
        'product_id'       => $product->id,
        'product_name'     => $product->name,
        'quantity'         => 1,
        'unit_price_minor' => 50000,
        'line_total_minor' => 50000,
        'currency'         => 'KWD',
    ]);

    $this->actingAs($customer);
    $resp1 = $this->get("/orders/{$undelivered->id}");
    $resp1->assertOk();
    // Inertia page props inspection
    $props = $resp1->viewData('page')['props'] ?? [];
    $items = $props['order']['items'] ?? [];
    expect(count($items))->toBe(1);
    expect($items[0]['can_review'])->toBeFalse();

    // 2. Delivered order — IS eligible
    $delivered = Order::create([
        'number'             => 'P91-DEL-001',
        'user_id'            => $customer->id,
        'status'             => 'completed',
        'payment_status'     => 'paid',
        'fulfillment_status' => 'delivered',
        'currency'           => 'KWD',
        'subtotal_minor'     => 50000,
        'shipping_minor'     => 0,
        'tax_minor'          => 0,
        'discount_minor'     => 0,
        'total_minor'        => 50000,
        'delivered_at'       => now(),
    ]);
    OrderItem::factory()->create([
        'order_id'         => $delivered->id,
        'product_id'       => $product->id,
        'product_name'     => $product->name,
        'quantity'         => 1,
        'unit_price_minor' => 50000,
        'line_total_minor' => 50000,
        'currency'         => 'KWD',
    ]);

    $resp2 = $this->get("/orders/{$delivered->id}");
    $resp2->assertOk();
    $items2 = $resp2->viewData('page')['props']['order']['items'] ?? [];
    expect($items2[0]['can_review'])->toBeTrue();
    expect($items2[0]['product_slug'])->toBe($product->slug);
});

it('a customer cannot review the same product twice', function () {
    [, $vendor] = p91Vendor('p91-dup-vendor@test');
    $product = p91Product($vendor, 'p91-dup');
    $customer = p91Customer('p91-dup-cust@test');

    // Existing review
    ProductReview::create([
        'user_id'    => $customer->id,
        'product_id' => $product->id,
        'rating'     => 5,
        'body'       => 'Already reviewed.',
        'status'     => 'approved',
        'is_verified_purchase' => true,
        'approved_at' => now(),
    ]);

    // Delivered order with the same product
    $order = Order::create([
        'number'             => 'P91-DUP-001',
        'user_id'            => $customer->id,
        'status'             => 'completed',
        'payment_status'     => 'paid',
        'fulfillment_status' => 'delivered',
        'currency'           => 'KWD',
        'subtotal_minor'     => 50000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor'     => 0, 'total_minor' => 50000,
        'delivered_at'       => now(),
    ]);
    OrderItem::factory()->create([
        'order_id'         => $order->id,
        'product_id'       => $product->id,
        'product_name'     => $product->name,
        'quantity'         => 1,
        'unit_price_minor' => 50000,
        'line_total_minor' => 50000,
        'currency'         => 'KWD',
    ]);

    $this->actingAs($customer);
    $resp = $this->get("/orders/{$order->id}");
    $items = $resp->viewData('page')['props']['order']['items'] ?? [];
    expect($items[0]['can_review'])->toBeFalse();
    expect($items[0]['already_reviewed'])->toBeTrue();
});

//
// BUG #8 — Admin ticket page is view mode, not edit mode
//

it('SupportTicketResource only exposes a view page, not an edit page', function () {
    $pages = \App\Filament\Resources\SupportTicketResource::getPages();
    expect(array_keys($pages))->toContain('view');
    expect(array_keys($pages))->not->toContain('edit');
});

it('the customer\'s original ticket subject + body cannot be overwritten via ViewSupportTicket page', function () {
    // The ViewSupportTicket page MUST extend ViewRecord (read-only), not EditRecord.
    $rc = new ReflectionClass(\App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket::class);
    expect($rc->getParentClass()->getName())->toBe(\Filament\Resources\Pages\ViewRecord::class);

    // And the EditSupportTicket class no longer exists
    expect(class_exists(\App\Filament\Resources\SupportTicketResource\Pages\EditSupportTicket::class))->toBeFalse();
});

it('admin reply via SupportTicketService creates a NEW message, does not mutate the original', function () {
    $customer = p91Customer('p91-ticket-orig@test');
    $admin = User::factory()->create(['email' => 'p91-admin@test', 'role' => 'admin']);

    $ticket = SupportTicket::create([
        'user_id'     => $customer->id,
        'number'      => 'TKT-' . now()->format('ymd') . '-7777',
        'ticket_type' => 'general_inquiry',
        'subject'     => 'Original customer subject',
        'priority'    => 'normal',
        'status'      => 'pending',
    ]);
    $originalMsg = SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id,
        'user_id'           => $customer->id,
        'body'              => 'Original customer body',
        'author_role'       => 'customer',
    ]);

    $svc = app(\App\Domain\Support\SupportTicketService::class);
    $svc->reply($ticket, $admin, 'Admin reply text', 'admin', false);

    // Original subject + body unchanged
    $ticket->refresh();
    expect($ticket->subject)->toBe('Original customer subject');
    expect($originalMsg->fresh()->body)->toBe('Original customer body');
    // Ticket has 2 messages — original customer + new admin reply
    expect($ticket->messages()->count())->toBe(2);
    // Status flipped to 'answered'
    expect($ticket->status)->toBe('answered');
});

//
// BUG #9 — mail safety: MAIL_MAILER=log in .env.example + no Phase 9 code dispatches mail
//

it('.env.example has MAIL_MAILER=log for safe local development', function () {
    $env = file_get_contents(base_path('.env.example'));
    expect($env)->toMatch('/^MAIL_MAILER\s*=\s*log\s*$/m');
});
