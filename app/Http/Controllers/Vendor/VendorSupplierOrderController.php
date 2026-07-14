<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Supplier\DropshipOrderCreator;
use App\Http\Controllers\Controller;
use App\Models\SupplierOrder;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 6 — vendor view of supplier_orders + manual status transitions.
 * Scoping: SupplierOrder::forVendor() filters by vendor_id.
 */
class VendorSupplierOrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $orders = SupplierOrder::forVendor($vendor->id)
            ->with(['platform:id,name', 'order:id,number', 'orderItems:id,supplier_order_id,product_name,quantity'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (SupplierOrder $so) => [
                'id'                  => $so->id,
                'number'              => $so->number,
                'platform'            => $so->platform?->name,
                'order_number'        => $so->order?->number,
                'order_id'            => $so->order_id,
                'status'              => $so->status,
                'supplier_reference'  => $so->supplier_reference,
                'tracking_number'     => $so->tracking_number,
                'cost'                => number_format($so->supplier_cost_minor / 100, 2) . ' ' . $so->currency,
                'cost_minor'          => $so->supplier_cost_minor,
                'currency'            => $so->currency,
                'items'               => $so->orderItems->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'quantity'     => $i->quantity,
                ]),
                'created_at'          => $so->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Vendor/Supplier/Orders/Index', [
            'orders' => $orders,
            'statuses' => SupplierOrder::ALL_STATUSES,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $so = SupplierOrder::forVendor($vendor->id)
            ->with(['platform', 'order:id,number', 'orderItems', 'events.actor:id,name'])
            ->findOrFail($id);

        return Inertia::render('Vendor/Supplier/Orders/Show', [
            'so' => [
                'id'                  => $so->id,
                'number'              => $so->number,
                'platform'            => $so->platform?->name,
                'order_number'        => $so->order?->number,
                'order_id'            => $so->order_id,
                'status'              => $so->status,
                'supplier_reference'  => $so->supplier_reference,
                'tracking_number'     => $so->tracking_number,
                'tracking_url'        => $so->tracking_url,
                'carrier'             => $so->carrier,
                'notes'               => $so->notes,
                'cost'                => number_format($so->supplier_cost_minor / 100, 2) . ' ' . $so->currency,
                'currency'            => $so->currency,
                'items'               => $so->orderItems->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'quantity'     => $i->quantity,
                    'supplier_cost' => $i->supplier_cost_minor !== null
                        ? number_format($i->supplier_cost_minor / 100, 2) . ' ' . $i->currency : '—',
                ]),
                'events' => $so->events->map(fn ($e) => [
                    'event_type'  => $e->event_type,
                    'message'     => $e->message,
                    'actor_name'  => $e->actor?->name,
                    'actor_role'  => $e->actor_role,
                    'created_at'  => $e->created_at?->toDateTimeString(),
                ]),
                'created_at'          => $so->created_at?->toDateTimeString(),
                'placed_at'           => $so->placed_at?->toDateTimeString(),
                'shipped_at'          => $so->shipped_at?->toDateTimeString(),
                'delivered_at'        => $so->delivered_at?->toDateTimeString(),
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $so = SupplierOrder::forVendor($vendor->id)->findOrFail($id);

        $data = $request->validate([
            'supplier_reference' => 'nullable|string|max:120',
            'tracking_number'    => 'nullable|string|max:120',
            'tracking_url'       => 'nullable|url|max:1024',
            'carrier'            => 'nullable|string|max:80',
            'notes'              => 'nullable|string|max:1000',
        ]);

        $so->update($data);

        return back()->with('success', 'Supplier order reference updated.');
    }

    public function transition(Request $request, int $id): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $so = SupplierOrder::forVendor($vendor->id)->findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:placed,packed,shipped,delivered,cancelled',
            'note'   => 'nullable|string|max:500',
        ]);

        try {
            app(DropshipOrderCreator::class)->transition(
                $so,
                $data['status'],
                $request->user()->id,
                'vendor',
                $data['note'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.supplier-orders.show', $so->id)
            ->with('success', "Status updated to {$data['status']}.");
    }
}
