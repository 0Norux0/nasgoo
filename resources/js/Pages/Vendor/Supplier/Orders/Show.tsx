import { useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface SO {
    id: number;
    number: string;
    platform: string | null;
    order_number: string | null;
    order_id: number;
    status: string;
    supplier_reference: string | null;
    tracking_number: string | null;
    tracking_url: string | null;
    carrier: string | null;
    notes: string | null;
    cost: string;
    currency: string;
    items: { product_name: string; quantity: number; supplier_cost: string }[];
    events: { event_type: string; message: string | null; actor_name: string | null; actor_role: string | null; created_at: string | null }[];
    created_at: string | null;
    placed_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
}

export default function Show({ so }: { so: SO }) {
    // Phase 6 v7.3 — use canonical SharedProps so usePage's generic
    // satisfies the augmented @inertiajs/core PageProps constraint.
    const { props } = usePage<SharedProps>();
    const flash = props.flash ?? {};

    const refForm = useForm({
        supplier_reference: so.supplier_reference ?? '',
        tracking_number:    so.tracking_number ?? '',
        tracking_url:       so.tracking_url ?? '',
        carrier:            so.carrier ?? '',
        notes:              so.notes ?? '',
    });

    const transition = useForm<{ status: string; note: string }>({ status: '', note: '' });

    const updateRefs = (e: FormEvent) => {
        e.preventDefault();
        refForm.patch(`/vendor/supplier-orders/${so.id}`);
    };

    const doTransition = (status: string) => {
        transition.transform((d) => ({ ...d, status }));
        transition.post(`/vendor/supplier-orders/${so.id}/transition`);
    };

    // Determine which next transitions are allowed from current status
    const allowedNext: Record<string, string[]> = {
        pending:   ['placed', 'cancelled'],
        placed:    ['packed', 'shipped', 'cancelled'],
        packed:    ['shipped', 'cancelled'],
        shipped:   ['delivered'],
        delivered: [],
        cancelled: [],
        failed:    ['placed'],
        refunded:  [],
    };
    const next = allowedNext[so.status] ?? [];

    return (
        <VendorLayout title={`Supplier Order ${so.number}`}>
            <div className="max-w-4xl mx-auto px-4 py-6">
                {flash.success && <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{flash.success}</div>}
                {flash.error && <div className="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded text-sm">{flash.error}</div>}

                <div className="bg-white border border-slate-200 rounded p-4 mb-4">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <div className="font-mono text-sm">{so.number}</div>
                            <div className="text-xs text-slate-500">Customer order: {so.order_number} · Platform: {so.platform ?? '—'}</div>
                        </div>
                        <span className="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs rounded">{so.status}</span>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 text-xs">
                        <div><span className="text-slate-500">Cost:</span> <strong>{so.cost}</strong></div>
                        <div><span className="text-slate-500">Created:</span> {so.created_at}</div>
                        <div><span className="text-slate-500">Placed:</span> {so.placed_at ?? '—'}</div>
                        <div><span className="text-slate-500">Shipped:</span> {so.shipped_at ?? '—'}</div>
                    </div>
                </div>

                {/* Items */}
                <div className="bg-white border border-slate-200 rounded p-4 mb-4">
                    <h3 className="font-medium mb-2 text-slate-700">Items</h3>
                    <table className="w-full text-sm">
                        <thead className="text-xs text-slate-500">
                            <tr><th className="text-left">Product</th><th className="text-left">Qty</th><th className="text-left">Supplier cost</th></tr>
                        </thead>
                        <tbody>
                            {so.items.map((it, i) => (
                                <tr key={i} className="border-t border-slate-100">
                                    <td className="py-1">{it.product_name}</td>
                                    <td className="py-1">{it.quantity}</td>
                                    <td className="py-1">{it.supplier_cost}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* References + tracking */}
                <form onSubmit={updateRefs} className="bg-white border border-slate-200 rounded p-4 mb-4 space-y-2">
                    <h3 className="font-medium mb-2 text-slate-700">Supplier reference & tracking</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Supplier reference</span>
                            <input value={refForm.data.supplier_reference}
                                onChange={(e) => refForm.setData('supplier_reference', e.target.value)}
                                placeholder="e.g. AliExpress order #ABC"
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Tracking number</span>
                            <input value={refForm.data.tracking_number}
                                onChange={(e) => refForm.setData('tracking_number', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Tracking URL</span>
                            <input value={refForm.data.tracking_url}
                                onChange={(e) => refForm.setData('tracking_url', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Carrier</span>
                            <input value={refForm.data.carrier}
                                onChange={(e) => refForm.setData('carrier', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                    </div>
                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">Notes</span>
                        <textarea value={refForm.data.notes}
                            onChange={(e) => refForm.setData('notes', e.target.value)}
                            rows={2} className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    </label>
                    <button type="submit" disabled={refForm.processing}
                        className="bg-slate-700 hover:bg-slate-800 disabled:opacity-60 text-white text-sm px-3 py-1.5 rounded">
                        {refForm.processing ? 'Saving…' : 'Save references'}
                    </button>
                </form>

                {/* Transitions */}
                {next.length > 0 && (
                    <div className="bg-white border border-slate-200 rounded p-4 mb-4">
                        <h3 className="font-medium mb-2 text-slate-700">Status transitions</h3>
                        <div className="flex flex-wrap gap-2">
                            {next.map((n) => (
                                <button key={n} type="button"
                                    onClick={() => doTransition(n)}
                                    disabled={transition.processing}
                                    className={`text-sm px-3 py-1.5 rounded text-white ${n === 'cancelled' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700'}`}>
                                    Mark {n}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Events */}
                <div className="bg-white border border-slate-200 rounded p-4">
                    <h3 className="font-medium mb-2 text-slate-700">Event log</h3>
                    {so.events.length === 0 ? (
                        <p className="text-sm text-slate-500">No events yet.</p>
                    ) : (
                        <ul className="text-sm space-y-1">
                            {so.events.map((e, i) => (
                                <li key={i} className="border-l-2 border-slate-200 pl-2 py-1">
                                    <span className="text-slate-700 font-medium">{e.event_type}</span>{' '}
                                    {e.message && <span className="text-slate-600">— {e.message}</span>}
                                    <div className="text-xs text-slate-400">
                                        {e.actor_name ?? 'system'} ({e.actor_role ?? '—'}) · {e.created_at}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </VendorLayout>
    );
}
