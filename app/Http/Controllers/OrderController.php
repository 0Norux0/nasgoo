<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\OrderLifecycleService;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->with(['items:id,order_id,product_name,quantity,vendor_id'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Orders/Index', [
            'orders' => $orders->through(fn (Order $o) => [
                'id'           => $o->id,
                'number'       => $o->number,
                'status'       => $o->status,
                'payment_status' => $o->payment_status,
                'fulfillment_status' => $o->fulfillment_status,
                'total'        => number_format($o->total_minor / 100, 2),
                'currency'     => $o->currency,
                'items_count'  => $o->items->sum('quantity'),
                'placed_at'    => $o->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function show(Request $request, int $order): Response
    {
        // v5.6 — eager load EVERY relation that present() touches, otherwise
        // Model::shouldBeStrict() (enabled in non-production) trips on lazy loads.
        // Phase 9 v9.3 — added items.product:id,slug for the Write Review
        // button's product_slug, replacing the v9.1 per-item value('slug')
        // workaround which still added N+1 queries (just bypassing strict mode).
        $o = Order::with([
            'items', 'addresses', 'shippingAddress',
            'events.actor:id,name',
            'payments.transactions',
            // Phase 7 — customization snapshot rows + latest proof per item
            'items.customizations',
            'items.latestProof',
            // Phase 9 v9.3 — product slug for Write Review link
            'items.product:id,slug,name',
        ])->findOrFail($order);
        $this->authorize('view', $o);

        return Inertia::render('Orders/Show', [
            'order' => $this->present($o),
        ]);
    }

    /**
     * Lightweight confirmation page after checkout. Same shape as show() but
     * with a different React page that emphasises the success message.
     */
    public function confirm(Request $request, int $order): Response
    {
        // v5.6 — present() reads items, shippingAddress, events, payments.
        // v6.1 — also eager-load events.actor since present() reads $e->actor?->name.
        // v7.6 — present() ALSO reads $i->customizations and $i->latestProof
        //        for Phase 7 customizable products. Without these eager-loads,
        //        the post-checkout redirect to /orders/{id}/confirm crashed
        //        with `Attempted to lazy load [customizations] on model
        //        [App\Models\OrderItem] but lazy loading is disabled.`
        //        This is the v7.6 developer-reported bug fix. OrderController::show
        //        already loaded these; only confirm was missing them — same
        //        present() helper renders both pages.
        $o = Order::with([
            'items', 'items.customizations', 'items.latestProof',
            'addresses', 'shippingAddress',
            'events.actor:id,name', 'payments',
            // Phase 9 v9.3 — product slug for Write Review link (same as show())
            'items.product:id,slug,name',
        ])->findOrFail($order);
        $this->authorize('view', $o);

        return Inertia::render('Orders/Confirm', [
            'order' => $this->present($o),
        ]);
    }

    public function cancel(Request $request, int $order, OrderLifecycleService $svc): RedirectResponse
    {
        // v5.6 — OrderLifecycleService::cancel iterates $order->items and each
        // $item->variant / $item->product to restock. Eager load to satisfy
        // Model::shouldBeStrict().
        $o = Order::with(['items.product', 'items.variant'])->findOrFail($order);
        $this->authorize('cancel', $o);

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        try {
            $svc->cancel($o, $reason, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Order #{$o->number} cancelled.");
    }

    private function present(Order $o): array
    {
        return [
            'id'                    => $o->id,
            'number'                => $o->number,
            'status'                => $o->status,
            'payment_status'        => $o->payment_status,
            'fulfillment_status'    => $o->fulfillment_status,
            'currency'              => $o->currency,
            'subtotal'              => number_format($o->subtotal_minor / 100, 2),
            'subtotal_minor'        => $o->subtotal_minor,
            'shipping'              => number_format($o->shipping_minor / 100, 2),
            'tax'                   => number_format($o->tax_minor / 100, 2),
            'discount'              => number_format($o->discount_minor / 100, 2),
            'discount_minor'        => $o->discount_minor,
            'total'                 => number_format($o->total_minor / 100, 2),
            'total_minor'           => $o->total_minor,
            // Phase 9 v9.3 — coupon snapshot for the customer order detail page
            'coupon'                => $o->coupon_id || $o->coupon_code ? [
                'id'             => $o->coupon_id,
                'code'           => $o->coupon_code,
                'discount'       => number_format(((int) $o->coupon_discount_minor) / 100, 2),
                'discount_minor' => (int) $o->coupon_discount_minor,
            ] : null,
            'customer_notes'        => $o->customer_notes,
            'placed_at'             => $o->created_at?->toDateTimeString(),
            'paid_at'               => $o->paid_at?->toDateTimeString(),
            'shipped_at'            => $o->shipped_at?->toDateTimeString(),
            'delivered_at'          => $o->delivered_at?->toDateTimeString(),
            'cancelled_at'          => $o->cancelled_at?->toDateTimeString(),
            'cancellation_reason'   => $o->cancellation_reason,

            'items' => $o->items->map(function ($i) use ($o) {
                // Phase 9 v9.1 — review eligibility (delivered + has product + not already reviewed)
                $alreadyReviewed = false;
                if ($i->product_id) {
                    $alreadyReviewed = \App\Models\ProductReview::query()
                        ->where('user_id', $o->user_id)
                        ->where('product_id', $i->product_id)
                        ->where(function ($q) use ($i) {
                            $q->whereNull('order_item_id')
                              ->orWhere('order_item_id', $i->id);
                        })
                        ->exists();
                }
                $canReview = $o->delivered_at !== null
                    && $i->product_id !== null
                    && ! $alreadyReviewed;

                return [
                    'id'              => $i->id,
                    'product_id'      => $i->product_id,
                    // Phase 9 v9.3 — use eager-loaded product instead of v9.1's inline value('slug') query
                    'product_slug'    => $i->product?->slug,
                    'product_name'    => $i->product_name,
                    'variant_name'    => $i->variant_name,
                    'quantity'        => $i->quantity,
                    'unit_price'      => number_format($i->unit_price_minor / 100, 2),
                    'line_total'      => number_format($i->line_total_minor / 100, 2),
                    'line_total_minor' => $i->line_total_minor,
                    // Phase 9 v9.3 — per-line coupon allocation
                    'coupon_allocation'       => number_format(((int) $i->coupon_allocation_minor) / 100, 2),
                    'coupon_allocation_minor' => (int) $i->coupon_allocation_minor,
                    'fulfillment'     => $i->fulfillment_status,
                    'can_review'      => $canReview,
                    'already_reviewed' => $alreadyReviewed,
                    'customization_status' => $i->customization_status,
                    'customization_fee'    => $i->customization_fee_minor > 0
                        ? number_format($i->customization_fee_minor / 100, 2)
                        : null,
                    'customizations'  => $i->customizations->map(fn ($c) => [
                        'id'                 => $c->id,
                        'field_key'          => $c->field_key,
                        'field_label'        => $c->field_label,
                        'field_type'         => $c->field_type,
                        'value'              => $c->value,
                        'file_original_name' => $c->file_original_name,
                        'has_file'           => (bool) $c->file_path,
                    ])->values(),
                    'latest_proof' => $i->latestProof ? [
                        'id'                 => $i->latestProof->id,
                        'file_original_name' => $i->latestProof->file_original_name,
                        'file_mime'          => $i->latestProof->file_mime,
                        'status'             => $i->latestProof->status,
                        'vendor_note'        => $i->latestProof->vendor_note,
                        'customer_response'  => $i->latestProof->customer_response,
                        'sent_at'            => $i->latestProof->sent_at?->toDateTimeString(),
                        'responded_at'       => $i->latestProof->responded_at?->toDateTimeString(),
                    ] : null,
                ];
            })->values(),

            'shipping_address' => $o->shippingAddress
                ? collect($o->shippingAddress->toArray())->only([
                    'recipient_name', 'phone', 'country', 'state', 'city',
                    'area', 'block', 'street', 'building', 'floor', 'apartment',
                    'postal_code',
                ])
                : null,

            'events' => $o->events->map(fn ($e) => [
                'event_type' => $e->event_type,
                'message'    => $e->message,
                'actor_role' => $e->actor_role,
                'actor_name' => $e->actor?->name,
                'at'         => $e->created_at?->toDateTimeString(),
            ])->values(),

            'payments' => $o->payments->map(fn ($p) => [
                'id'           => $p->id,
                'method_slug'  => $p->method_slug,
                'status'       => $p->status,
                'amount'       => number_format($p->amount_minor / 100, 2),
                'refunded'     => number_format($p->refunded_minor / 100, 2),
                'reference'    => $p->reference,
                'captured_at'  => $p->captured_at?->toDateTimeString(),
            ])->values(),
        ];
    }
}
