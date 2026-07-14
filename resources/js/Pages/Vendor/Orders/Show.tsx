import { Link, router, useForm } from '@inertiajs/react';
import { useState, type ChangeEvent, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';

interface ItemCustomization {
    id: number;
    field_key: string;
    field_label: string;
    field_type: string;
    value: string | null;
    file_original_name: string | null;
    has_file: boolean;
}
interface ItemProof {
    id: number;
    file_original_name: string;
    status: string;
    vendor_note: string | null;
    customer_response: string | null;
    sent_at: string | null;
    responded_at: string | null;
}
interface Item {
    id: number;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    unit_price: string;
    line_total: string;
    commission_percent: number;
    commission_amount: string;
    vendor_earning: string;
    fulfillment: string;
    // Phase 7
    customization_status: string | null;
    customization_fee: string | null;
    customizations: ItemCustomization[];
    proofs: ItemProof[];
    // Phase 9 v9.3 — per-line coupon allocation + net base for commission
    coupon_allocation: string;
    coupon_allocation_minor: number;
    net_line_total: string;
}

interface ShippingAddress {
    recipient_name: string;
    phone: string | null;
    country: string;
    state: string | null;
    city: string;
    area: string | null;
    block: string | null;
    street: string | null;
    building: string | null;
    floor: string | null;
    apartment: string | null;
    postal_code: string | null;
}

interface Props {
    order: {
        id: number;
        number: string;
        status: string;
        payment_status: string;
        fulfillment_status: string;
        currency: string;
        placed_at: string | null;
        paid_at: string | null;
        items: Item[];
        shipping_address: ShippingAddress | null;
        vendor_subtotal: string;
        vendor_commission: string;
        vendor_earnings: string;
        // Phase 9 v9.3 — order-level coupon + per-vendor breakdown
        coupon: {
            code: string;
            order_discount: string;
            order_discount_minor: number;
            vendor_allocation: string;
            vendor_allocation_minor: number;
        } | null;
        vendor_summary: {
            gross_total: string;
            gross_total_minor: number;
            allocated_coupon: string;
            allocated_coupon_minor: number;
            net_total: string;
            net_total_minor: number;
            commission: string;
            commission_minor: number;
            earnings: string;
            earnings_minor: number;
        };
    };
    // Phase 10 v10.11 §3 — server-computed availability per transition.
    // React just displays; the canonical rules live in
    // VendorOrderController::computeStatusOptions.
    status_options: Array<{
        value: string;
        label: string;
        available: boolean;
        reason: string | null;
    }>;
}

export default function VendorOrderShow({ order, status_options }: Props) {
    // Phase 10 v10.11 §3 — availability comes from server. The side action
    // buttons (Confirm / Ship / Deliver) and the dropdown both read from the
    // same source-of-truth array, so they cannot disagree.
    const availability = Object.fromEntries(
        status_options.map((o) => [o.value, o.available])
    ) as Record<string, boolean>;
    const canConfirm = availability['confirm'] ?? false;
    const canShip = availability['ship'] ?? false;
    const canDeliver = availability['deliver'] ?? false;

    const ship = () => {
        if (!confirm('Mark your items as shipped?')) return;
        router.post(`/vendor/orders/${order.id}/ship`);
    };
    const confirmOrder = () => {
        if (!confirm('Confirm this order?')) return;
        router.post(`/vendor/orders/${order.id}/confirm`);
    };
    const deliverOrder = () => {
        if (!confirm('Mark this order as delivered? (COD: confirm cash was received)')) return;
        router.post(`/vendor/orders/${order.id}/deliver`);
    };

    // Phase 10 v10.11 §3 — statusOptions now comes directly from the server,
    // replacing the v10.6 client-side computation that referenced non-existent
    // fulfillment_status values ('shipped'/'partially_shipped' aren't in the
    // fulfillment_status enum — they're order STATUS values) making canDeliver
    // always false and the dropdown effectively un-usable for many real
    // order states. The server uses Order::STATUS_* + OrderItem::FUL_* enum
    // constants directly so it can never drift from the schema.
    type StatusOption = { value: string; label: string; available: boolean; reason: string | null };
    const statusOptions: StatusOption[] = status_options;

    const [statusSubmitting, setStatusSubmitting] = useState<string | null>(null);

    const submitStatusChange = (action: 'confirm' | 'ship' | 'deliver') => {
        const url = `/vendor/orders/${order.id}/${action}`;
        setStatusSubmitting(action);
        router.post(url, {}, {
            preserveScroll: true,
            onFinish: () => setStatusSubmitting(null),
        });
    };

    const onStatusChange = (e: ChangeEvent<HTMLSelectElement>) => {
        const v = e.target.value;
        if (v === '__current' || v === '') return;
        // No native confirm() — that was the v10.3 UX trap that made users
        // think the dropdown was broken when they accidentally dismissed
        // the dialog. Submission is direct; Inertia's flash messages
        // provide the feedback after server response.
        if (v === 'confirm' || v === 'ship' || v === 'deliver') {
            submitStatusChange(v);
        }
        // Always reset visual state — even if we ignored an option, this
        // returns the <select> to its labeled current-status default.
        e.target.value = '__current';
    };

    return (
        <VendorLayout title={`Order ${order.number}`}>
            <Link href="/vendor/orders" className="text-sm text-slate-500 hover:text-slate-700">
                ← All orders
            </Link>

            <div className="mt-2 mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 font-mono">{order.number}</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Placed {order.placed_at}
                        {order.paid_at && ` · Paid ${order.paid_at}`}
                    </p>
                </div>
                <div className="flex gap-2 items-center flex-wrap">
                    <span className="px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
                        {order.status.replace('_', ' ')}
                    </span>
                    <span className="px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
                        payment: {order.payment_status.replace('_', ' ')}
                    </span>

                    {/* Phase 10 v10.6 — explicit fulfillment-status dropdown.
                        v10.3 used native confirm() inside the dropdown handler;
                        accidental Cancel made the dropdown look broken. v10.6
                        submits directly via Inertia and shows an inline
                        "Updating…" state. Payment status remains read-only above.

                        Phase 11B.3 v11B.3.3 §6 — belt-and-suspenders fix.
                        Even after the global CSS fix (removed
                        overflow-wrap: anywhere from span), keep whitespace-nowrap
                        on the "Update status:" label so it can't fragment
                        under any container width. The <select> is allowed
                        to shrink (min-w-0) while the label holds its width. */}
                    <label className="flex items-center gap-2 text-xs flex-wrap">
                        <span className="text-slate-500 whitespace-nowrap" data-testid="vendor-order-update-status-label">
                            Update status:
                        </span>
                        <select
                            onChange={onStatusChange}
                            defaultValue="__current"
                            disabled={statusSubmitting !== null}
                            data-testid="vendor-order-status-dropdown"
                            className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm min-w-0 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-progress"
                        >
                            {statusOptions.map((o) => (
                                <option key={o.value} value={o.value} disabled={!o.available} title={o.reason ?? ''}>
                                    {o.label}{o.reason && !o.available ? ` (${o.reason})` : ''}
                                </option>
                            ))}
                        </select>
                        {statusSubmitting && (
                            <span className="text-xs text-indigo-700 animate-pulse" data-testid="vendor-order-status-submitting">
                                Updating…
                            </span>
                        )}
                    </label>

                    {canConfirm && (
                        <button
                            onClick={confirmOrder}
                            className="rounded-md bg-emerald-600 text-white px-4 py-2 text-sm hover:bg-emerald-700"
                            data-testid="vendor-order-confirm"
                        >
                            Confirm order
                        </button>
                    )}
                    {canShip && (
                        <button
                            onClick={ship}
                            className="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700"
                            data-testid="vendor-order-ship"
                        >
                            Mark items shipped
                        </button>
                    )}
                    {canDeliver && (
                        <button
                            onClick={deliverOrder}
                            className="rounded-md bg-teal-600 text-white px-4 py-2 text-sm hover:bg-teal-700"
                            data-testid="vendor-order-deliver"
                        >
                            Mark delivered
                        </button>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                <div className="space-y-6">
                    {/* Your items */}
                    <div className="bg-white border border-slate-200 rounded-xl overflow-hidden">
                        <div className="px-4 py-3 border-b border-slate-200 text-sm font-medium text-slate-700">
                            Your items in this order
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="text-left py-2 px-4">Product</th>
                                    <th className="text-right py-2 px-4">Qty</th>
                                    <th className="text-right py-2 px-4">Unit</th>
                                    <th className="text-right py-2 px-4">Line total</th>
                                    <th className="text-right py-2 px-4">Commission</th>
                                    <th className="text-right py-2 px-4">Your earning</th>
                                    <th className="text-left py-2 px-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {order.items.map((item) => (
                                    <tr key={item.id} className="border-t border-slate-100">
                                        <td className="py-3 px-4">
                                            <div className="font-medium text-slate-900">{item.product_name}</div>
                                            {item.variant_name && (
                                                <div className="text-xs text-slate-500">{item.variant_name}</div>
                                            )}
                                        </td>
                                        <td className="py-3 px-4 text-right">{item.quantity}</td>
                                        <td className="py-3 px-4 text-right">{item.unit_price}</td>
                                        <td className="py-3 px-4 text-right font-medium">{item.line_total}</td>
                                        <td className="py-3 px-4 text-right text-slate-600">
                                            {item.commission_amount}
                                            <span className="text-xs text-slate-400 ml-1">({item.commission_percent}%)</span>
                                        </td>
                                        <td className="py-3 px-4 text-right font-medium text-emerald-700">
                                            {item.vendor_earning}
                                        </td>
                                        <td className="py-3 px-4">
                                            <span className="px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700">
                                                {item.fulfillment.replace('_', ' ')}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Phase 7 — per-item customization details + proof upload */}
                    {order.items.filter((i) => i.customizations.length > 0 || i.proofs.length > 0).map((item) => (
                        <div key={`cust-${item.id}`} className="bg-white border border-slate-200 rounded-xl p-5">
                            <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
                                Customization — {item.product_name}
                            </h3>
                            <CustomizationVendorBlock orderItemId={item.id} item={item} />
                        </div>
                    ))}

                    {/* Shipping address */}
                    {order.shipping_address && (
                        <div className="bg-white border border-slate-200 rounded-xl p-5">
                            <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Ship to</h3>
                            <div className="text-sm text-slate-700">
                                <div className="font-medium">{order.shipping_address.recipient_name}</div>
                                {order.shipping_address.phone && <div>{order.shipping_address.phone}</div>}
                                {[
                                    order.shipping_address.building && `Building ${order.shipping_address.building}`,
                                    order.shipping_address.floor && `Floor ${order.shipping_address.floor}`,
                                    order.shipping_address.apartment && `Apt ${order.shipping_address.apartment}`,
                                ].filter(Boolean).join(', ') && (
                                    <div>{[
                                        order.shipping_address.building && `Building ${order.shipping_address.building}`,
                                        order.shipping_address.floor && `Floor ${order.shipping_address.floor}`,
                                        order.shipping_address.apartment && `Apt ${order.shipping_address.apartment}`,
                                    ].filter(Boolean).join(', ')}</div>
                                )}
                                {order.shipping_address.street && <div>{order.shipping_address.street}</div>}
                                {order.shipping_address.block && <div>Block {order.shipping_address.block}</div>}
                                {order.shipping_address.area && <div>{order.shipping_address.area}</div>}
                                <div>
                                    {order.shipping_address.city}
                                    {order.shipping_address.state && `, ${order.shipping_address.state}`}
                                    {order.shipping_address.postal_code && ` ${order.shipping_address.postal_code}`}
                                </div>
                                <div>{order.shipping_address.country}</div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Right: vendor totals */}
                <aside>
                    <div className="bg-white border border-slate-200 rounded-xl p-5">
                        <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Your portion</h3>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Gross subtotal</dt>
                                <dd className="text-slate-900">{order.vendor_summary.gross_total} {order.currency}</dd>
                            </div>
                            {/* Phase 9 v9.3 — coupon allocation off this vendor's lines */}
                            {order.coupon && order.vendor_summary.allocated_coupon_minor > 0 && (
                                <div className="flex justify-between text-amber-700" data-testid="vendor-order-coupon-allocation">
                                    <dt>Coupon ({order.coupon.code}) — your share</dt>
                                    <dd>−{order.vendor_summary.allocated_coupon} {order.currency}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Net subtotal (what customer paid)</dt>
                                <dd className="text-slate-900 font-medium">{order.vendor_summary.net_total} {order.currency}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Platform commission</dt>
                                <dd className="text-rose-700">−{order.vendor_summary.commission} {order.currency}</dd>
                            </div>
                            <div className="flex justify-between font-semibold text-base border-t border-slate-100 pt-2">
                                <dt className="text-slate-900">Your earnings</dt>
                                <dd className="text-emerald-700" data-testid="vendor-order-earnings">{order.vendor_summary.earnings} {order.currency}</dd>
                            </div>
                        </dl>
                        <p className="text-xs text-slate-500 mt-3">
                            {order.coupon && order.vendor_summary.allocated_coupon_minor > 0
                                ? `The coupon "${order.coupon.code}" reduced the order subtotal — your share above was allocated proportionally. Commission is on the net (post-discount) amount.`
                                : 'Earnings release 7 days after the order is marked delivered.'}
                        </p>
                    </div>
                </aside>
            </div>
        </VendorLayout>
    );
}

function CustomizationVendorBlock({ orderItemId, item }: { orderItemId: number; item: Item }) {
    const [file, setFile] = useState<File | null>(null);
    const form = useForm<{ file: File | null; vendor_note: string; send_now: boolean }>({
        file: null, vendor_note: '', send_now: true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!file) return;
        form.setData('file', file);
        form.post(`/vendor/orders/items/${orderItemId}/proofs`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setFile(null); form.reset('vendor_note'); },
        });
    };

    const sendDraft = (proofId: number) => {
        router.post(`/vendor/orders/items/${orderItemId}/proofs/${proofId}/send`, {}, { preserveScroll: true });
    };

    return (
        <div className="space-y-4 text-sm">
            {/* Customer customization data */}
            {item.customizations.length > 0 && (
                <div>
                    <div className="text-xs font-semibold uppercase text-slate-500 mb-1">Customer inputs</div>
                    <ul className="space-y-1">
                        {item.customizations.map((c) => (
                            <li key={c.id} className="flex items-baseline gap-2">
                                <span className="text-slate-500 min-w-[140px]">{c.field_label}:</span>
                                {c.has_file ? (
                                    <a href={`/vendor/orders/items/${orderItemId}/files/customization/${c.id}`}
                                       target="_blank" rel="noopener noreferrer"
                                       className="text-indigo-600 hover:underline">
                                        {c.file_original_name ?? 'download file'}
                                    </a>
                                ) : (
                                    <span className="text-slate-800">{c.value ?? '—'}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                    {item.customization_status && (
                        <div className="text-xs text-slate-500 mt-2">Status: <span className="text-slate-700 font-medium">{item.customization_status.replace(/_/g, ' ')}</span></div>
                    )}
                </div>
            )}

            {/* Existing proofs */}
            {item.proofs.length > 0 && (
                <div className="border-t border-slate-100 pt-3">
                    <div className="text-xs font-semibold uppercase text-slate-500 mb-2">Proofs</div>
                    <ul className="space-y-2">
                        {item.proofs.map((p) => (
                            <li key={p.id} className="p-3 bg-slate-50 border border-slate-200 rounded flex items-start justify-between">
                                <div>
                                    <a href={`/vendor/orders/items/${orderItemId}/files/proof/${p.id}`}
                                       target="_blank" rel="noopener noreferrer"
                                       className="text-indigo-600 hover:underline text-sm">
                                        {p.file_original_name}
                                    </a>
                                    <div className="text-xs text-slate-500 mt-0.5">Status: {p.status}</div>
                                    {p.vendor_note && <div className="text-xs text-slate-700 mt-1"><strong>Your note:</strong> {p.vendor_note}</div>}
                                    {p.customer_response && <div className="text-xs text-slate-700 mt-1"><strong>Customer:</strong> {p.customer_response}</div>}
                                </div>
                                {(p.status === 'draft' || p.status === 'rejected') && (
                                    <button onClick={() => sendDraft(p.id)} className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded">
                                        {p.status === 'rejected' ? 'Resend' : 'Send to customer'}
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Upload new proof */}
            <form onSubmit={submit} className="border-t border-slate-100 pt-3 space-y-2" encType="multipart/form-data">
                <div className="text-xs font-semibold uppercase text-slate-500">Upload a proof for the customer</div>
                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    className="text-sm" />
                {form.errors.file && <div className="text-xs text-rose-600">{form.errors.file}</div>}
                <textarea value={form.data.vendor_note}
                    onChange={(e) => form.setData('vendor_note', e.target.value)}
                    placeholder="Optional note to the customer"
                    rows={2} className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                <label className="flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" checked={form.data.send_now}
                        onChange={(e) => form.setData('send_now', e.target.checked)} />
                    Send to customer immediately (uncheck to save as draft)
                </label>
                <button type="submit" disabled={!file || form.processing}
                    className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-3 py-1.5 rounded">
                    {form.processing ? 'Uploading…' : 'Upload proof'}
                </button>
            </form>
        </div>
    );
}
