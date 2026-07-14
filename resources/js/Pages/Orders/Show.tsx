import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface ItemCustomization {
    id: number;
    field_key: string;
    field_label: string;
    field_type: string;
    value: string | null;
    file_original_name: string | null;
    has_file: boolean;
}
interface LatestProof {
    id: number;
    file_original_name: string;
    file_mime: string;
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
    fulfillment: string;
    // Phase 7
    customization_status: string | null;
    customization_fee: string | null;
    customizations: ItemCustomization[];
    latest_proof: LatestProof | null;
    // Phase 9 v9.1 — review eligibility
    product_id: number | null;
    product_slug: string | null;
    can_review: boolean;
    already_reviewed: boolean;
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
interface Event {
    event_type: string;
    message: string | null;
    actor_role: string | null;
    actor_name: string | null;
    at: string | null;
}
interface Payment {
    id: number;
    method_slug: string;
    status: string;
    amount: string;
    refunded: string;
    reference: string | null;
    captured_at: string | null;
}

interface Props {
    order: {
        id: number;
        number: string;
        status: string;
        payment_status: string;
        fulfillment_status: string;
        currency: string;
        subtotal: string;
        shipping: string;
        tax: string;
        discount: string;
        total: string;
        customer_notes: string | null;
        placed_at: string | null;
        paid_at: string | null;
        shipped_at: string | null;
        delivered_at: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        items: Item[];
        shipping_address: ShippingAddress | null;
        events: Event[];
        payments: Payment[];
        // Phase 9 v9.3 — coupon snapshot on order
        coupon: {
            id: number | null;
            code: string | null;
            discount: string;
            discount_minor: number;
        } | null;
    };
}

export default function OrderShow({ order }: Props) {
    const [showCancel, setShowCancel] = useState(false);
    const cancelForm = useForm({ reason: '' });

    const canCancel = ['pending_payment', 'paid', 'confirmed'].includes(order.status);

    const submitCancel = (e: FormEvent) => {
        e.preventDefault();
        cancelForm.post(`/orders/${order.id}/cancel`, { preserveScroll: true, onSuccess: () => setShowCancel(false) });
    };

    return (
        <StorefrontLayout title={`Order ${order.number}`}>
            <div className="max-w-5xl mx-auto">
                <Link href="/orders" className="text-sm text-slate-500 hover:text-slate-700">
                    ← All orders
                </Link>
                <div className="flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 font-mono">{order.number}</h1>
                        <p className="text-sm text-slate-500 mt-1">Placed {order.placed_at}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        <span className="px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
                            {order.status.replace('_', ' ')}
                        </span>
                        <span className="px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
                            payment: {order.payment_status.replace('_', ' ')}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8">
                    <div className="space-y-6">
                        {/* Items */}
                        <Section title="Items">
                            <table className="w-full text-sm">
                                <tbody>
                                    {order.items.map((item) => (
                                        <tr key={item.id} className="border-t border-slate-100 first:border-t-0">
                                            <td className="py-3 pr-2">
                                                <div className="font-medium text-slate-900">{item.product_name}</div>
                                                {item.variant_name && (
                                                    <div className="text-xs text-slate-500">{item.variant_name}</div>
                                                )}
                                                <div className="text-xs text-slate-400 mt-0.5">
                                                    {item.unit_price} {order.currency} × {item.quantity}
                                                </div>
                                            </td>
                                            <td className="py-3 text-right whitespace-nowrap">
                                                <div className="font-medium text-slate-900">
                                                    {item.line_total} {order.currency}
                                                </div>
                                                <div className="text-xs text-slate-500 mt-0.5">{item.fulfillment.replace('_', ' ')}</div>
                                                {item.customization_status && item.customization_status !== 'pending' && (
                                                    <div className="text-xs text-indigo-600 mt-0.5">{item.customization_status.replace(/_/g, ' ')}</div>
                                                )}
                                                {/* Phase 9 v9.1 — Write Review button for delivered, un-reviewed items */}
                                                {item.can_review && item.product_slug && (
                                                    <Link
                                                        href={`/products/${item.product_slug}#review-form`}
                                                        className="inline-block mt-2 text-xs text-indigo-600 hover:underline font-medium"
                                                        data-testid="write-review-button"
                                                    >
                                                        Write a Review →
                                                    </Link>
                                                )}
                                                {item.already_reviewed && (
                                                    <div className="text-xs text-green-700 mt-2" data-testid="already-reviewed-label">
                                                        ✓ Review submitted
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Section>

                        {/* Phase 7 — per-item customization summary + proof approval */}
                        {order.items.filter((i) => i.customizations.length > 0 || i.latest_proof).map((item) => (
                            <Section key={`cust-${item.id}`} title={`Customization — ${item.product_name}`}>
                                <CustomizationBlock orderId={order.id} item={item} />
                            </Section>
                        ))}

                        {/* Shipping address */}
                        {order.shipping_address && (
                            <Section title="Shipping to">
                                <div className="text-sm text-slate-700">
                                    <div className="font-medium">{order.shipping_address.recipient_name}</div>
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
                                {order.shipping_address.phone && (
                                    <div className="text-xs text-slate-500 mt-2">Phone: {order.shipping_address.phone}</div>
                                )}
                            </Section>
                        )}

                        {/* Customer notes */}
                        {order.customer_notes && (
                            <Section title="Your notes">
                                <p className="text-sm text-slate-700 whitespace-pre-line">{order.customer_notes}</p>
                            </Section>
                        )}

                        {/* Cancellation */}
                        {order.cancelled_at && (
                            <Section title="Cancellation">
                                <p className="text-sm text-rose-700">
                                    Cancelled on {order.cancelled_at}.
                                    {order.cancellation_reason && (
                                        <>
                                            <br />
                                            Reason: {order.cancellation_reason}
                                        </>
                                    )}
                                </p>
                            </Section>
                        )}

                        {/* Events timeline */}
                        <Section title="Activity">
                            <ul className="space-y-3 text-sm">
                                {order.events.map((evt, i) => (
                                    <li key={i} className="flex gap-3">
                                        <div className="w-2 h-2 mt-1.5 rounded-full bg-slate-300 flex-shrink-0" />
                                        <div className="flex-1">
                                            <div className="text-slate-900">
                                                <span className="font-medium">{evt.event_type.replace('_', ' ')}</span>
                                                {evt.message && <span className="text-slate-600"> — {evt.message}</span>}
                                            </div>
                                            <div className="text-xs text-slate-400 mt-0.5">
                                                {evt.at}{evt.actor_role && ` · by ${evt.actor_role}`}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    </div>

                    {/* Right: summary + payments + cancel */}
                    <aside className="space-y-4">
                        <div className="bg-white border border-slate-200 rounded-xl p-5">
                            <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Summary</h2>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between"><dt className="text-slate-500">Subtotal</dt><dd>{order.subtotal} {order.currency}</dd></div>
                                {/* Phase 9 v9.3 — coupon discount line + code */}
                                {order.coupon && (
                                    <div className="flex justify-between text-green-700" data-testid="order-coupon-line">
                                        <dt>Coupon ({order.coupon.code})</dt>
                                        <dd>−{order.coupon.discount} {order.currency}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between"><dt className="text-slate-500">Shipping</dt><dd>{order.shipping} {order.currency}</dd></div>
                                {parseFloat(order.tax) > 0 && (
                                    <div className="flex justify-between"><dt className="text-slate-500">Tax</dt><dd>{order.tax} {order.currency}</dd></div>
                                )}
                                <div className="flex justify-between font-semibold text-base border-t border-slate-100 pt-2">
                                    <dt className="text-slate-900">Total</dt>
                                    <dd className="text-slate-900">{order.total} {order.currency}</dd>
                                </div>
                            </dl>
                        </div>

                        {order.payments.length > 0 && (
                            <div className="bg-white border border-slate-200 rounded-xl p-5">
                                <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Payment</h2>
                                {order.payments.map((p) => (
                                    <div key={p.id} className="text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-slate-700">{p.method_slug}</span>
                                            <span className="text-slate-900 font-medium">{p.status.replace('_', ' ')}</span>
                                        </div>
                                        {p.reference && (
                                            <div className="text-xs text-slate-500 mt-1 font-mono">{p.reference}</div>
                                        )}
                                        {parseFloat(p.refunded) > 0 && (
                                            <div className="text-xs text-rose-600 mt-1">
                                                Refunded: {p.refunded} {order.currency}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        {canCancel && (
                            <div className="bg-white border border-slate-200 rounded-xl p-5">
                                {!showCancel ? (
                                    <button
                                        onClick={() => setShowCancel(true)}
                                        className="w-full text-sm text-rose-600 hover:underline"
                                    >
                                        Cancel this order
                                    </button>
                                ) : (
                                    <form onSubmit={submitCancel}>
                                        <h3 className="text-sm font-semibold text-slate-700 mb-2">Cancel order</h3>
                                        <textarea
                                            value={cancelForm.data.reason}
                                            onChange={(e) => cancelForm.setData('reason', e.target.value)}
                                            rows={2}
                                            maxLength={500}
                                            placeholder="Reason"
                                            required
                                            className="w-full text-sm border-slate-300 rounded px-2 py-1 border focus:border-rose-500 focus:ring-rose-500 mb-2"
                                        />
                                        <div className="flex gap-2">
                                            <button
                                                type="submit"
                                                disabled={cancelForm.processing}
                                                className="flex-1 rounded-md bg-rose-600 text-white px-3 py-1.5 text-sm hover:bg-rose-700 disabled:opacity-60"
                                            >
                                                Confirm cancel
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setShowCancel(false)}
                                                className="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-900"
                                            >
                                                Keep
                                            </button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </StorefrontLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-5">
            <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">{title}</h2>
            {children}
        </div>
    );
}

function CustomizationBlock({ orderId, item }: { orderId: number; item: Item }) {
    const [showReject, setShowReject] = useState(false);
    const approveForm = useForm<{ note: string }>({ note: '' });
    const rejectForm = useForm<{ reason: string }>({ reason: '' });

    const approve = () => {
        if (!item.latest_proof) return;
        approveForm.post(`/orders/${orderId}/items/${item.id}/proofs/${item.latest_proof.id}/approve`, { preserveScroll: true });
    };
    const reject = (e: FormEvent) => {
        e.preventDefault();
        if (!item.latest_proof) return;
        rejectForm.post(`/orders/${orderId}/items/${item.id}/proofs/${item.latest_proof.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => setShowReject(false),
        });
    };

    return (
        <div className="space-y-3 text-sm">
            {item.customizations.length > 0 && (
                <div>
                    <div className="text-xs font-semibold uppercase text-slate-500 mb-1">Your customizations</div>
                    <ul className="space-y-1">
                        {item.customizations.map((c) => (
                            <li key={c.id} className="flex items-baseline gap-2">
                                <span className="text-slate-500 min-w-[140px]">{c.field_label}:</span>
                                {c.has_file ? (
                                    <a href={`/orders/${orderId}/items/${item.id}/files/customization/${c.id}`}
                                       target="_blank" rel="noopener noreferrer"
                                       className="text-indigo-600 hover:underline">
                                        {c.file_original_name ?? 'view file'}
                                    </a>
                                ) : (
                                    <span className="text-slate-800">{c.value ?? '—'}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {item.customization_fee && (
                <div className="text-xs text-slate-500">Customization fee included: +{item.customization_fee}</div>
            )}

            {item.latest_proof ? (
                <div className="border-t border-slate-100 pt-3">
                    <div className="text-xs font-semibold uppercase text-slate-500 mb-2">Design proof</div>
                    <div className="p-3 bg-slate-50 border border-slate-200 rounded">
                        <div className="flex items-center justify-between">
                            <div>
                                <a href={`/orders/${orderId}/items/${item.id}/files/proof/${item.latest_proof.id}`}
                                   target="_blank" rel="noopener noreferrer"
                                   className="text-indigo-600 hover:underline text-sm">
                                    {item.latest_proof.file_original_name}
                                </a>
                                <div className="text-xs text-slate-500 mt-0.5">Status: {item.latest_proof.status}</div>
                                {item.latest_proof.vendor_note && (
                                    <div className="text-xs text-slate-700 mt-1"><strong>Vendor note:</strong> {item.latest_proof.vendor_note}</div>
                                )}
                                {item.latest_proof.customer_response && (
                                    <div className="text-xs text-slate-700 mt-1"><strong>Your response:</strong> {item.latest_proof.customer_response}</div>
                                )}
                            </div>
                            {item.latest_proof.status === 'sent' && (
                                <div className="flex gap-2">
                                    <button onClick={approve} disabled={approveForm.processing}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-3 py-1.5 rounded disabled:opacity-60">
                                        Approve
                                    </button>
                                    <button onClick={() => setShowReject((v) => !v)}
                                        className="bg-rose-600 hover:bg-rose-700 text-white text-sm px-3 py-1.5 rounded">
                                        Reject
                                    </button>
                                </div>
                            )}
                        </div>
                        {showReject && (
                            <form onSubmit={reject} className="mt-3 space-y-2">
                                <textarea value={rejectForm.data.reason}
                                    onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    placeholder="Why are you rejecting this proof? (5-1000 chars)"
                                    rows={3} className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                {rejectForm.errors.reason && <div className="text-xs text-rose-600">{rejectForm.errors.reason}</div>}
                                <div className="flex gap-2">
                                    <button type="submit" disabled={rejectForm.processing}
                                        className="bg-rose-600 hover:bg-rose-700 text-white text-xs px-3 py-1 rounded disabled:opacity-60">
                                        Send rejection
                                    </button>
                                    <button type="button" onClick={() => setShowReject(false)}
                                        className="text-xs text-slate-500 px-2 py-1">Cancel</button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            ) : (
                item.customization_status === 'pending' && (
                    <div className="text-xs text-slate-500 italic">Your vendor will upload a proof for your approval shortly.</div>
                )
            )}
        </div>
    );
}
