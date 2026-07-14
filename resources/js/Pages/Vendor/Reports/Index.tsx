import { router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import VendorReportsIntelligenceEmbed from '@/Components/VendorIntelligence/VendorReportsIntelligenceEmbed';

interface Filter { preset: string; from: string; to: string; }

interface Financial {
    order_count: number; units_sold: number;
    gross: string; gross_minor: number;
    allocation: string; net: string; net_minor: number;
    commission: string; commission_minor: number;
    earnings: string; earnings_minor: number;
    payout_pending: string; payout_approved: string; payout_paid: string;
    reviews_count: number; reviews_avg_rating: number;
}

interface ProductRow {
    product_id: number; product_name: string;
    units_sold: number; order_count: number;
    gross: string; allocation: string; commission: string;
    earnings: string; earnings_minor: number;
}

interface SeriesPoint { date: string; total: string; total_minor: number; orders: number; }

interface Props {
    filter: Filter;
    financial: Financial;
    products: ProductRow[];
    series: SeriesPoint[];
    vendor: { id: number; business_name: string };
    currency: string;
}

export default function VendorReports({ filter, financial, products, series, vendor, currency }: Props) {
    const [preset, setPreset] = useState(filter.preset);
    const [from, setFrom] = useState(filter.from);
    const [to, setTo] = useState(filter.to);

    const applyFilter = () => {
        router.get('/vendor/reports', { preset, from, to }, { preserveState: false });
    };

    const exportUrl = `/vendor/reports/export.csv?preset=${encodeURIComponent(preset)}&from=${from}&to=${to}`;

    return (
        <VendorLayout title="Reports">
            {/* Phase 11B.4 v11B.4.2 Defect 8 fix — intelligence insights embed.
                Reads pre-generated summary; no heavy recalc on the reports page. */}
            <VendorReportsIntelligenceEmbed />

            <div className="mb-6 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">My Reports</h1>
                    <p className="text-sm text-slate-500">{vendor.business_name} · {filter.from} → {filter.to}</p>
                </div>
                <a href={exportUrl} className="rounded-md bg-emerald-600 text-white px-4 py-2 text-sm hover:bg-emerald-700">
                    Download CSV
                </a>
            </div>

            <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6 flex items-end gap-3 flex-wrap">
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

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <KPI label="Gross sales" value={financial.gross} unit={currency} />
                <KPI label="Coupon allocation" value={financial.allocation} unit={currency} negative />
                <KPI label="Net (what customer paid)" value={financial.net} unit={currency} />
                <KPI label="Platform commission" value={financial.commission} unit={currency} negative />
                <KPI label="My earnings" value={financial.earnings} unit={currency} highlight />
                <KPI label="Orders" value={String(financial.order_count)} unit="" />
                <KPI label="Units sold" value={String(financial.units_sold)} unit="" />
                <KPI label="Avg rating" value={`${financial.reviews_avg_rating} (${financial.reviews_count})`} unit="" />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Pending payout</h3>
                    <p className="text-2xl font-bold text-amber-700">{financial.payout_pending} <span className="text-sm font-medium text-slate-500">{currency}</span></p>
                </div>
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Approved payout</h3>
                    <p className="text-2xl font-bold text-indigo-700">{financial.payout_approved} <span className="text-sm font-medium text-slate-500">{currency}</span></p>
                </div>
                <div className="bg-white border border-slate-200 rounded-lg p-4">
                    <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-2">Paid payout</h3>
                    <p className="text-2xl font-bold text-emerald-700">{financial.payout_paid} <span className="text-sm font-medium text-slate-500">{currency}</span></p>
                </div>
            </div>

            {series.length > 0 && <DailyRevenueChart series={series} currency={currency} />}

            <div className="bg-white border border-slate-200 rounded-lg p-4">
                <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">My products</h3>
                {products.length === 0 ? (
                    <p className="text-sm text-slate-500">No product sales in this window.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-slate-500 border-b border-slate-200">
                                <th className="py-2">Product</th>
                                <th className="py-2 text-right">Orders</th>
                                <th className="py-2 text-right">Units</th>
                                <th className="py-2 text-right">Gross</th>
                                <th className="py-2 text-right">Coupon Alloc.</th>
                                <th className="py-2 text-right">Commission</th>
                                <th className="py-2 text-right">Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((p) => (
                                <tr key={p.product_id} className="border-b border-slate-100">
                                    <td className="py-2 text-slate-900 font-medium">{p.product_name}</td>
                                    <td className="py-2 text-right">{p.order_count}</td>
                                    <td className="py-2 text-right">{p.units_sold}</td>
                                    <td className="py-2 text-right">{p.gross}</td>
                                    <td className="py-2 text-right text-amber-700">−{p.allocation}</td>
                                    <td className="py-2 text-right text-rose-700">−{p.commission}</td>
                                    <td className="py-2 text-right text-emerald-700 font-semibold">{p.earnings}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </VendorLayout>
    );
}

function KPI({ label, value, unit, negative, highlight }: { label: string; value: string; unit: string; negative?: boolean; highlight?: boolean }) {
    return (
        <div className={`border border-slate-200 rounded-lg p-4 ${highlight ? 'bg-emerald-50' : 'bg-white'}`}>
            <p className="text-xs uppercase text-slate-500 mb-1">{label}</p>
            <p className={`text-2xl font-bold ${negative ? 'text-rose-700' : highlight ? 'text-emerald-800' : 'text-slate-900'}`}>
                {negative && parseFloat(value) > 0 ? '−' : ''}{value} {unit && <span className="text-sm font-medium text-slate-500">{unit}</span>}
            </p>
        </div>
    );
}

function DailyRevenueChart({ series, currency }: { series: SeriesPoint[]; currency: string }) {
    const maxTotal = Math.max(...series.map((s) => s.total_minor), 1);
    const width = 800; const height = 120;
    const stepX = series.length > 1 ? width / (series.length - 1) : width;
    const points = series.map((s, i) => `${i * stepX},${height - (s.total_minor / maxTotal) * (height - 20)}`).join(' ');
    const total = series.reduce((acc, s) => acc + s.total_minor, 0);

    return (
        <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6">
            <h3 className="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-3">
                Daily revenue · {(total / 100).toFixed(2)} {currency} total
            </h3>
            <svg viewBox={`0 0 ${width} ${height + 20}`} className="w-full h-32" preserveAspectRatio="none">
                <polyline points={points} fill="none" stroke="#10b981" strokeWidth="2" />
                {series.map((s, i) => (
                    <circle key={s.date} cx={i * stepX} cy={height - (s.total_minor / maxTotal) * (height - 20)} r="2" fill="#10b981" />
                ))}
            </svg>
            <div className="flex justify-between text-xs text-slate-400 mt-1">
                <span>{series[0]?.date}</span>
                <span>{series[series.length - 1]?.date}</span>
            </div>
        </div>
    );
}
