import { Link } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface Props {
    order: {
        id: number;
        number: string;
        status: string;
        payment_status: string;
        currency: string;
        total: string;
        items: { id: number; product_name: string; quantity: number; line_total: string }[];
        payments: { method_slug: string; reference: string | null; status: string }[];
    };
}

export default function OrderConfirm({ order }: Props) {
    const payment = order.payments[0];

    return (
        <StorefrontLayout title="Thank you">
            <div className="max-w-2xl mx-auto">
                <div className="text-center mb-8">
                    <div className="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1 className="text-3xl font-bold text-slate-900 mb-2">Thank you for your order!</h1>
                    <p className="text-slate-600">
                        Your order <span className="font-mono font-medium text-slate-900">{order.number}</span> has been placed.
                    </p>
                </div>

                <div className="bg-white border border-slate-200 rounded-xl p-6 mb-6">
                    <h2 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Order summary</h2>
                    <ul className="space-y-2 mb-4">
                        {order.items.map((item) => (
                            <li key={item.id} className="flex justify-between text-sm">
                                <span className="text-slate-700">
                                    {item.product_name} <span className="text-slate-400">× {item.quantity}</span>
                                </span>
                                <span className="text-slate-900 font-medium">{item.line_total} {order.currency}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="flex justify-between text-lg font-semibold border-t border-slate-200 pt-3">
                        <span className="text-slate-900">Total</span>
                        <span className="text-slate-900">{order.total} {order.currency}</span>
                    </div>
                </div>

                {payment && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6 text-sm">
                        <h3 className="font-semibold text-amber-900 mb-2">Payment — {payment.method_slug.replace('_', ' ')}</h3>
                        {payment.method_slug === 'cod' && (
                            <p className="text-amber-800">
                                You&apos;ll pay <strong>{order.total} {order.currency}</strong> in cash when your order is delivered.
                            </p>
                        )}
                        {payment.method_slug === 'manual_transfer' && payment.reference && (
                            <>
                                <p className="text-amber-800 mb-2">
                                    Please transfer <strong>{order.total} {order.currency}</strong> to the platform bank account and quote this reference in the description:
                                </p>
                                <div className="font-mono text-lg bg-white border border-amber-300 rounded px-3 py-2 inline-block">
                                    {payment.reference}
                                </div>
                            </>
                        )}
                        {payment.method_slug === 'online_mock' && payment.status === 'captured' && (
                            <p className="text-amber-800">
                                Payment captured (demo provider). In production, your card statement would show this charge.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex flex-col sm:flex-row gap-3">
                    <Link
                        href={`/orders/${order.id}`}
                        className="flex-1 text-center rounded-md bg-indigo-600 text-white px-5 py-3 font-medium hover:bg-indigo-700"
                    >
                        View order details
                    </Link>
                    <Link
                        href="/products"
                        className="flex-1 text-center rounded-md border border-slate-300 text-slate-700 px-5 py-3 font-medium hover:bg-slate-50"
                    >
                        Keep shopping
                    </Link>
                </div>
            </div>
        </StorefrontLayout>
    );
}
