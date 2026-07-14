import { useForm, Link, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import Container from '@/Components/Layout/Container';
import type { SharedProps } from '@/types/inertia';

// v5.2 — address fields match the Phase 1 `addresses` schema exactly.
// The pre-v5.2 form used full_name / line1 / line2 / region which did not
// exist in the actual table, causing a SQL error on /checkout render.

interface Address {
    id: number;
    label: string | null;
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
    is_default: boolean;
    single_line: string;
}

interface PaymentMethod {
    slug: string;
    name: string;
    description: string | null;
}

interface CartItem {
    id: number;
    product_name: string | null;
    variant_name: string | null;
    vendor_name: string | null;
    quantity: number;
    unit_price: string;          // ORIGINAL pre-promotion unit price
    line_total: string;          // ORIGINAL pre-promotion line total
    // Phase 11B.2 v11B.2.2 §B — promotion-aware fields, identical contract to
    // Cart/Show.tsx. unit_price_final equals unit_price when no promotion
    // applies; otherwise it's the discounted unit. line_promotion is the
    // label (e.g. "−20% Summer Flash Sale") rendered next to the price.
    unit_price_final: string;
    line_total_final: string;
    line_promotion: string | null;
    promotion: { id: number; type: string; title: string } | null;
    thumb: string | null;
}

interface Props {
    cart: {
        currency: string;
        subtotal: string;                    // PRE-promotion subtotal
        subtotal_minor: number;
        items_count: number;
        items: CartItem[];
        // Phase 11B.2 v11B.2.2 §B — promotion total (null when no promotion applies)
        promotion: { discount: string; discount_minor: number } | null;
        subtotal_after_promotion: string;
        subtotal_after_promotion_minor: number;
        // Phase 9 v9.3 — coupon block from CheckoutController (server-revalidated)
        coupon: {
            code: string;
            discount: string;
            discount_minor: number;
        } | null;
        // Server-authoritative payable: subtotal − promotion − coupon (NO shipping).
        // The React component adds shipping client-side, but NEVER recomputes
        // the promotion/coupon math. Per dev §5: "Recalculate server-side at
        // checkout. Do not trust totals submitted by React or stored only in
        // browser state."
        payable: string;
        payable_minor: number;
    };
    addresses: Address[];
    payment_methods: PaymentMethod[];
    default_shipping_minor: number;
    has_addresses: boolean;
    user_name: string;
}

interface InlineAddress {
    recipient_name: string;
    phone: string;
    country: string;
    state: string;
    city: string;
    area: string;
    block: string;
    street: string;
    building: string;
    floor: string;
    apartment: string;
    postal_code: string;
}

interface CheckoutForm {
    shipping_address_id: string;
    shipping_address: InlineAddress;
    payment_method_slug: string;
    customer_notes: string;
    shipping_minor: number;
    [key: string]: string | number | object;
}

export default function CheckoutShow({ cart, addresses, payment_methods, default_shipping_minor, has_addresses, user_name }: Props) {
    const [useNew, setUseNew] = useState(!has_addresses);

    // Flash messages (e.g. domain errors from CheckoutController::place catch
    // blocks like "Product out of stock") arrive as flash.error. Pre-v5.4 the
    // checkout page never displayed these, so a failed Place Order looked like
    // nothing happened. v5.6: use the SharedProps type so Inertia v2's
    // `usePage<T extends PageProps>` constraint is satisfied (an inline object
    // type without an index signature does not satisfy PageProps).
    const page = usePage<SharedProps>();
    const flashError = page.props.flash?.error ?? null;

    const { data, setData, post, processing, errors, transform } = useForm<CheckoutForm>({
        shipping_address_id: addresses[0]?.id?.toString() ?? '',
        shipping_address: {
            recipient_name: user_name,
            phone: '', country: 'KW', state: '', city: '',
            area: '', block: '', street: '', building: '',
            floor: '', apartment: '', postal_code: '',
        },
        payment_method_slug: payment_methods[0]?.slug ?? '',
        customer_notes: '',
        shipping_minor: default_shipping_minor,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        // v5.4 — use the useForm helper (not bare router.post) so:
        //   1. `processing` flips true → button shows "Placing order…" + disables
        //   2. validation errors populate THIS form's `errors` object (the one
        //      the template reads), instead of only the page's shared errors bag
        // transform() shapes the payload: saved-address-id XOR inline address.
        transform((d) => {
            const base = {
                payment_method_slug: d.payment_method_slug,
                customer_notes: d.customer_notes,
                shipping_minor: d.shipping_minor,
            };
            return useNew
                ? { ...base, shipping_address: d.shipping_address }
                : { ...base, shipping_address_id: d.shipping_address_id ? parseInt(d.shipping_address_id, 10) : null };
        });

        post('/checkout', {
            preserveScroll: true,
            // On a successful order the server redirects to /orders/{id}/confirm
            // and Inertia follows it automatically — no onSuccess needed.
            // On validation/domain failure the errors + flash.error render below.
        });
    };

    const updateInline = (field: keyof InlineAddress, value: string) => {
        setData('shipping_address', { ...data.shipping_address, [field]: value });
    };

    const shippingMinor = data.shipping_minor;
    // Phase 11B.2 v11B.2.2 §B — LAUNCH-BLOCKING FINANCIAL FIX.
    //
    // BEFORE v11B.2.2 (the defect):
    //   const couponMinor = cart.coupon?.discount_minor ?? 0;
    //   const totalMinor = cart.subtotal_minor + shippingMinor − couponMinor;
    //
    // The pre-fix formula used the PRE-PROMOTION subtotal (cart.subtotal_minor)
    // and subtracted only the coupon. The active promotion discount (e.g. the
    // 20% Summer Flash Sale) was NEVER subtracted client-side, so the customer
    // saw the un-discounted total at checkout. The server-side order creation
    // (CheckoutService::place via PricingService) was already correct — the
    // actual order was written with the right total — but the customer was
    // shown an inflated amount before clicking Place Order, and the button
    // text quoted that inflated amount.
    //
    // AFTER v11B.2.2: use cart.payable_minor — the server-authoritative
    // payable (subtotal − promotion − coupon) — and add only shipping, which
    // is selected on the checkout page itself. No client-side promotion math.
    // No client-side coupon math. The server is the only source of truth.
    const totalMinor = Math.max(0, cart.payable_minor + shippingMinor);
    const totalFmt = (totalMinor / 100).toFixed(2);

    const inputCls = 'w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border';

    return (
        <StorefrontLayout title="Checkout">
            <Container className="py-4 sm:py-6 lg:py-8">
                <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 lg:gap-8">
                <div className="space-y-6">
                    {/* v5.4 — surface domain errors (flash.error) + any validation
                        errors so a failed Place Order is never silent. */}
                    {(flashError || Object.keys(errors).length > 0) && (
                        <div className="bg-rose-50 border border-rose-200 rounded-xl p-4">
                            <p className="text-sm font-semibold text-rose-800">
                                {flashError ?? 'Please fix the following before placing your order:'}
                            </p>
                            {Object.keys(errors).length > 0 && (
                                <ul className="mt-2 list-disc list-inside text-sm text-rose-700">
                                    {Object.entries(errors).map(([key, message]) => (
                                        <li key={key}>{message as string}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    <Section title="1. Shipping address">
                        {has_addresses && (
                            <div className="mb-3">
                                <label className="flex items-center gap-2 mb-2">
                                    <input type="radio" checked={!useNew} onChange={() => setUseNew(false)} />
                                    <span className="text-sm font-medium text-slate-700">Use a saved address</span>
                                </label>
                                {!useNew && (
                                    <div className="space-y-2 ml-6">
                                        {addresses.map((addr) => (
                                            <label
                                                key={addr.id}
                                                className={`block border rounded-lg p-3 cursor-pointer ${
                                                    data.shipping_address_id === addr.id.toString()
                                                        ? 'border-indigo-500 bg-indigo-50'
                                                        : 'border-slate-200 hover:border-slate-300'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="shipping_address_id"
                                                    value={addr.id}
                                                    checked={data.shipping_address_id === addr.id.toString()}
                                                    onChange={(e) => setData('shipping_address_id', e.target.value)}
                                                    className="mr-2"
                                                />
                                                <span className="font-medium text-sm">
                                                    {addr.label ?? 'Address'}{addr.is_default && ' · default'}
                                                </span>
                                                <div className="text-xs text-slate-500 mt-1 ml-5">
                                                    {addr.single_line}
                                                    {addr.phone && <span className="block">📞 {addr.phone}</span>}
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                )}
                                <label className="flex items-center gap-2 mt-3">
                                    <input type="radio" checked={useNew} onChange={() => setUseNew(true)} />
                                    <span className="text-sm font-medium text-slate-700">Use a different address</span>
                                </label>
                            </div>
                        )}

                        {!has_addresses && (
                            <p className="text-sm text-slate-600 mb-3">
                                You don&apos;t have any saved addresses yet. Fill in your shipping address below — we&apos;ll deliver this order to it.
                            </p>
                        )}

                        {useNew && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <input placeholder="Recipient name" value={data.shipping_address.recipient_name}
                                    onChange={(e) => updateInline('recipient_name', e.target.value)}
                                    className={inputCls + ' md:col-span-2'} />
                                <input placeholder="Phone" value={data.shipping_address.phone}
                                    onChange={(e) => updateInline('phone', e.target.value)} className={inputCls} />
                                <input placeholder="Country (ISO-2, e.g. KW)" maxLength={2}
                                    value={data.shipping_address.country}
                                    onChange={(e) => updateInline('country', e.target.value.toUpperCase())}
                                    className={inputCls} required />
                                <input placeholder="Governorate / state" value={data.shipping_address.state}
                                    onChange={(e) => updateInline('state', e.target.value)} className={inputCls} />
                                <input placeholder="City *" value={data.shipping_address.city}
                                    onChange={(e) => updateInline('city', e.target.value)} className={inputCls} required />
                                <input placeholder="Area" value={data.shipping_address.area}
                                    onChange={(e) => updateInline('area', e.target.value)} className={inputCls} />
                                <input placeholder="Block" value={data.shipping_address.block}
                                    onChange={(e) => updateInline('block', e.target.value)} className={inputCls} />
                                <input placeholder="Street" value={data.shipping_address.street}
                                    onChange={(e) => updateInline('street', e.target.value)} className={inputCls} />
                                <input placeholder="Building" value={data.shipping_address.building}
                                    onChange={(e) => updateInline('building', e.target.value)} className={inputCls} />
                                <input placeholder="Floor" value={data.shipping_address.floor}
                                    onChange={(e) => updateInline('floor', e.target.value)} className={inputCls} />
                                <input placeholder="Apartment" value={data.shipping_address.apartment}
                                    onChange={(e) => updateInline('apartment', e.target.value)} className={inputCls} />
                                <input placeholder="Postal code" value={data.shipping_address.postal_code}
                                    onChange={(e) => updateInline('postal_code', e.target.value)} className={inputCls} />
                            </div>
                        )}
                        {errors.shipping_address_id && <p className="text-sm text-rose-600 mt-1">{errors.shipping_address_id}</p>}
                    </Section>

                    <Section title="2. Payment method">
                        <div className="space-y-2">
                            {payment_methods.length === 0 ? (
                                <p className="text-sm text-rose-600">No payment methods are currently available for this order.</p>
                            ) : (
                                payment_methods.map((m) => (
                                    <label key={m.slug}
                                        className={`block border rounded-lg p-3 cursor-pointer ${
                                            data.payment_method_slug === m.slug
                                                ? 'border-indigo-500 bg-indigo-50'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}>
                                        <input type="radio" name="payment_method_slug" value={m.slug}
                                            checked={data.payment_method_slug === m.slug}
                                            onChange={(e) => setData('payment_method_slug', e.target.value)} className="mr-2" />
                                        <span className="font-medium text-sm">{m.name}</span>
                                        {m.description && <div className="text-xs text-slate-500 mt-1 ml-5">{m.description}</div>}
                                    </label>
                                ))
                            )}
                        </div>
                        {errors.payment_method_slug && <p className="text-sm text-rose-600 mt-1">{errors.payment_method_slug}</p>}
                    </Section>

                    <Section title="3. Order notes (optional)">
                        <textarea value={data.customer_notes} onChange={(e) => setData('customer_notes', e.target.value)}
                            rows={3} maxLength={1000}
                            placeholder="Anything we should know before fulfilling your order?" className={inputCls} />
                    </Section>
                </div>

                <aside className="bg-white border border-slate-200 rounded-xl p-5 h-fit lg:sticky lg:top-4">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4">Review</h2>
                    <ul className="space-y-2 mb-4 max-h-60 overflow-y-auto">
                        {cart.items.map((item) => (
                            <li key={item.id} className="flex justify-between text-sm" data-testid="checkout-line-item">
                                <span className="text-slate-700">
                                    {item.product_name}
                                    {item.variant_name && <span className="text-slate-400"> · {item.variant_name}</span>}
                                    <span className="text-slate-400"> × {item.quantity}</span>
                                    {/* v11B.2.2 §B — show promotion label per line when active */}
                                    {item.line_promotion && (
                                        <span className="ml-2 text-xs text-green-700" data-testid="checkout-line-promo-label">
                                            {item.line_promotion}
                                        </span>
                                    )}
                                </span>
                                <span className="text-slate-900 font-medium whitespace-nowrap ml-2 text-right">
                                    {/* v11B.2.2 §B — render the post-promotion line total. When no
                                        promotion applies, line_total_final equals line_total, so the
                                        display is identical to before in the non-promotion case. */}
                                    <span data-testid="checkout-line-total-final">
                                        {item.line_total_final} {cart.currency}
                                    </span>
                                    {item.line_promotion && (
                                        <span className="block text-xs line-through text-slate-400" data-testid="checkout-line-total-original">
                                            {item.line_total}
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <dl className="border-t border-slate-200 pt-4 space-y-2 text-sm mb-4">
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Subtotal</dt>
                            <dd className="text-slate-900">{cart.subtotal} {cart.currency}</dd>
                        </div>
                        {/* Phase 11B.2 v11B.2.2 §B — promotion savings line. Renders BEFORE
                            coupon per dev §8 ("coupons apply to subtotal AFTER automatic
                            promotion"). Hidden when no promotion applies. */}
                        {cart.promotion && (
                            <div className="flex justify-between text-green-700" data-testid="checkout-promotion-line">
                                <dt>Promotion savings</dt>
                                <dd>−{cart.promotion.discount} {cart.currency}</dd>
                            </div>
                        )}
                        {/* Phase 9 v9.3 — coupon discount line in the checkout summary
                            (server-revalidated in CheckoutController::show, so stale
                            coupons are dropped before the user sees this number). */}
                        {cart.coupon && (
                            <div className="flex justify-between text-green-700" data-testid="checkout-coupon-line">
                                <dt>Coupon ({cart.coupon.code})</dt>
                                <dd>−{cart.coupon.discount} {cart.currency}</dd>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <dt className="text-slate-500">Shipping</dt>
                            <dd className="text-slate-900">{(shippingMinor / 100).toFixed(2)} {cart.currency}</dd>
                        </div>
                        <div className="flex justify-between text-base font-semibold pt-2 border-t border-slate-100">
                            <dt className="text-slate-900">Total</dt>
                            <dd className="text-slate-900">{totalFmt} {cart.currency}</dd>
                        </div>
                    </dl>
                    <button type="submit" disabled={processing || payment_methods.length === 0}
                        className="w-full rounded-md bg-indigo-600 text-white px-5 py-3 font-medium hover:bg-indigo-700 disabled:opacity-60">
                        {processing ? 'Placing order…' : `Place order — ${totalFmt} ${cart.currency}`}
                    </button>
                    <Link href="/cart" className="block text-center mt-3 text-sm text-slate-500 hover:text-slate-700">← Back to cart</Link>
                </aside>
            </form>
            </Container>
        </StorefrontLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-5">
            <h2 className="text-lg font-semibold text-slate-900 mb-4">{title}</h2>
            {children}
        </div>
    );
}
