import { router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

interface Filter {
    preset: string;
    from: string;
    to: string;
}

interface Financial {
    order_count: number;
    subtotal: string;
    shipping: string;
    tax: string;
    coupon_discount: string;
    coupon_discount_minor: number;
    promotion_discount: string;
    gross_total: string;
    commission: string;
    vendor_earnings: string;
    allocation: string;
    aov: string;
    reconciliation_delta_minor: number;
    allocation_delta_minor: number;
}

interface Statuses {
    pending_payment: number;
    paid: number;
    confirmed: number;
    shipped: number;
    completed: number;
    cancelled: number;
    refunded: number;
    total: number;
}

interface PayoutBucket { count: number; amount: string; amount_minor: number; }
interface Payouts {
    pending: PayoutBucket;
    approved: PayoutBucket;
    paid: PayoutBucket;
    rejected: PayoutBucket;
}

interface Counts {
    customers_total: number; vendors_approved: number; vendors_pending: number; vendors_rejected: number;
    products_total: number; products_published: number;
    services_total: number; services_published: number; bookings_total: number;
    support_tickets_open: number; support_tickets_total: number;
    reviews_approved: number; reviews_pending: number; reviews_avg_rating: number;
}

interface VendorRow { id: number; business_name: string; order_count: number; gross: string; allocation: string; commission: string; earnings: string; earnings_minor: number; }
interface ProductRow { product_id: number; product_name: string; units_sold: number; gross: string; gross_minor: number; }
interface SeriesPoint { date: string; total: string; total_minor: number; orders: number; }

interface Props {
    filter: Filter;
    financial: Financial;
    statuses: Statuses;
    payouts: Payouts;
    counts: Counts;
    vendors: VendorRow[];
    products: ProductRow[];
    series: SeriesPoint[];
    currency: string;
}

export default function AdminReports({ filter, financial, statuses, payouts, counts, vendors, products, series, currency }: Props) {
    const [preset, setPreset] = useState(filter.preset);
    const [from, setFrom] = useState(filter.from);
    const [to, setTo] = useState(filter.to);

    const applyFilter = () => {
        router.get('/admin/reports', { preset, from, to }, { preserveState: false });
    };

    const exportUrl = `/admin/reports/export.csv?preset=${encodeURIComponent(preset)}&from=${from}&to=${to}`;

    const reconcileOk = financial.reconciliation_delta_minor === 0 && financial.allocation_delta_minor === 0;

    return (
        <AdminLayout title="Reports">
            <div className="mb-6 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Marketplace Reports</h1>
                    <p className="text-sm text-slate-500">Financial overview for {filter.from} → {filter.to}</p>
                </div>
                <a
                    href={exportUrl}
                    className="rounded-md bg-emerald-600 text-white px-4 py-2 text-sm hover:bg-emerald-700"
                    data-testid="admin-reports-export-csv"
                >
                    Download CSV
                </a>
            </div>

            {/* Date filter */}
            <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6 flex items-end gap-3 flex-wrap" data-testid="admin-reports-filter">
                <div>
                    <label className="block text-xs uppercase text-slate-500 mb-1">Preset</label>
                    <select value={preset} onChange={(e) => setPreset(e.target.value)} className="border border-slate-300 rounded px-3 py-2 text-sm">
                        <option value="today">Today</option>
                        <option value="last_7_days">Last 7 days</option>
                        <option value="last_30_days">Last 30 days</option>
                        <option value="this_month">This month</option>
                        <option value="previous_month">Previous month</option>
                        <option value="custom">Custom range</option>
                    </select>
                </div>
                {preset === 'custom' && (
                    <>
                        <div>
                            <label className="block text-xs uppercase text-slate-500 mb-1">From</label>
                            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="border border-slate-300 rounded px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="block text-xs uppercase text-slate-500 mb-1">To</label>
                            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="border border-slate-300 rounded px-3 py-2 text-sm" />
                        </div>
                    </>
                )}
                <button onClick={applyFilter} className="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm hover:bg-indigo-700">Apply</button>
            </div>

            {/* Reconciliation banner */}
            {!reconcileOk && (
                <div className="bg-rose-50 border border-rose-200 rounded-lg p-4 mb-6" data-testid="admin-reports-reconcile-warn">
                    <p className="font-medium text-rose-800">Financial reconciliation drift detected</p>
                    <p className="text-sm text-rose-700 mt-1">
                        Allocation delta: {financial.allocation_delta_minor} minor units · Earnings delta: {financial.reconciliation_delta_minor} minor units.
                        Investigate via the v9.3 reconciliation invariant. Tooling: <code className="bg-rose-100 rounded px-1">{"php artisan test --filter='Phase9V93' --filter='reconciliation'"}</code>.
                    </p>
                </div>
            )}

            {/* Financial KPIs */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <KPI label="Gross sales" value={financial.gross_total} unit={currency} testid="kpi-gross" />
                <KPI label="Subtotal (pre-discount)" value={financial.subtotal} unit={currency} />
                <KPI label="Coupon discounts" value={financial.coupon_discount} unit={currency} negative />
                <KPI label="Promotion discounts" value={financial.promotion_discount} unit={currency} negative />
                <KPI label="Platform commission" value={financial.commission} unit={currency} testid="kpi-commission" />
                <KPI label="Vendor earnings" value={financial.vendor_earnings} unit={currency} testid="kpi-earnings" />
                <KPI label="Shipping" value={financial.shipping} unit={currency} />
                <KPI label="Average order value" value={financial.aov} unit={currency} />
            </div>

            {/* Daily revenue chart */}
            {series.length > 0 && <DailyRevenueChart series={series} currency={currency} />}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                {/* Order statuses */}
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Orders</h3>
                    <dl className="space-y-1.5 text-sm">
                        <StatusRow label="Pending payment" value={statuses.pending_payment} />
                        <StatusRow label="Paid" value={statuses.paid} />
                        <StatusRow label="Confirmed" value={statuses.confirmed} />
                        <StatusRow label="Shipped" value={statuses.shipped} />
                        <StatusRow label="Completed" value={statuses.completed} />
                        <StatusRow label="Cancelled" value={statuses.cancelled} />
                        <StatusRow label="Refunded" value={statuses.refunded} />
                        <div className="border-t border-slate-200 pt-2 mt-2 flex justify-between font-semibold">
                            <dt>Total</dt><dd>{statuses.total}</dd>
                        </div>
                    </dl>
                </div>

                {/* Payouts */}
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Payouts</h3>
                    <dl className="space-y-1.5 text-sm">
                        <StatusRow label="Pending" value={`${payouts.pending.count} (${payouts.pending.amount} ${currency})`} />
                        <StatusRow label="Approved" value={`${payouts.approved.count} (${payouts.approved.amount} ${currency})`} />
                        <StatusRow label="Paid" value={`${payouts.paid.count} (${payouts.paid.amount} ${currency})`} />
                        <StatusRow label="Rejected" value={`${payouts.rejected.count} (${payouts.rejected.amount} ${currency})`} />
                    </dl>
                </div>

                {/* Marketplace counts */}
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Marketplace</h3>
                    <dl className="space-y-1.5 text-sm">
                        <StatusRow label="Customers" value={counts.customers_total} />
                        <StatusRow label="Vendors (approved)" value={counts.vendors_approved} />
                        <StatusRow label="Vendors (pending)" value={counts.vendors_pending} />
                        <StatusRow label="Products (published)" value={`${counts.products_published} / ${counts.products_total}`} />
                        <StatusRow label="Services (published)" value={`${counts.services_published} / ${counts.services_total}`} />
                        <StatusRow label="Open tickets" value={`${counts.support_tickets_open} / ${counts.support_tickets_total}`} />
                        <StatusRow label="Approved reviews" value={`${counts.reviews_approved} (avg ${counts.reviews_avg_rating})`} />
                    </dl>
                </div>
            </div>

            {/* Top vendors */}
            <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6">
                <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Top vendors by gross</h3>
                {vendors.length === 0 ? (
                    <p className="text-sm text-slate-500">No vendor sales in this window.</p>
                ) : (
                    <table className="w-full text-sm" data-testid="admin-reports-top-vendors">
                        <thead>
                            <tr className="text-left text-slate-500 border-b border-slate-200">
                                <th className="py-2">Vendor</th>
                                <th className="py-2 text-right">Orders</th>
                                <th className="py-2 text-right">Gross</th>
                                <th className="py-2 text-right">Coupon Alloc.</th>
                                <th className="py-2 text-right">Commission</th>
                                <th className="py-2 text-right">Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vendors.map((v) => (
                                <tr key={v.id} className="border-b border-slate-100">
                                    <td className="py-2 text-slate-900 font-medium">{v.business_name}</td>
                                    <td className="py-2 text-right">{v.order_count}</td>
                                    <td className="py-2 text-right">{v.gross}</td>
                                    <td className="py-2 text-right text-amber-700">−{v.allocation}</td>
                                    <td className="py-2 text-right text-rose-700">−{v.commission}</td>
                                    <td className="py-2 text-right text-emerald-700 font-semibold">{v.earnings}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {/* Top products */}
            <div className="bg-white border border-slate-200 rounded-lg p-4">
                <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">Top products by units sold</h3>
                {products.length === 0 ? (
                    <p className="text-sm text-slate-500">No product sales in this window.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-slate-500 border-b border-slate-200">
                                <th className="py-2">Product</th>
                                <th className="py-2 text-right">Units</th>
                                <th className="py-2 text-right">Gross</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((p) => (
                                <tr key={p.product_id} className="border-b border-slate-100">
                                    <td className="py-2 text-slate-900">{p.product_name}</td>
                                    <td className="py-2 text-right">{p.units_sold}</td>
                                    <td className="py-2 text-right">{p.gross}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    );
}

function KPI({ label, value, unit, negative, testid }: { label: string; value: string; unit: string; negative?: boolean; testid?: string }) {
    return (
        <div className="bg-white border border-slate-200 rounded-lg p-4" data-testid={testid}>
            <p className="text-xs uppercase text-slate-500 mb-1">{label}</p>
            <p className={`text-2xl font-bold ${negative ? 'text-rose-700' : 'text-slate-900'}`}>
                {negative && parseFloat(value) > 0 ? '−' : ''}{value} <span className="text-sm font-medium text-slate-500">{unit}</span>
            </p>
        </div>
    );
}

function StatusRow({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="flex justify-between">
            <dt className="text-slate-600">{label}</dt>
            <dd className="text-slate-900 font-medium">{value}</dd>
        </div>
    );
}

function DailyRevenueChart({ series, currency }: { series: SeriesPoint[]; currency: string }) {
    const maxTotal = Math.max(...series.map((s) => s.total_minor), 1);
    const width = 800;
    const height = 120;
    const stepX = series.length > 1 ? width / (series.length - 1) : width;

    const points = series.map((s, i) => {
        const x = i * stepX;
        const y = height - (s.total_minor / maxTotal) * (height - 20);
        return `${x},${y}`;
    }).join(' ');

    const total = series.reduce((acc, s) => acc + s.total_minor, 0);

    return (
        <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6">
            <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
                Daily revenue · {(total / 100).toFixed(2)} {currency} total
            </h3>
            <svg viewBox={`0 0 ${width} ${height + 20}`} className="w-full h-32" preserveAspectRatio="none">
                <polyline points={points} fill="none" stroke="#4f46e5" strokeWidth="2" />
                {series.map((s, i) => (
                    <circle key={s.date} cx={i * stepX} cy={height - (s.total_minor / maxTotal) * (height - 20)} r="2" fill="#4f46e5" />
                ))}
            </svg>
            <div className="flex justify-between text-xs text-slate-400 mt-1">
                <span>{series[0]?.date}</span>
                <span>{series[series.length - 1]?.date}</span>
            </div>
        </div>
    );
}
