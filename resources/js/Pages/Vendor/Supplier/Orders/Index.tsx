import { Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface SO {
    id: number;
    number: string;
    platform: string | null;
    order_number: string | null;
    order_id: number;
    status: string;
    supplier_reference: string | null;
    tracking_number: string | null;
    cost: string;
    cost_minor: number;
    currency: string;
    items: { product_name: string; quantity: number }[];
    created_at: string | null;
}

// Phase 6 v7.3 NOTE: page-specific props type. NOT named "PageProps" — that
// name now shadows the augmented global type from @inertiajs/core (via
// resources/js/types/inertia.d.ts). Local types like this are fine because
// the page receives props as function arguments, not via usePage<>.
interface SupplierOrdersIndexProps {
    orders: { data: SO[]; links: { url: string | null; label: string; active: boolean }[] };
    statuses: string[];
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    placed: 'bg-blue-100 text-blue-800',
    packed: 'bg-indigo-100 text-indigo-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-slate-200 text-slate-700',
    failed: 'bg-rose-100 text-rose-800',
    refunded: 'bg-rose-100 text-rose-800',
};

export default function Index({ orders }: SupplierOrdersIndexProps) {
    return (
        <VendorLayout title="Supplier Orders">
            <div className="max-w-6xl mx-auto px-4 py-6">
                <p className="text-sm text-slate-500 mb-4">
                    Supplier orders auto-created from customer orders of your dropshipping products. Update the supplier reference + tracking number as you process each one.
                </p>

                <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-700 text-xs uppercase">
                            <tr>
                                <th className="text-left px-3 py-2">Supplier order</th>
                                <th className="text-left px-3 py-2">Customer order</th>
                                <th className="text-left px-3 py-2">Platform</th>
                                <th className="text-left px-3 py-2">Items</th>
                                <th className="text-left px-3 py-2">Cost</th>
                                <th className="text-left px-3 py-2">Status</th>
                                <th className="text-left px-3 py-2">Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.length === 0 && (
                                <tr><td colSpan={8} className="text-center text-slate-500 py-6">
                                    No supplier orders yet. They appear here when customers buy your dropshipping products.
                                </td></tr>
                            )}
                            {orders.data.map((so) => (
                                <tr key={so.id} className="border-t border-slate-100 align-top">
                                    <td className="px-3 py-2 font-mono text-xs text-slate-900">{so.number}</td>
                                    <td className="px-3 py-2 text-slate-700">
                                        <Link href={`/vendor/orders/${so.order_id}`} className="text-indigo-600 hover:underline">
                                            {so.order_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 text-slate-600">{so.platform ?? '—'}</td>
                                    <td className="px-3 py-2 text-slate-700">
                                        {so.items.map((i, idx) => (
                                            <div key={idx} className="text-xs">{i.quantity}× {i.product_name}</div>
                                        ))}
                                    </td>
                                    <td className="px-3 py-2 text-slate-700">{so.cost}</td>
                                    <td className="px-3 py-2">
                                        <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[so.status] ?? 'bg-slate-100'}`}>
                                            {so.status}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{so.created_at}</td>
                                    <td className="px-3 py-2 text-right">
                                        <Link href={`/vendor/supplier-orders/${so.id}`}
                                            className="text-indigo-600 hover:underline text-xs">View →</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </VendorLayout>
    );
}
