import { Link, router, useForm } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import Container from '@/Components/Layout/Container';

interface CartItemCustomization {
    id: number;
    field_key: string;
    field_label: string;
    field_type: string;
    value: string | null;
    file_original_name: string | null;
    extra_fee: string | null;
}

interface CartItem {
    id: number;
    product_id: number;
    product_slug: string | null;
    product_name: string | null;
    product_thumb: string | null;
    variant_id: number | null;
    variant_name: string | null;
    variant_attrs: Record<string, string> | null;
    vendor_name: string | null;
    vendor_slug: string | null;
    quantity: number;
    unit_price: string;
    line_total: string;
    // Phase 10 v10.8 — per-line promotion-aware pricing
    unit_price_final: string;
    line_total_final: string;
    line_promotion: string | null;
    promotion: {
        id: number;
        title: string;
        type: string;
        discount_type: string;
        discount_value: string;
        badge: string;
    } | null;
    // Phase 7
    customization_fee: string | null;
    customizations: CartItemCustomization[];
}

interface Props {
    cart: {
        id: number;
        currency: string;
        items_count: number;
        subtotal: string;
        subtotal_minor: number;
        items: CartItem[];
        // Phase 10 v10.8 — promotion total (null when no promotion applies)
        promotion: {
            discount: string;
            discount_minor: number;
        } | null;
        subtotal_after_promotion: string;
        subtotal_after_promotion_minor: number;
        // Phase 9 v9.1 — coupon snapshot + payable amount
        coupon: {
            id: number;
            code: string;
            discount: string;
            discount_minor: number;
        } | null;
        payable: string;
        payable_minor: number;
    };
}

export default function CartShow({ cart }: Props) {
    const empty = cart.items.length === 0;

    const updateQty = (itemId: number, qty: number) => {
        if (qty < 1) return;
        router.patch(`/cart/items/${itemId}`, { quantity: qty }, { preserveScroll: true });
    };

    const removeItem = (itemId: number) => {
        if (!confirm('Remove this item?')) return;
        router.delete(`/cart/items/${itemId}`, { preserveScroll: true });
    };

    const clearCart = () => {
        if (!confirm('Empty the entire cart?')) return;
        router.post('/cart/clear');
    };

    // Group items by vendor for clearer display
    const byVendor = cart.items.reduce<Record<string, CartItem[]>>((acc, item) => {
        const key = item.vendor_name ?? '—';
        (acc[key] ||= []).push(item);
        return acc;
    }, {});

    return (
        <StorefrontLayout title="Your cart">
            <Container className="py-4 sm:py-6 lg:py-8">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl sm:text-3xl font-bold text-slate-900">Your cart</h1>
                    <span className="text-sm text-slate-500">
                        {cart.items_count} {cart.items_count === 1 ? 'item' : 'items'}
                    </span>
                </div>

                {empty ? (
                    <div className="bg-white border border-slate-200 border-dashed rounded-xl p-12 text-center">
                        <p className="text-slate-500 mb-4">Your cart is empty.</p>
                        <Link
                            href="/products"
                            className="inline-block rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700"
                        >
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8">
                        {/* Items */}
                        <div className="space-y-6">
                            {Object.entries(byVendor).map(([vendorName, items]) => (
                                <div key={vendorName} className="bg-white border border-slate-200 rounded-xl overflow-hidden">
                                    <div className="px-4 py-2 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                                        Sold by {vendorName}
                                    </div>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            {items.map((item) => (
                                                <tr key={item.id} className="border-t border-slate-100 first:border-t-0">
                                                    <td className="py-3 px-4">
                                                        <div className="flex items-start gap-3">
                                                            <div className="w-14 h-14 rounded bg-slate-100 flex items-center justify-center text-slate-400 flex-shrink-0 overflow-hidden">
                                                                {item.product_thumb ? (
                                                                    <img src={item.product_thumb} alt="" className="w-full h-full object-cover" />
                                                                ) : '·'}
                                                            </div>
                                                            <div>
                                                                <Link
                                                                    href={`/products/${item.product_slug}`}
                                                                    className="font-medium text-slate-900 hover:text-indigo-600"
                                                                >
                                                                    {item.product_name}
                                                                </Link>
                                                                {item.variant_name && (
                                                                    <div className="text-xs text-slate-500 mt-0.5">{item.variant_name}</div>
                                                                )}
                                                                <div className="text-xs text-slate-500 mt-0.5">
                                                                    {item.unit_price} {cart.currency} each
                                                                </div>
                                                                {/* Phase 7 — customization summary per line */}
                                                                {item.customizations.length > 0 && (
                                                                    <div className="mt-2 p-2 bg-indigo-50/50 border border-indigo-100 rounded text-xs">
                                                                        <div className="font-medium text-indigo-900 mb-1">Customizations:</div>
                                                                        <ul className="space-y-0.5">
                                                                            {item.customizations.map((c) => (
                                                                                <li key={c.id} className="text-slate-700">
                                                                                    <span className="text-slate-500">{c.field_label}:</span>{' '}
                                                                                    {c.file_original_name ? (
                                                                                        <span className="font-mono">{c.file_original_name}</span>
                                                                                    ) : (
                                                                                        <span>{c.value ?? '—'}</span>
                                                                                    )}
                                                                                    {c.extra_fee && <span className="text-slate-500 ml-1">(+{c.extra_fee} {cart.currency})</span>}
                                                                                </li>
                                                                            ))}
                                                                        </ul>
                                                                        {item.customization_fee && (
                                                                            <div className="mt-1 text-slate-600">
                                                                                Customization fee total: +{item.customization_fee} {cart.currency}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-4 whitespace-nowrap">
                                                        <div className="inline-flex items-center border border-slate-300 rounded-md overflow-hidden">
                                                            <button
                                                                onClick={() => updateQty(item.id, item.quantity - 1)}
                                                                className="px-2 py-1 text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                                                                disabled={item.quantity <= 1}
                                                            >
                                                                −
                                                            </button>
                                                            <span className="px-3 py-1 text-sm font-medium text-slate-900 min-w-[40px] text-center">
                                                                {item.quantity}
                                                            </span>
                                                            <button
                                                                onClick={() => updateQty(item.id, item.quantity + 1)}
                                                                className="px-2 py-1 text-slate-600 hover:bg-slate-100"
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-4 text-right whitespace-nowrap font-medium text-slate-900">
                                                        {item.promotion ? (
                                                            <div className="flex flex-col items-end gap-0.5" data-testid={`cart-line-promoted-${item.id}`}>
                                                                <span className="text-rose-700 font-semibold">
                                                                    {item.line_total_final} {cart.currency}
                                                                </span>
                                                                <span className="text-xs text-slate-400 line-through font-normal">
                                                                    {item.line_total}
                                                                </span>
                                                                <span
                                                                    className="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-semibold text-rose-700"
                                                                    title={item.promotion.title}
                                                                >
                                                                    {item.promotion.badge}
                                                                </span>
                                                            </div>
                                                        ) : (
                                                            <>{item.line_total} {cart.currency}</>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-4 text-right">
                                                        <button
                                                            onClick={() => removeItem(item.id)}
                                                            className="text-rose-600 hover:underline text-xs"
                                                        >
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ))}

                            <div className="flex justify-between items-center">
                                <Link href="/products" className="text-sm text-slate-600 hover:text-slate-900">
                                    ← Continue shopping
                                </Link>
                                <button
                                    onClick={clearCart}
                                    className="text-sm text-rose-600 hover:underline"
                                >
                                    Empty cart
                                </button>
                            </div>
                        </div>

                        {/* Summary */}
                        <aside className="bg-white border border-slate-200 rounded-xl p-5 h-fit lg:sticky lg:top-4">
                            <h2 className="text-lg font-semibold text-slate-900 mb-4">Summary</h2>
                            <dl className="space-y-2 text-sm mb-4">
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Subtotal</dt>
                                    <dd className="text-slate-900 font-medium" data-testid="cart-summary-subtotal">
                                        {cart.subtotal} {cart.currency}
                                    </dd>
                                </div>
                                {/* Phase 10 v10.8 — promotion total (separate from coupon) */}
                                {cart.promotion && (
                                    <div className="flex justify-between text-rose-700" data-testid="cart-summary-promotion">
                                        <dt>Promotion discount</dt>
                                        <dd>−{cart.promotion.discount} {cart.currency}</dd>
                                    </div>
                                )}
                                {cart.promotion && (
                                    <div className="flex justify-between text-xs text-slate-500" data-testid="cart-summary-after-promotion">
                                        <dt>Subtotal after promotion</dt>
                                        <dd>{cart.subtotal_after_promotion} {cart.currency}</dd>
                                    </div>
                                )}
                                {/* Phase 9 v9.1 — applied coupon discount line (now applies to post-promotion subtotal per v10.8 stacking) */}
                                {cart.coupon && (
                                    <div className="flex justify-between text-green-700" data-testid="cart-summary-coupon">
                                        <dt>Coupon ({cart.coupon.code})</dt>
                                        <dd>−{cart.coupon.discount} {cart.currency}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between text-xs text-slate-400">
                                    <dt>Shipping + tax</dt>
                                    <dd>calculated at checkout</dd>
                                </div>
                                {/* Final payable (= subtotal − promotion − coupon). Shown whenever ANY discount applies. */}
                                {(cart.coupon || cart.promotion) && (
                                    <div className="flex justify-between border-t border-slate-200 pt-2 mt-2">
                                        <dt className="text-slate-700 font-semibold">Subtotal after discount</dt>
                                        <dd className="text-slate-900 font-semibold" data-testid="cart-summary-payable">{cart.payable} {cart.currency}</dd>
                                    </div>
                                )}
                            </dl>

                            {/* Phase 9 v9.1 — Coupon input / remove */}
                            <CartCouponForm coupon={cart.coupon} />

                            <Link
                                href="/checkout"
                                className="block w-full text-center rounded-md bg-indigo-600 text-white px-5 py-3 font-medium hover:bg-indigo-700 mt-4"
                            >
                                Proceed to checkout
                            </Link>
                        </aside>
                    </div>
                )}
            </Container>
        </StorefrontLayout>
    );
}

/**
 * Phase 9 v9.1 — Coupon application form on the cart page.
 *
 * Tiny sub-component (kept in the same file as the rest of the cart
 * presenter — splitting it out into a new file feels over-engineered
 * for one form). Two modes:
 *
 *   no coupon applied   → input + Apply button
 *   coupon applied      → code shown + Remove button
 *
 * Errors come back as standard Laravel validation errors via Inertia
 * (the CouponValidator maps every rejection reason to a user-facing
 * message in CouponValidator::reasonMessage). The form.errors.code
 * key matches the request key 'code' (v8.4 form-errors-key defense).
 */
interface CartCouponFormProps {
    coupon: {
        id: number;
        code: string;
        discount: string;
        discount_minor: number;
    } | null;
}

function CartCouponForm({ coupon }: CartCouponFormProps) {
    const form = useForm({ code: '' });

    const apply = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        form.post('/cart/coupon', {
            preserveScroll: true,
            onSuccess: () => form.reset('code'),
        });
    };

    const remove = () => {
        form.delete('/cart/coupon', { preserveScroll: true });
    };

    if (coupon) {
        return (
            <div className="border-t border-slate-200 pt-3 mt-3" data-testid="cart-coupon-applied">
                <div className="flex justify-between items-center text-sm">
                    <span className="text-slate-600">
                        Coupon: <span className="font-mono font-semibold text-slate-900">{coupon.code}</span>
                    </span>
                    <button
                        type="button"
                        onClick={remove}
                        className="text-rose-600 hover:underline text-xs"
                        data-testid="cart-coupon-remove"
                    >
                        Remove
                    </button>
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={apply} className="border-t border-slate-200 pt-3 mt-3" data-testid="cart-coupon-form">
            <label htmlFor="coupon-code" className="block text-xs uppercase text-slate-500 mb-2">
                Have a coupon?
            </label>
            <div className="flex gap-2">
                <input
                    id="coupon-code"
                    type="text"
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                    placeholder="Enter code"
                    className="flex-1 border border-slate-300 rounded px-3 py-2 text-sm font-mono uppercase"
                    data-testid="cart-coupon-input"
                />
                <button
                    type="submit"
                    disabled={form.processing || form.data.code.length < 2}
                    className="bg-slate-900 text-white px-4 py-2 rounded text-sm hover:bg-slate-700 disabled:opacity-50"
                    data-testid="cart-coupon-apply"
                >
                    {form.processing ? 'Applying...' : 'Apply'}
                </button>
            </div>
            {form.errors.code && (
                <p className="text-rose-600 text-xs mt-2" data-testid="cart-coupon-error">
                    {form.errors.code}
                </p>
            )}
        </form>
    );
}
