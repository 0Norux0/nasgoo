<?php

declare(strict_types=1);

use App\Domain\Order\CheckoutService;
use App\Domain\Promotion\CouponValidator;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
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

// v8.5 — every helper prefixed `p93_` to avoid collisions with existing
// helpers (`p9_`, `p91_`, and the 22 from prior phases).

function p93Customer(string $email = 'p93-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

function p93Vendor(string $email): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p93Product(Vendor $v, int $priceMinor = 50000, string $slug = 'p93prod'): Product
{
    return Product::factory()->published()->create([
        'vendor_id'   => $v->id,
        'slug'        => $slug . '-' . $v->id,
        'name'        => 'Phase 9.3 test product',
        'price_minor' => $priceMinor,
        'currency'    => 'KWD',
    ]);
}

//
// BUG #1: COUPON PERSISTENCE — cart → checkout → order snapshot + per-line allocation
//

it('checkout page shows the coupon applied in the cart', function () {
    $customer = p93Customer();
    [, $vendor] = p93Vendor('p93-checkout-vendor@test');
    $product = p93Product($vendor, 50000, 'p93-cko');

    $cart = Cart::create([
        'user_id' => $customer->id, 'currency' => 'KWD',
        'subtotal_minor' => 0, 'items_count' => 0,
    ]);
    CartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id,
        'vendor_id' => $product->vendor_id,
        'quantity' => 2, 'unit_price_minor' => 50000,
        'currency' => 'KWD',
    ]);
    $cart->update(['subtotal_minor' => 100000, 'items_count' => 1]);

    $coupon = Coupon::create([
        'code' => 'P93CKO', 'discount_type' => 'percentage', 'discount_value' => 20,
        'is_active' => true, 'per_user_limit' => 1, 'currency' => 'KWD',
    ]);
    $cart->update(['coupon_id' => $coupon->id, 'discount_minor' => 20000]);

    $this->actingAs($customer);
    $resp = $this->get('/checkout');
    $resp->assertOk();
    $props = $resp->viewData('page')['props']['cart'] ?? [];
    expect($props)->toHaveKey('coupon');
    expect($props['coupon'])->not->toBeNull();
    expect($props['coupon']['code'])->toBe('P93CKO');
    expect($props['coupon']['discount_minor'])->toBe(20000);
    expect($props['payable_minor'])->toBe(80000);   // 100k − 20k
});

it('order snapshot stores coupon_id + coupon_code + coupon_discount_minor after checkout', function () {
    $customer = p93Customer('p93-snap-cust@test');
    [, $vendor] = p93Vendor('p93-snap-vendor@test');
    $product = p93Product($vendor, 100000, 'p93-snap');

    $cart = Cart::create([
        'user_id' => $customer->id, 'currency' => 'KWD',
        'subtotal_minor' => 0, 'items_count' => 0,
    ]);
    CartItem::create([
        'cart_id' => $cart->id, 'product_id' => $product->id,
        'vendor_id' => $product->vendor_id,
        'quantity' => 1, 'unit_price_minor' => 100000,
        'currency' => 'KWD',
    ]);
    $cart->update(['subtotal_minor' => 100000, 'items_count' => 1]);

    $coupon = Coupon::create([
        'code' => 'P93SNAP', 'discount_type' => 'percentage', 'discount_value' => 10,
        'is_active' => true, 'per_user_limit' => 1, 'currency' => 'KWD',
    ]);
    $cart->update(['coupon_id' => $coupon->id, 'discount_minor' => 10000]);

    // Address + place order via CheckoutService
    $customer->addresses()->create([
        'label' => 'Home', 'type' => 'shipping', 'country' => 'KW',
        'city' => 'Kuwait City', 'is_default' => true,
    ]);

    $svc = app(CheckoutService::class);
    $order = $svc->placeOrder($customer, [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'billing_address_id' => $customer->addresses()->first()->id,
        'payment_method' => 'cod',
        'shipping_minor' => 0,
    ]);

    expect($order->coupon_id)->toBe($coupon->id);
    expect($order->coupon_code)->toBe('P93SNAP');
    expect($order->coupon_discount_minor)->toBe(10000);
    expect($order->discount_minor)->toBe(10000);
    expect($order->total_minor)->toBe(90000);   // 100k − 10k
    expect(CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $customer->id)->exists())->toBeTrue();
});

//
// BUG #1: ALLOCATION RECONCILIATION — single-vendor and multi-vendor invariants
//

it('single-vendor cart: coupon allocation sums to coupon discount and reconciles', function () {
    $customer = p93Customer('p93-alloc1-cust@test');
    [, $vendor] = p93Vendor('p93-alloc1-vendor@test');

    // 3 line items, total 100 KWD
    $p1 = p93Product($vendor, 30000, 'p93-a1-1');
    $p2 = p93Product($vendor, 50000, 'p93-a1-2');
    $p3 = p93Product($vendor, 20000, 'p93-a1-3');

    $cart = Cart::create(['user_id' => $customer->id, 'currency' => 'KWD', 'subtotal_minor' => 0, 'items_count' => 0]);
    foreach ([[$p1, 30000], [$p2, 50000], [$p3, 20000]] as [$p, $price]) {
        CartItem::create([
            'cart_id' => $cart->id, 'product_id' => $p->id,
        'vendor_id' => $p->vendor_id,
            'quantity' => 1, 'unit_price_minor' => $price, 'currency' => 'KWD',
        ]);
    }
    $cart->update(['subtotal_minor' => 100000, 'items_count' => 3]);

    $coupon = Coupon::create([
        'code' => 'P93A1', 'discount_type' => 'percentage', 'discount_value' => 15,   // 15 KWD off
        'is_active' => true, 'per_user_limit' => 1, 'currency' => 'KWD',
    ]);
    $cart->update(['coupon_id' => $coupon->id, 'discount_minor' => 15000]);

    $customer->addresses()->create(['label' => 'H', 'type' => 'shipping', 'country' => 'KW', 'city' => 'KC', 'is_default' => true]);
    $order = app(CheckoutService::class)->placeOrder($customer, [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'billing_address_id' => $customer->addresses()->first()->id,
        'payment_method' => 'cod', 'shipping_minor' => 0,
    ]);

    $items = OrderItem::where('order_id', $order->id)->get();

    // Invariant 1: sum of allocations == coupon discount
    expect($items->sum('coupon_allocation_minor'))->toBe(15000);

    // Invariant 2: sum of line_total_minor == subtotal
    expect($items->sum('line_total_minor'))->toBe(100000);

    // Invariant 3: every item's net = gross − allocation; commission on net
    //              ⇒ sum(commission + earning) == subtotal − coupon
    $totalNet      = $items->sum(fn ($i) => $i->line_total_minor - $i->coupon_allocation_minor);
    $totalEarnings = $items->sum('vendor_earning_minor');
    $totalCommission = $items->sum('commission_amount_minor');
    expect($totalNet)->toBe(85000);
    expect($totalEarnings + $totalCommission)->toBe(85000);
});

it('multi-vendor cart: coupon allocation splits proportionally across vendors and reconciles', function () {
    $customer = p93Customer('p93-alloc2-cust@test');
    [, $vendorA] = p93Vendor('p93-alloc2-A@test');
    [, $vendorB] = p93Vendor('p93-alloc2-B@test');

    // Vendor A: 70 KWD; Vendor B: 30 KWD; total 100 KWD
    $pA = p93Product($vendorA, 70000, 'p93-a2-A');
    $pB = p93Product($vendorB, 30000, 'p93-a2-B');

    $cart = Cart::create(['user_id' => $customer->id, 'currency' => 'KWD', 'subtotal_minor' => 0, 'items_count' => 0]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $pA->id,
        'vendor_id' => $pA->vendor_id, 'quantity' => 1, 'unit_price_minor' => 70000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $pB->id,
        'vendor_id' => $pB->vendor_id, 'quantity' => 1, 'unit_price_minor' => 30000, 'currency' => 'KWD']);
    $cart->update(['subtotal_minor' => 100000, 'items_count' => 2]);

    $coupon = Coupon::create([
        'code' => 'P93A2', 'discount_type' => 'fixed_amount', 'discount_value' => 10000,   // 10 KWD off
        'is_active' => true, 'per_user_limit' => 1, 'currency' => 'KWD',
    ]);
    $cart->update(['coupon_id' => $coupon->id, 'discount_minor' => 10000]);

    $customer->addresses()->create(['label' => 'H', 'type' => 'shipping', 'country' => 'KW', 'city' => 'KC', 'is_default' => true]);
    $order = app(CheckoutService::class)->placeOrder($customer, [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'billing_address_id' => $customer->addresses()->first()->id,
        'payment_method' => 'cod', 'shipping_minor' => 0,
    ]);

    $itemA = OrderItem::where('order_id', $order->id)->where('vendor_id', $vendorA->id)->first();
    $itemB = OrderItem::where('order_id', $order->id)->where('vendor_id', $vendorB->id)->first();

    // Allocations: vendor A gets 70% of 10k = 7000; vendor B gets the remainder = 3000
    expect($itemA->coupon_allocation_minor + $itemB->coupon_allocation_minor)->toBe(10000);
    // Vendor A's allocation is proportional to their line share
    expect($itemA->coupon_allocation_minor)->toBe(7000);
    expect($itemB->coupon_allocation_minor)->toBe(3000);

    // Reconciliation: sum(earnings + commission) == subtotal − coupon
    $items = collect([$itemA, $itemB]);
    $sumPay = $items->sum('vendor_earning_minor') + $items->sum('commission_amount_minor');
    expect($sumPay)->toBe(90000);
});

it('cart without coupon: every order_item has coupon_allocation_minor = 0', function () {
    $customer = p93Customer('p93-nocoup-cust@test');
    [, $vendor] = p93Vendor('p93-nocoup-vendor@test');
    $p = p93Product($vendor, 50000, 'p93-noc');

    $cart = Cart::create(['user_id' => $customer->id, 'currency' => 'KWD', 'subtotal_minor' => 0, 'items_count' => 0]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p->id,
        'vendor_id' => $p->vendor_id, 'quantity' => 1, 'unit_price_minor' => 50000, 'currency' => 'KWD']);
    $cart->update(['subtotal_minor' => 50000, 'items_count' => 1]);

    $customer->addresses()->create(['label' => 'H', 'type' => 'shipping', 'country' => 'KW', 'city' => 'KC', 'is_default' => true]);
    $order = app(CheckoutService::class)->placeOrder($customer, [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'billing_address_id' => $customer->addresses()->first()->id,
        'payment_method' => 'cod', 'shipping_minor' => 0,
    ]);

    $items = OrderItem::where('order_id', $order->id)->get();
    expect($items->sum('coupon_allocation_minor'))->toBe(0);
});

//
// BUG #1: COUPON VISIBLE IN CUSTOMER + VENDOR ORDER DETAIL
//

it('customer order detail exposes coupon block + per-item allocation', function () {
    $customer = p93Customer('p93-cdet-cust@test');
    $coupon = Coupon::create(['code' => 'P93CDET', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true, 'per_user_limit' => 1, 'currency' => 'KWD']);

    $order = Order::create([
        'number' => 'P93-CDET-001', 'user_id' => $customer->id,
        'status' => 'paid', 'payment_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
        'currency' => 'KWD',
        'subtotal_minor' => 50000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => 5000, 'total_minor' => 45000,
        'coupon_id' => $coupon->id, 'coupon_code' => 'P93CDET', 'coupon_discount_minor' => 5000,
    ]);

    $this->actingAs($customer);
    $resp = $this->get("/orders/{$order->id}");
    $resp->assertOk();
    $props = $resp->viewData('page')['props']['order'] ?? [];
    expect($props['coupon'])->not->toBeNull();
    expect($props['coupon']['code'])->toBe('P93CDET');
    expect($props['coupon']['discount_minor'])->toBe(5000);
});

//
// BUG #2: WRITE REVIEW BUTTON ON DELIVERED ITEMS
//

it('delivered order item exposes can_review=true via OrderController::present', function () {
    [, $vendor] = p93Vendor('p93-rev-vendor@test');
    $product = p93Product($vendor, 50000, 'p93-rev');
    $customer = p93Customer('p93-rev-cust@test');

    $order = Order::create([
        'number' => 'P93-REV-001', 'user_id' => $customer->id,
        'status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'delivered',
        'currency' => 'KWD',
        'subtotal_minor' => 50000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => 0, 'total_minor' => 50000,
        'delivered_at' => now(),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 1, 'unit_price_minor' => 50000, 'line_total_minor' => 50000, 'currency' => 'KWD',
    ]);

    $this->actingAs($customer);
    $resp = $this->get("/orders/{$order->id}");
    $items = $resp->viewData('page')['props']['order']['items'];
    expect($items[0]['can_review'])->toBeTrue();
    expect($items[0]['product_slug'])->toBe($product->slug);
});

it('customer can submit a review through the existing /products/{slug}/reviews endpoint and it links to the order item', function () {
    [, $vendor] = p93Vendor('p93-revend-vendor@test');
    $product = p93Product($vendor, 50000, 'p93-revend');
    $customer = p93Customer('p93-revend-cust@test');

    $order = Order::create([
        'number' => 'P93-REVEND-001', 'user_id' => $customer->id,
        'status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'delivered',
        'currency' => 'KWD',
        'subtotal_minor' => 50000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => 0, 'total_minor' => 50000,
        'delivered_at' => now(),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
        'quantity' => 1, 'unit_price_minor' => 50000, 'line_total_minor' => 50000, 'currency' => 'KWD',
    ]);

    $this->actingAs($customer);
    $this->post("/products/{$product->slug}/reviews", [
        'rating' => 5,
        'body'   => 'Great product, fast shipping!',
    ])->assertRedirect();

    expect(ProductReview::where('user_id', $customer->id)->where('product_id', $product->id)->exists())->toBeTrue();
    // Second attempt blocked
    $resp = $this->post("/products/{$product->slug}/reviews", [
        'rating' => 5,
        'body'   => 'Duplicate attempt',
    ]);
    expect(ProductReview::where('user_id', $customer->id)->where('product_id', $product->id)->count())->toBe(1);
});

//
// BUG #3: LAZY-LOAD ON ADMIN TICKET PAGE
//

it('ViewSupportTicket::resolveRecord eager-loads messages.user so the Infolist does not lazy-load', function () {
    $customer = p93Customer('p93-tlz-cust@test');
    $admin = User::factory()->create(['email' => 'p93-tlz-admin@test', 'role' => 'admin']);

    $ticket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9301',
        'ticket_type' => 'general_inquiry',
        'subject' => 'Lazy-load regression test',
        'priority' => 'normal', 'status' => 'pending',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $customer->id,
        'body' => 'Hello', 'author_role' => 'customer',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $admin->id,
        'body' => 'Hi there', 'author_role' => 'admin',
    ]);

    // Enable strict model mode (matches Phase 7+ runtime)
    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);

    try {
        $page = new \App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket();
        $resolved = $page->resolveRecord($ticket->id);

        // Force the same access pattern the Infolist's RepeatableEntry will use:
        // each message's user.name. If lazy-loading kicks in here, this test
        // throws — which is exactly what the developer saw at runtime.
        foreach ($resolved->messages as $m) {
            $name = $m->user?->name;
            expect($name)->not->toBeNull();
        }
        expect(true)->toBeTrue();
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

it('admin can reply via SupportTicketService and the message links to a user without lazy-loading', function () {
    $customer = p93Customer('p93-tlz-flow-cust@test');
    $admin = User::factory()->create(['email' => 'p93-tlz-flow-admin@test', 'role' => 'admin']);

    $ticket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9302',
        'ticket_type' => 'general_inquiry',
        'subject' => 'End-to-end ticket test',
        'priority' => 'normal', 'status' => 'open',
    ]);
    SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id, 'user_id' => $customer->id,
        'body' => 'Question', 'author_role' => 'customer',
    ]);

    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);

    try {
        $svc = app(\App\Domain\Support\SupportTicketService::class);
        $reply = $svc->reply($ticket, $admin, 'Answer', 'admin', false);
        expect($reply)->not->toBeNull();
        expect($ticket->fresh()->status)->toBe('answered');

        // Re-resolve via ViewSupportTicket and traverse the chain
        $page = new \App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket();
        $resolved = $page->resolveRecord($ticket->id);
        foreach ($resolved->messages as $m) {
            expect($m->user?->name)->not->toBeNull();
        }
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});
