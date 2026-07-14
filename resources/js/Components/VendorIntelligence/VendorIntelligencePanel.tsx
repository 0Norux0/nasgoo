import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, Package, TrendingUp, TrendingDown,
    Sparkles, CheckCircle2, X, Clock, PauseCircle,
} from 'lucide-react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

/**
 * Phase 11B.4 §22 §23 — Vendor Dashboard Intelligence panel.
 *
 * Fetches from GET /vendor/intelligence (JSON) and renders:
 *   1. Summary counters (out-of-stock, low-stock, slow-moving, etc.)
 *   2. Critical alerts list (priority ordered)
 *   3. Store completion + action checklist
 *   4. Best-selling / most-viewed highlights
 *
 * Uses ONLY approved layout primitives:
 *   - responsive cards on mobile (no letter-by-letter wrapping — v11B.3.3
 *     CSS root-cause fix preserved)
 *   - shared Container gutter (16px on mobile via v11A.2)
 *   - Arabic RTL via native flex flow (start/end keywords in the CSS)
 */

interface Summary {
    total_products: number;
    total_active_products: number;
    out_of_stock_count: number;
    low_stock_count: number;
    slow_moving_count: number;
    missing_arabic_count: number;
    missing_images_count: number;
    active_alerts_count: number;
    store_completion_score: number;
    avg_product_quality: number;
    computed_at: string | null;
    // Phase 11B.4 v11B.4.2 Defect 11 fix — freshness fields
    last_generated_at: string | null;
    stale_at: string | null;
    stale_reason: string | null;
    is_stale: boolean;
}

interface Alert {
    id: number;
    alert_type: string;
    entity_type: string | null;
    entity_id: number | null;
    priority: 'critical' | 'high' | 'medium' | 'low' | 'info';
    status: string;
    evidence: Record<string, unknown>;
    is_dismissable: boolean;
    action_link: string;
}

interface PerformanceRow {
    product_id: number;
    name: string;
    units_sold?: number;
    revenue_minor?: number;
    views?: number;
    wishlist_count?: number;
}

interface ChecklistItem {
    key: string;
    title: string;
    priority: string;
    link: string;
    evidence: Record<string, unknown>;
}

interface DashboardPayload {
    summary: Summary;
    alerts: Alert[];
    top_selling: PerformanceRow[];
    most_viewed: PerformanceRow[];
    most_wishlisted: PerformanceRow[];
    checklist: ChecklistItem[];
    store_completion: { score: number; missing_fields: string[] };
}

const PRIORITY_COLORS: Record<string, string> = {
    critical: 'bg-rose-100 text-rose-800 border-rose-200',
    high:     'bg-orange-100 text-orange-800 border-orange-200',
    medium:   'bg-amber-100 text-amber-800 border-amber-200',
    low:      'bg-slate-100 text-slate-700 border-slate-200',
    info:     'bg-blue-100 text-blue-800 border-blue-200',
};

export default function VendorIntelligencePanel() {
    const t = useT();
    const { siteSettings } = usePage<SharedProps>().props;
    const featureEnabled = (siteSettings?.vendor_intelligence as { enabled?: boolean } | undefined)?.enabled ?? true;

    const [payload, setPayload] = useState<DashboardPayload | null>(null);
    const [loading, setLoading] = useState(featureEnabled);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        // Phase 11B.4 v11B.4.2 Defect 4 fix — skip the fetch entirely when
        // the feature is disabled. Pre-v11B.4.2 the panel always fetched
        // even when the admin turned the feature off, wasting the
        // endpoint round-trip and potentially showing stale cached data.
        if (! featureEnabled) {
            setLoading(false);
            return;
        }
        fetch('/vendor/intelligence', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((r) => {
                if (!r.ok) throw new Error(String(r.status));
                return r.json();
            })
            .then((data) => {
                // Server can also return {enabled:false} if the flag was
                // toggled between page load and the fetch. Respect it.
                if (data.enabled === false) {
                    setPayload(null);
                } else {
                    setPayload(data);
                }
            })
            .catch((e) => setError(String(e)))
            .finally(() => setLoading(false));
    }, [featureEnabled]);

    // Phase 11B.4 v11B.4.2 Defect 4 fix — disabled banner is a distinct
    // UI state so the vendor can see WHY the panel isn't showing data.
    if (! featureEnabled) {
        return (
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-center gap-3" data-testid="vendor-intelligence-disabled">
                <PauseCircle size={20} className="text-slate-400 flex-shrink-0" aria-hidden="true" />
                <div className="text-sm text-slate-600">
                    {t('vendor_intelligence.feature_disabled_msg',
                        'Vendor insights are currently disabled by the marketplace administrator.')}
                </div>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-500" data-testid="vendor-intelligence-loading">
                {t('vendor_intelligence.loading', 'Loading insights…')}
            </div>
        );
    }
    if (error || !payload) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-500" data-testid="vendor-intelligence-error">
                {t('vendor_intelligence.unavailable', 'Insights are temporarily unavailable.')}
            </div>
        );
    }

    return (
        <section className="space-y-4" data-testid="vendor-intelligence-panel" aria-label="Vendor intelligence">

            {/* Phase 11B.4 v11B.4.2 Defect 11 fix — stale/last-generated banner.
                Shows the vendor exactly how fresh the data is. */}
            {payload.summary.last_generated_at && (
                <div
                    className={`text-xs px-3 py-2 rounded-md border flex items-center gap-2 flex-wrap ${
                        payload.summary.is_stale
                            ? 'bg-amber-50 border-amber-200 text-amber-800'
                            : 'bg-slate-50 border-slate-200 text-slate-600'
                    }`}
                    data-testid="vi-freshness-banner"
                >
                    <Clock size={12} aria-hidden="true" />
                    {payload.summary.is_stale ? (
                        <span data-testid="vi-freshness-stale">
                            {t('vendor_intelligence.stale_message',
                                'Data refresh pending — a new snapshot is being prepared.')}
                        </span>
                    ) : (
                        <span data-testid="vi-freshness-fresh">
                            {t('vendor_intelligence.last_generated', 'Last refreshed:')} {' '}
                            <time dateTime={payload.summary.last_generated_at}>
                                {new Date(payload.summary.last_generated_at).toLocaleString()}
                            </time>
                        </span>
                    )}
                </div>
            )}

            {/* ─── Summary counters ─── */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3" data-testid="vi-summary">
                <SummaryCard
                    icon={<AlertTriangle size={16} />}
                    label={t('vendor_intelligence.summary.out_of_stock', 'Out of stock')}
                    value={payload.summary.out_of_stock_count}
                    tone={payload.summary.out_of_stock_count > 0 ? 'rose' : 'slate'}
                    testId="vi-summary-oos"
                />
                <SummaryCard
                    icon={<Package size={16} />}
                    label={t('vendor_intelligence.summary.low_stock', 'Low stock')}
                    value={payload.summary.low_stock_count}
                    tone={payload.summary.low_stock_count > 0 ? 'amber' : 'slate'}
                    testId="vi-summary-low"
                />
                <SummaryCard
                    icon={<TrendingDown size={16} />}
                    label={t('vendor_intelligence.summary.slow_moving', 'Slow moving')}
                    value={payload.summary.slow_moving_count}
                    tone="slate"
                    testId="vi-summary-slow"
                />
                <SummaryCard
                    icon={<Sparkles size={16} />}
                    label={t('vendor_intelligence.summary.store_score', 'Store %')}
                    value={payload.summary.store_completion_score}
                    tone={payload.summary.store_completion_score >= 80 ? 'emerald' : 'amber'}
                    testId="vi-summary-store"
                />
            </div>

            {/* ─── Action alerts ─── */}
            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden" data-testid="vi-alerts">
                <div className="px-4 py-3 border-b border-slate-200 flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold text-slate-900 whitespace-nowrap">
                        {t('vendor_intelligence.alerts_title', 'Action alerts')}
                    </h2>
                    <span className="text-xs text-slate-500">{payload.alerts.length}</span>
                </div>
                {payload.alerts.length === 0 ? (
                    <div className="p-6 text-center text-sm text-slate-500" data-testid="vi-alerts-empty">
                        <CheckCircle2 size={24} className="mx-auto text-emerald-500 mb-2" aria-hidden="true" />
                        {t('vendor_intelligence.no_alerts', 'All clear — nothing needs your attention right now.')}
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {payload.alerts.map((a) => (
                            <AlertRow key={a.id} alert={a} onRefresh={refreshFromServer} />
                        ))}
                    </ul>
                )}
            </div>

            {/* ─── Checklist ─── */}
            {payload.checklist.length > 0 && (
                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden" data-testid="vi-checklist">
                    <div className="px-4 py-3 border-b border-slate-200">
                        <h2 className="text-sm font-semibold text-slate-900">
                            {t('vendor_intelligence.checklist_title', 'Your action checklist')}
                        </h2>
                    </div>
                    <ul className="divide-y divide-slate-100">
                        {payload.checklist.map((c) => (
                            <li key={c.key} className="px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm text-slate-800 whitespace-normal">
                                        {t(c.title, c.key)}
                                    </div>
                                    <div className="text-xs text-slate-500 mt-0.5">
                                        {JSON.stringify(c.evidence)}
                                    </div>
                                </div>
                                <Link
                                    href={c.link}
                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-700 whitespace-nowrap"
                                    data-testid={`vi-checklist-${c.key}`}
                                >
                                    {t('common.review', 'Review')} →
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* ─── Highlights ─── */}
            {payload.top_selling.length > 0 && (
                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden" data-testid="vi-top-selling">
                    <div className="px-4 py-3 border-b border-slate-200 flex items-center gap-2">
                        <TrendingUp size={16} className="text-emerald-600" aria-hidden="true" />
                        <h2 className="text-sm font-semibold text-slate-900">
                            {t('vendor_intelligence.top_selling_title', 'Top selling (last 30 days)')}
                        </h2>
                    </div>
                    <ul className="divide-y divide-slate-100">
                        {payload.top_selling.map((row) => (
                            <li key={row.product_id} className="px-4 py-2 flex items-center justify-between gap-3">
                                <Link
                                    href={`/vendor/products/${row.product_id}/edit`}
                                    className="text-sm text-slate-800 hover:text-indigo-600 truncate flex-1 min-w-0"
                                >
                                    {row.name}
                                </Link>
                                <span className="text-xs text-slate-600 whitespace-nowrap">
                                    {row.units_sold} {t('vendor_intelligence.units', 'units')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );

    function refreshFromServer() {
        setLoading(true);
        fetch('/vendor/intelligence', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => setPayload(data))
            .finally(() => setLoading(false));
    }
}

function SummaryCard({
    icon, label, value, tone, testId,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
    tone: 'rose' | 'amber' | 'emerald' | 'slate';
    testId?: string;
}) {
    const toneCls = {
        rose:    'bg-rose-50 border-rose-200 text-rose-800',
        amber:   'bg-amber-50 border-amber-200 text-amber-800',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        slate:   'bg-white border-slate-200 text-slate-800',
    }[tone];

    return (
        <div className={`border rounded-lg p-3 ${toneCls}`} data-testid={testId}>
            <div className="flex items-center gap-2 mb-1">
                <span aria-hidden="true" className="opacity-70">{icon}</span>
                <span className="text-xs whitespace-nowrap">{label}</span>
            </div>
            <div className="text-lg sm:text-xl font-semibold" data-testid={`${testId}-value`}>
                {value}
            </div>
        </div>
    );
}

function AlertRow({ alert, onRefresh }: { alert: Alert; onRefresh: () => void }) {
    const t = useT();
    const priorityCls = PRIORITY_COLORS[alert.priority] ?? PRIORITY_COLORS.medium;
    const productName = (alert.evidence?.product_name as string) ?? '';

    const dismiss = () => {
        router.post('/vendor/intelligence/dismiss', {
            suggestion_type: alert.alert_type,
            entity_type: alert.entity_type ?? 'product',
            entity_id: alert.entity_id,
        }, { onSuccess: onRefresh, preserveScroll: true });
    };

    const snooze = () => {
        router.post('/vendor/intelligence/snooze', {
            suggestion_type: alert.alert_type,
            entity_type: alert.entity_type ?? 'product',
            entity_id: alert.entity_id,
        }, { onSuccess: onRefresh, preserveScroll: true });
    };

    return (
        <li className="p-3 flex items-start gap-3" data-testid={`vi-alert-${alert.alert_type}`}>
            <span
                className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border whitespace-nowrap ${priorityCls}`}
                data-testid={`vi-alert-priority-${alert.priority}`}
            >
                {alert.priority}
            </span>
            <div className="min-w-0 flex-1">
                <div className="text-sm font-medium text-slate-900">
                    {t(`vendor_intelligence.alerts.${alert.alert_type}.title`, alert.alert_type.replace(/_/g, ' '))}
                </div>
                {productName && (
                    <div className="text-xs text-slate-600 truncate">{productName}</div>
                )}
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
                <Link
                    href={alert.action_link}
                    className="text-xs font-medium text-indigo-600 hover:text-indigo-700 whitespace-nowrap"
                    data-testid={`vi-alert-action-${alert.id}`}
                >
                    {t('common.review', 'Review')}
                </Link>
                <button
                    type="button"
                    onClick={snooze}
                    aria-label={t('vendor_intelligence.snooze', 'Snooze')}
                    className="text-slate-400 hover:text-slate-700"
                    data-testid={`vi-alert-snooze-${alert.id}`}
                >
                    <Clock size={14} />
                </button>
                {alert.is_dismissable && (
                    <button
                        type="button"
                        onClick={dismiss}
                        aria-label={t('vendor_intelligence.dismiss', 'Dismiss')}
                        className="text-slate-400 hover:text-slate-700"
                        data-testid={`vi-alert-dismiss-${alert.id}`}
                    >
                        <X size={14} />
                    </button>
                )}
            </div>
        </li>
    );
}
