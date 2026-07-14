import { Link, router } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface VendorOrderRow {
    id: number;
    number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    currency: string;
    vendor_total: string;
    vendor_earnings: string;
    items_count: number;
    placed_at: string | null;
}

interface Props {
    orders: {
        data: VendorOrderRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
}

/**
 * Phase 10 v10.1 — vendor orders list now has visible inline action buttons.
 *
 * Pre-v10.1: action buttons (confirm / ship / deliver) only existed on the
 * individual /vendor/orders/{id} show page. The dev had to drill into every
 * order to update its status — they reported "vendor cannot update order
 * status" because the affordance wasn't on the list.
 *
 * v10.1: each row exposes the appropriate action(s) for that order's current
 * state. The same authorization rules apply server-side (vendor:approved
 * middleware + OrderLifecycleService gates).
 *
 * Mobile: table wrapped in overflow-x-auto so narrow viewports scroll
 * horizontally rather than overflowing the page.
 */
export default function VendorOrdersIndex({ orders }: Props) {
    const post = (url: string, msg: string) => {
        if (!confirm(msg)) return;
        router.post(url, {}, { preserveScroll: true });
    };

    return (
        <VendorLayout title="Orders">
            <div className="flex items-center justify-between mb-6 flex-wrap gap-2">
                <p className="text-sm text-slate-500">{orders.total} order(s) containing your products</p>
            </div>

            {orders.data.length === 0 ? (
                <div className="bg-white border border-slate-200 border-dashed rounded-xl p-12 text-center text-slate-500">
                    No orders yet. When customers buy your products, they'll appear here.
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl overflow-x-auto">
                    <table className="min-w-[900px] w-full text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="text-left py-2 px-4">Order</th>
                                <th className="text-left py-2 px-4">Date</th>
                                <th className="text-left py-2 px-4">Status</th>
                                <th className="text-left py-2 px-4">Fulfillment</th>
                                <th className="text-right py-2 px-4">Items</th>
                                <th className="text-right py-2 px-4">Your subtotal</th>
                                <th className="text-right py-2 px-4">Your earnings</th>
                                <th className="text-right py-2 px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((o) => {
                                const canConfirm = o.status === 'pending_payment' || o.status === 'paid';
                                const canShip = (o.payment_status === 'paid') && (o.fulfillment_status === 'unfulfilled' || o.fulfillment_status === 'partially_shipped');
                                const canDeliver = o.fulfillment_status === 'shipped' || o.fulfillment_status === 'partially_shipped';
                                return (
                                    <tr key={o.id} className="border-t border-slate-100 hover:bg-slate-50">
                                        <td className="py-3 px-4 font-mono">
                                            <Link href={`/vendor/orders/${o.id}`} className="text-indigo-600 hover:underline">
                                                {o.number}
                                            </Link>
                                        </td>
                                        <td className="py-3 px-4 text-slate-600 whitespace-nowrap">{o.placed_at}</td>
                                        <td className="py-3 px-4">
                                            <span className="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 whitespace-nowrap">
                                                {o.status.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span className="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 whitespace-nowrap">
                                                {o.fulfillment_status.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-right">{o.items_count}</td>
                                        <td className="py-3 px-4 text-right text-slate-700 whitespace-nowrap">{o.vendor_total} {o.currency}</td>
                                        <td className="py-3 px-4 text-right font-medium text-emerald-700 whitespace-nowrap">
                                            {o.vendor_earnings} {o.currency}
                                        </td>
                                        <td className="py-3 px-4 text-right">
                                            <div className="flex gap-1 justify-end flex-wrap">
                                                {canConfirm && (
                                                    <button
                                                        onClick={() => post(`/vendor/orders/${o.id}/confirm`, `Confirm order ${o.number}?`)}
                                                        className="rounded bg-emerald-600 text-white px-2 py-1 text-xs hover:bg-emerald-700"
                                                        data-testid={`row-confirm-${o.id}`}
                                                    >
                                                        Confirm
                                                    </button>
                                                )}
                                                {canShip && (
                                                    <button
                                                        onClick={() => post(`/vendor/orders/${o.id}/ship`, `Mark your items in ${o.number} as shipped?`)}
                                                        className="rounded bg-indigo-600 text-white px-2 py-1 text-xs hover:bg-indigo-700"
                                                        data-testid={`row-ship-${o.id}`}
                                                    >
                                                        Ship
                                                    </button>
                                                )}
                                                {canDeliver && (
                                                    <button
                                                        onClick={() => post(`/vendor/orders/${o.id}/deliver`, `Mark order ${o.number} as delivered?`)}
                                                        className="rounded bg-teal-600 text-white px-2 py-1 text-xs hover:bg-teal-700"
                                                        data-testid={`row-deliver-${o.id}`}
                                                    >
                                                        Deliver
                                                    </button>
                                                )}
                                                <Link href={`/vendor/orders/${o.id}`} className="text-slate-500 hover:text-slate-900 text-xs px-2 py-1">
                                                    Open →
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {orders.links.length > 3 && (
                <nav className="mt-6 flex flex-wrap gap-1 justify-center">
                    {orders.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            className={`px-3 py-1.5 rounded border text-sm ${
                                link.active
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : link.url
                                      ? 'border-slate-300 text-slate-700 hover:bg-slate-50'
                                      : 'border-slate-200 text-slate-400 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </VendorLayout>
    );
}
