import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Users, Sparkles, TrendingUp } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageContainer, PageHeader } from '@/Components/Layout/PageContainer';
import { ResponsiveDataList, type ColumnDef } from '@/Components/Layout/ResponsiveDataList';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

/**
 * Phase 11B.4 §19 §41 — admin vendor intelligence overview.
 *
 * Super-admin-only aggregate view. Shows one row per vendor with:
 *   - business_name (from vendors join)
 *   - active_alerts / low_stock / missing_arabic / store_completion
 * Never shows customer-level personalization or private ticket text.
 * Never shows individual product IDs — just counts.
 */

interface VendorSummaryRow {
    id: number;
    vendor_id: number;
    business_name: string;
    business_email: string;
    total_active_products: number;
    active_alerts_count: number;
    out_of_stock_count: number;
    low_stock_count: number;
    slow_moving_count: number;
    missing_arabic_count: number;
    missing_images_count: number;
    store_completion_score: number;
    avg_product_quality: number;
}

interface Props extends SharedProps {
    summaries: {
        data: VendorSummaryRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filter: string | null;
    rollup: {
        total_vendors: number;
        total_alerts: number;
        avg_completion: number;
        avg_quality: number;
    };
}

const FILTERS: Array<{ key: string | null; label: string }> = [
    { key: null,               label: 'All vendors' },
    { key: 'low_stock',        label: 'Vendors with low stock' },
    { key: 'incomplete_stores', label: 'Incomplete stores' },
    { key: 'missing_arabic',   label: 'Missing Arabic' },
    { key: 'many_pending',     label: 'Many pending actions' },
];

export default function VendorIntelligenceOverview() {
    const { summaries, filter, rollup } = usePage<Props>().props;
    const t = useT();

    const columns: ColumnDef<VendorSummaryRow>[] = [
        {
            key: 'vendor',
            label: 'Vendor',
            render: (r) => (
                <div>
                    <div className="font-medium text-slate-900 whitespace-normal">{r.business_name}</div>
                    <div className="text-xs text-slate-500">{r.business_email}</div>
                </div>
            ),
        },
        {
            key: 'products',
            label: 'Products',
            className: 'text-end',
            render: (r) => <span className="text-slate-800">{r.total_active_products}</span>,
        },
        {
            key: 'alerts',
            label: 'Active alerts',
            className: 'text-end',
            render: (r) => (
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                    r.active_alerts_count > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'
                }`}>
                    {r.active_alerts_count}
                </span>
            ),
        },
        {
            key: 'low_stock',
            label: 'Low stock',
            className: 'text-end',
            hideOnMd: true,
            render: (r) => <span className="text-slate-800">{r.low_stock_count}</span>,
        },
        {
            key: 'missing_arabic',
            label: 'Missing AR',
            className: 'text-end',
            hideOnMd: true,
            render: (r) => <span className="text-slate-800">{r.missing_arabic_count}</span>,
        },
        {
            key: 'store_score',
            label: 'Store %',
            className: 'text-end',
            render: (r) => (
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                    r.store_completion_score >= 80
                        ? 'bg-emerald-100 text-emerald-800'
                        : r.store_completion_score >= 50
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-rose-100 text-rose-800'
                }`}>
                    {r.store_completion_score}%
                </span>
            ),
        },
    ];

    const renderCard = (r: VendorSummaryRow) => (
        <div className="bg-white border border-slate-200 rounded-xl p-4" data-testid="admin-vi-mobile-card">
            <div className="mb-2 min-w-0">
                <div className="text-sm font-semibold text-slate-900 whitespace-normal">{r.business_name}</div>
                <div className="text-xs text-slate-500 truncate">{r.business_email}</div>
            </div>
            <dl className="grid grid-cols-2 gap-y-1 text-xs">
                <dt className="text-slate-500">Active products</dt>
                <dd className="text-end text-slate-800">{r.total_active_products}</dd>
                <dt className="text-slate-500">Active alerts</dt>
                <dd className="text-end text-slate-800">{r.active_alerts_count}</dd>
                <dt className="text-slate-500">Low stock</dt>
                <dd className="text-end text-slate-800">{r.low_stock_count}</dd>
                <dt className="text-slate-500">Missing AR</dt>
                <dd className="text-end text-slate-800">{r.missing_arabic_count}</dd>
                <dt className="text-slate-500">Store %</dt>
                <dd className="text-end font-medium text-slate-900">{r.store_completion_score}%</dd>
            </dl>
        </div>
    );

    return (
        <AdminLayout title="Vendor intelligence overview">
            <PageContainer>
                <PageHeader
                    title={t('admin.vendor_intelligence.title', 'Vendor intelligence overview')}
                    description={t('admin.vendor_intelligence.subtitle', 'Aggregate signals — helps identify vendors that need attention.')}
                    testId="admin-vi-title"
                />

                {/* Rollup */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 mb-4" data-testid="admin-vi-rollup">
                    <RollupCard icon={<Users size={16} />}       label="Vendors"      value={rollup.total_vendors} />
                    <RollupCard icon={<AlertTriangle size={16} />} label="Active alerts" value={rollup.total_alerts} />
                    <RollupCard icon={<Sparkles size={16} />}    label="Avg store %"  value={`${rollup.avg_completion}%`} />
                    <RollupCard icon={<TrendingUp size={16} />}  label="Avg quality"  value={`${rollup.avg_quality}%`} />
                </div>

                {/* Filter chips */}
                <div className="flex flex-wrap gap-2 mb-4 overflow-x-auto" data-testid="admin-vi-filters">
                    {FILTERS.map((f) => (
                        <button
                            key={f.label}
                            type="button"
                            onClick={() => router.get('/admin/vendor-intelligence', f.key ? { filter: f.key } : {}, { preserveState: false, preserveScroll: false })}
                            className={`text-xs px-3 py-1.5 rounded-full border whitespace-nowrap ${
                                filter === f.key
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                            }`}
                            data-testid={`admin-vi-filter-${f.key ?? 'all'}`}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>

                <ResponsiveDataList
                    items={summaries.data}
                    columns={columns}
                    renderCard={renderCard}
                    getKey={(r) => r.id}
                    testId="admin-vi-list"
                />

                {/* Pagination */}
                {summaries.data.length > 0 && summaries.links.length > 3 && (
                    <div className="mt-6 flex justify-center flex-wrap gap-1">
                        {summaries.links.map((l) => (
                            <Link
                                key={l.label}
                                href={l.url ?? '#'}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                                className={`text-sm px-3 py-1 rounded ${
                                    l.active
                                        ? 'bg-indigo-600 text-white'
                                        : l.url
                                            ? 'text-slate-700 hover:bg-slate-100'
                                            : 'text-slate-400 cursor-default'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </AdminLayout>
    );
}

function RollupCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: number | string }) {
    return (
        <div className="bg-white border border-slate-200 rounded-lg p-3">
            <div className="flex items-center gap-2 text-slate-500 mb-1">
                <span aria-hidden="true">{icon}</span>
                <span className="text-xs whitespace-nowrap">{label}</span>
            </div>
            <div className="text-lg sm:text-xl font-semibold text-slate-900">{value}</div>
        </div>
    );
}
