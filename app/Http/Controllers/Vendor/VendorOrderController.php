<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Order\OrderLifecycleService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor's view of orders. Vendors only see orders that contain their own
 * order_items; the dashboard masks customer details + items they don't own.
 *
 * markShipped here scopes to the calling vendor's items via OrderLifecycleService.
 */
class VendorOrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $orders = Order::query()
            ->forVendor($vendor->id)
            ->with(['items' => fn ($q) => $q->where('vendor_id', $vendor->id)])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Vendor/Orders/Index', [
            'orders' => $orders->through(fn (Order $o) => [
                'id'                => $o->id,
                'number'            => $o->number,
                'status'            => $o->status,
                'payment_status'    => $o->payment_status,
                'fulfillment_status'=> $o->fulfillment_status,
                'currency'          => $o->currency,
                'vendor_total' => number_format(
                    $o->items->sum(fn ($i) => $i->line_total_minor) / 100,
                    2
                ),
                'vendor_earnings' => number_format(
                    $o->items->sum(fn ($i) => $i->vendor_earning_minor) / 100,
                    2
                ),
                'items_count' => $o->items->sum('quantity'),
                'placed_at'   => $o->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function show(Request $request, int $order): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $o = Order::with([
            'items' => fn ($q) => $q->where('vendor_id', $vendor->id),
            'items.customizations',
            'items.proofs',
            'addresses', 'shippingAddress',
            // v6.4 — defensive: even though present() below doesn't currently
            // iterate events, ANY future addition that reads $e->actor?->name
            // would trigger the same lazy-load class as the v6.1 OrderController
            // bug. Eager-loading actor:id,name is cheap and forecloses the bug.
            'events.actor:id,name',
        ])->find($order);

        if (! $o || ! Order::forVendor($vendor->id)->where('id', $order)->exists()) {
            throw new NotFoundHttpException();
        }

        // Phase 9 v9.3 — vendor sees:
        //   - order-level coupon code (informational)
        //   - per-line allocated coupon discount
        //   - net line total (gross − allocation), which is the base for commission
        //   - their final vendor_earning, which is what the platform will pay them
        // Vendor does NOT see other vendors' lines (the items relation is
        // already scoped to their vendor_id above).
        $vendorAllocatedTotal = $o->items->sum('coupon_allocation_minor');
        $vendorGrossTotal     = $o->items->sum('line_total_minor');
        $vendorNetTotal       = max(0, $vendorGrossTotal - $vendorAllocatedTotal);
        $vendorEarningsTotal  = $o->items->sum('vendor_earning_minor');
        $vendorCommissionTotal = $o->items->sum('commission_amount_minor');

        return Inertia::render('Vendor/Orders/Show', [
            'order' => [
                'id'                 => $o->id,
                'number'             => $o->number,
                'status'             => $o->status,
                'payment_status'     => $o->payment_status,
                'fulfillment_status' => $o->fulfillment_status,
                'currency'           => $o->currency,
                'placed_at'          => $o->created_at?->toDateTimeString(),
                'paid_at'            => $o->paid_at?->toDateTimeString(),
                // Phase 9 v9.3 — order-level coupon snapshot (for vendor info)
                'coupon' => $o->coupon_code ? [
                    'code'                       => $o->coupon_code,
                    'order_discount'             => number_format(((int) $o->coupon_discount_minor) / 100, 2),
                    'order_discount_minor'       => (int) $o->coupon_discount_minor,
                    // The portion of the coupon discount that came off THIS vendor's lines
                    'vendor_allocation'          => number_format($vendorAllocatedTotal / 100, 2),
                    'vendor_allocation_minor'    => $vendorAllocatedTotal,
                ] : null,
                // Phase 9 v9.3 — vendor financial summary
                'vendor_summary' => [
                    'gross_total'       => number_format($vendorGrossTotal / 100, 2),
                    'gross_total_minor' => $vendorGrossTotal,
                    'allocated_coupon'  => number_format($vendorAllocatedTotal / 100, 2),
                    'allocated_coupon_minor' => $vendorAllocatedTotal,
                    'net_total'         => number_format($vendorNetTotal / 100, 2),
                    'net_total_minor'   => $vendorNetTotal,
                    'commission'        => number_format($vendorCommissionTotal / 100, 2),
                    'commission_minor'  => $vendorCommissionTotal,
                    'earnings'          => number_format($vendorEarningsTotal / 100, 2),
                    'earnings_minor'    => $vendorEarningsTotal,
                ],

                'items' => $o->items->map(fn (OrderItem $i) => [
                    'id'                  => $i->id,
                    'product_name'        => $i->product_name,
                    'variant_name'        => $i->variant_name,
                    'quantity'            => $i->quantity,
                    'unit_price'          => number_format($i->unit_price_minor / 100, 2),
                    'line_total'          => number_format($i->line_total_minor / 100, 2),
                    'line_total_minor'    => $i->line_total_minor,
                    // Phase 9 v9.3 — per-line coupon allocation + net base for commission
                    'coupon_allocation'   => number_format(((int) $i->coupon_allocation_minor) / 100, 2),
                    'coupon_allocation_minor' => (int) $i->coupon_allocation_minor,
                    'net_line_total'      => number_format(max(0, $i->line_total_minor - (int) $i->coupon_allocation_minor) / 100, 2),
                    'commission_percent'  => (float) $i->commission_percent,
                    'commission_amount'   => number_format($i->commission_amount_minor / 100, 2),
                    'vendor_earning'      => number_format($i->vendor_earning_minor / 100, 2),
                    'fulfillment'         => $i->fulfillment_status,
                    // Phase 7 — customizations + proof workflow
                    'customization_status' => $i->customization_status,
                    'customization_fee'    => $i->customization_fee_minor > 0
                        ? number_format($i->customization_fee_minor / 100, 2)
                        : null,
                    'customizations' => $i->customizations->map(fn ($c) => [
                        'id'                 => $c->id,
                        'field_key'          => $c->field_key,
                        'field_label'        => $c->field_label,
                        'field_type'         => $c->field_type,
                        'value'              => $c->value,
                        'file_original_name' => $c->file_original_name,
                        'has_file'           => (bool) $c->file_path,
                    ])->values(),
                    'proofs' => $i->proofs->map(fn ($p) => [
                        'id'                 => $p->id,
                        'file_original_name' => $p->file_original_name,
                        'status'             => $p->status,
                        'vendor_note'        => $p->vendor_note,
                        'customer_response'  => $p->customer_response,
                        'sent_at'            => $p->sent_at?->toDateTimeString(),
                        'responded_at'       => $p->responded_at?->toDateTimeString(),
                    ])->values(),
                ])->values(),

                'shipping_address' => $o->shippingAddress
                    ? collect($o->shippingAddress->toArray())->only([
                        'recipient_name', 'phone', 'country', 'state', 'city',
                        'area', 'block', 'street', 'building', 'floor', 'apartment',
                        'postal_code',
                    ])
                    : null,

                'vendor_subtotal' => number_format(
                    $o->items->sum('line_total_minor') / 100, 2
                ),
                'vendor_commission' => number_format(
                    $o->items->sum('commission_amount_minor') / 100, 2
                ),
                'vendor_earnings' => number_format(
                    $o->items->sum('vendor_earning_minor') / 100, 2
                ),
            ],
            // Phase 10 v10.11 §3 — server-computed status options. Pre-v10.11
            // the React Show.tsx computed availability using values like
            // `order.fulfillment_status === 'shipped'` which is NEVER true —
            // the fulfillment_status enum is ['unfulfilled', 'partially_fulfilled',
            // 'fulfilled', 'returned']. 'shipped' is an ORDER STATUS, not a
            // fulfillment status. As a result canDeliver was always false,
            // canShip required exact 'unfulfilled' item state, and the dev
            // saw the dropdown with every option grayed out for many real
            // order states.
            //
            // v10.11 computes availability against the canonical lifecycle
            // service rules on the server, where the enum constants are the
            // source of truth. React just displays the options.
            'status_options' => $this->computeStatusOptions($o, $vendor->id),
        ]);
    }

    /**
     * v10.11 §3 — canonical availability rules for vendor order transitions.
     *
     * - confirm: order is in pending_payment or paid → confirm transitions to confirmed
     * - ship:    at least one vendor item is unfulfilled AND order is not in
     *            a terminal state (cancelled/refunded/failed). markShipped
     *            transitions order.status to shipped if all items fulfilled.
     * - deliver: order.status is shipped → markDelivered transitions to delivered
     *
     * Vendor is NEVER given a "paid" option — payment status is the customer's
     * payment provider's responsibility (or the admin's, for manual transfer).
     *
     * @return array<int, array{value:string,label:string,available:bool,reason:?string}>
     */
    private function computeStatusOptions(Order $o, int $vendorId): array
    {
        $terminal = [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED, Order::STATUS_FAILED, Order::STATUS_COMPLETED];

        $canConfirm = in_array($o->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID], true);

        $vendorHasUnfulfilledItem = $o->items
            ->where('vendor_id', $vendorId)
            ->contains(fn (OrderItem $i) => $i->fulfillment_status === OrderItem::FUL_UNFULFILLED);
        $canShip = $vendorHasUnfulfilledItem && ! in_array($o->status, $terminal, true);

        $canDeliver = $o->status === Order::STATUS_SHIPPED;

        return [
            [
                'value'     => '__current',
                'label'     => 'Current: ' . str_replace('_', ' ', $o->status),
                'available' => false,
                'reason'    => null,
            ],
            [
                'value'     => 'confirm',
                'label'     => '→ Confirm order (mark processing)',
                'available' => $canConfirm,
                'reason'    => $canConfirm ? null : ('already ' . str_replace('_', ' ', $o->status)),
            ],
            [
                'value'     => 'ship',
                'label'     => '→ Mark items shipped',
                'available' => $canShip,
                'reason'    => $canShip ? null : (
                    ! $vendorHasUnfulfilledItem
                        ? 'no unfulfilled items remain'
                        : 'order is ' . str_replace('_', ' ', $o->status)
                ),
            ],
            [
                'value'     => 'deliver',
                'label'     => '→ Mark delivered (fulfilled)',
                'available' => $canDeliver,
                'reason'    => $canDeliver ? null : ('order must be shipped first (currently ' . str_replace('_', ' ', $o->status) . ')'),
            ],
        ];
    }

    /**
     * Vendor marks THEIR items as shipped. Order-level fulfillment is
     * recomputed by the service from the union of all vendors' line statuses.
     */
    public function ship(Request $request, int $order, OrderLifecycleService $svc): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $o = Order::forVendor($vendor->id)->find($order);
        if (! $o) {
            throw new NotFoundHttpException();
        }

        $this->authorize('ship', $o);

        try {
            $svc->markShipped($o, $vendor->id, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Marked your items as shipped.');
    }

    /**
     * Phase 9 v9.1 — vendor confirms an order (the COD flow + payment-pending
     * orders waiting for vendor acknowledgement). Uses the existing
     * OrderLifecycleService::confirm transition.
     */
    public function confirm(Request $request, int $order, OrderLifecycleService $svc): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $o = Order::forVendor($vendor->id)->find($order);
        if (! $o) {
            throw new NotFoundHttpException();
        }
        $this->authorize('ship', $o);   // reuse the existing policy gate

        try {
            $svc->confirm($o, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Order confirmed.');
    }

    /**
     * Phase 9 v9.1 — vendor marks the order delivered (typical COD flow
     * where the vendor's driver hands the package over and collects cash).
     */
    public function deliver(Request $request, int $order, OrderLifecycleService $svc): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $o = Order::forVendor($vendor->id)->find($order);
        if (! $o) {
            throw new NotFoundHttpException();
        }
        $this->authorize('ship', $o);

        try {
            $svc->markDelivered($o, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Order marked delivered.');
    }
}
