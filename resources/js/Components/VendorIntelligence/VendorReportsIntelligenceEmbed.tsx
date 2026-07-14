import { useEffect, useState } from 'react';
import { usePage, Link } from '@inertiajs/react';
import { Activity, Package, AlertTriangle, Sparkles, ArrowRight } from 'lucide-react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

/**
 * Phase 11B.4 v11B.4.2 Defect 8 fix — vendor reports intelligence embed.
 *
 * Lightweight card that reads from the pre-generated
 * `vendor_intelligence_summaries` row (via the same /vendor/intelligence
 * endpoint). Does NOT trigger regeneration. Displays the top-line
 * counters + links to the full panel on /vendor.
 *
 * Respects `siteSettings.vendor_intelligence.enabled` — renders nothing
 * when the feature is off.
 */

interface Summary {
    active_alerts_count: number;
    out_of_stock_count: number;
    low_stock_count: number;
    slow_moving_count: number;
    avg_product_quality: number;
    store_completion_score: number;
    last_generated_at: string | null;
    is_stale: boolean;
}

interface Alert {
    id: number;
    alert_type: string;
    priority: string;
    evidence: Record<string, unknown>;
    action_link: string;
}

interface Payload {
    enabled?: boolean;
    summary?: Summary;
    alerts?: Alert[];
}

export default function VendorReportsIntelligenceEmbed() {
    const t = useT();
    const { siteSettings } = usePage<SharedProps>().props;
    const featureEnabled = (siteSettings?.vendor_intelligence as { enabled?: boolean } | undefined)?.enabled ?? true;

    const [payload, setPayload] = useState<Payload | null>(null);
    const [loading, setLoading] = useState(featureEnabled);

    useEffect(() => {
        if (! featureEnabled) return;
        fetch('/vendor/intelligence', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((r) => r.ok ? r.json() : null)
            .then((data) => setPayload(data))
            .catch(() => setPayload(null))
            .finally(() => setLoading(false));
    }, [featureEnabled]);

    if (! featureEnabled) return null;
    if (loading || !payload || payload.enabled === false || !payload.summary) return null;

    const s = payload.summary;
    const criticalCount = (payload.alerts ?? []).filter((a) => a.priority === 'critical').length;

    return (
        <section
            className="bg-white border border-slate-200 rounded-xl overflow-hidden mb-6"
            data-testid="vendor-reports-intelligence-embed"
        >
            <div className="px-4 py-3 border-b border-slate-200 flex items-center justify-between gap-3 flex-wrap">
                <h2 className="text-sm font-semibold text-slate-900 flex items-center gap-2 whitespace-nowrap">
                    <Activity size={16} className="text-indigo-600" aria-hidden="true" />
                    {t('vendor_intelligence.reports_embed_title', 'Intelligence insights')}
                </h2>
                <Link
                    href="/vendor"
                    className="text-xs font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1 whitespace-nowrap"
                    data-testid="vendor-reports-intelligence-link"
                >
                    {t('common.view_full_dashboard', 'View full dashboard')}
                    <ArrowRight size={12} />
                </Link>
            </div>
            <div className="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                <MetricCell icon={<AlertTriangle size={14} />}
                            label={t('vendor_intelligence.reports.active_alerts', 'Active alerts')}
                            value={s.active_alerts_count}
                            tone={s.active_alerts_count > 0 ? 'rose' : 'slate'} />
                <MetricCell icon={<Package size={14} />}
                            label={t('vendor_intelligence.reports.critical', 'Critical')}
                            value={criticalCount}
                            tone={criticalCount > 0 ? 'rose' : 'slate'} />
                <MetricCell icon={<Package size={14} />}
                            label={t('vendor_intelligence.summary.low_stock', 'Low stock')}
                            value={s.low_stock_count}
                            tone={s.low_stock_count > 0 ? 'amber' : 'slate'} />
                <MetricCell icon={<Sparkles size={14} />}
                            label={t('vendor_intelligence.reports.quality', 'Avg quality')}
                            value={`${s.avg_product_quality}%`}
                            tone={s.avg_product_quality >= 80 ? 'emerald' : 'amber'} />
            </div>
            {s.last_generated_at && (
                <div className="px-4 pb-3 text-xs text-slate-500">
                    {s.is_stale
                        ? t('vendor_intelligence.reports.stale', 'Data refresh pending — new data expected shortly.')
                        : t('vendor_intelligence.reports.last_generated', 'Last generated:')} {' '}
                    <time dateTime={s.last_generated_at}>{new Date(s.last_generated_at).toLocaleString()}</time>
                </div>
            )}
        </section>
    );
}

function MetricCell({ icon, label, value, tone }: {
    icon: React.ReactNode;
    label: string;
    value: number | string;
    tone: 'rose' | 'amber' | 'emerald' | 'slate';
}) {
    const cls = {
        rose:    'bg-rose-50 border-rose-200 text-rose-800',
        amber:   'bg-amber-50 border-amber-200 text-amber-800',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        slate:   'bg-slate-50 border-slate-200 text-slate-700',
    }[tone];
    return (
        <div className={`border rounded-lg p-2.5 ${cls}`}>
            <div className="flex items-center gap-1.5 text-xs mb-1 opacity-80">
                <span aria-hidden="true">{icon}</span>
                <span className="whitespace-nowrap">{label}</span>
            </div>
            <div className="text-lg font-semibold">{value}</div>
        </div>
    );
}
