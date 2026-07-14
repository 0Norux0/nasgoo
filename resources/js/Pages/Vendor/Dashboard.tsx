import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import VendorIntelligencePanel from '@/Components/VendorIntelligence/VendorIntelligencePanel';

interface PackageProps {
    name: string;
    slug: string;
    billing_cycle: string;
    analytics_level: string;
    features: Record<string, boolean>;
    limits: {
        max_products: number | null;
        max_services: number | null;
        max_images_per_product: number;
    };
}

interface Props {
    vendor: {
        id: number;
        business_name: string;
        slug: string;
        status: 'pending' | 'approved' | 'rejected' | 'suspended' | 'closed';
        rejection_reason: string | null;
        logo_path: string | null;
        created_at: string | null;
    };
    package: PackageProps | null;
    subscription: { status: string; starts_at: string | null; ends_at: string | null } | null;
    commission: {
        scope: string;
        commission_type: string;
        percent_value: string | null;
        fixed_value_minor: number | null;
    } | null;
    profile_completion: number;
}

export default function VendorDashboard({ vendor, package: pkg, subscription, commission, profile_completion }: Props) {
    return (
        <VendorLayout title="Vendor Dashboard">
            {/* Status banner */}
            <StatusBanner status={vendor.status} reason={vendor.rejection_reason} />

            {/* Phase 11B.4 §22 — vendor intelligence panel.
                Only shown for approved vendors — pending/rejected vendors
                shouldn't see stock alerts for products they can't yet sell. */}
            {vendor.status === 'approved' && (
                <div className="mb-6" data-testid="vendor-dashboard-intelligence-slot">
                    <VendorIntelligencePanel />
                </div>
            )}

            {/* Business header */}
            <div className="bg-white border border-slate-200 rounded-xl p-6 mb-6 flex items-center justify-between">
                <div>
                    <div className="text-sm text-slate-500">Business</div>
                    <div className="text-xl font-semibold text-slate-900">{vendor.business_name}</div>
                    <div className="text-xs text-slate-400 mt-1">slug: {vendor.slug}</div>
                </div>
                {vendor.status === 'approved' && (
                    <Link href={`/vendors/${vendor.slug}`} className="text-sm text-indigo-600 hover:underline">
                        View public storefront →
                    </Link>
                )}
            </div>

            {/* Phase 10 v10.13 — Reports CTA card. The dev's v10.13 report
                said they couldn't find the Reports menu item in the nav.
                The nav link IS rendered (in baseItems on VendorLayout) but
                gets lost among 15 other nav items. This dashboard card
                surfaces Reports as a prominent CTA so vendors can reach it
                even before scanning the nav. Shows only for APPROVED vendors
                (the route is gated by vendor:approved middleware; showing
                the CTA to a pending/rejected/suspended vendor would silently
                redirect them on click). */}
            {vendor.status === 'approved' && (
                <Link
                    href="/vendor/reports"
                    data-testid="vendor-dashboard-reports-cta"
                    className="block bg-gradient-to-r from-indigo-50 to-indigo-100 border border-indigo-200 rounded-xl p-5 mb-6 hover:from-indigo-100 hover:to-indigo-200 transition-colors"
                >
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="rounded-lg bg-indigo-600 p-3 flex-shrink-0">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="white"
                                    className="w-6 h-6"
                                    aria-hidden="true"
                                >
                                    <path d="M3 17a1 1 0 0 1-1-1V4a1 1 0 0 1 2 0v12a1 1 0 0 1-1 1Zm5 0a1 1 0 0 1-1-1V9a1 1 0 0 1 2 0v7a1 1 0 0 1-1 1Zm5 0a1 1 0 0 1-1-1V7a1 1 0 0 1 2 0v9a1 1 0 0 1-1 1Zm5 0a1 1 0 0 1-1-1V11a1 1 0 0 1 2 0v5a1 1 0 0 1-1 1Z" />
                                </svg>
                            </div>
                            <div>
                                <div className="text-lg font-semibold text-slate-900">View My Reports</div>
                                <div className="text-sm text-slate-600 mt-0.5">
                                    Gross sales, commission, earnings, payouts, top products — your own data only.
                                </div>
                            </div>
                        </div>
                        <div className="text-indigo-700 font-semibold text-sm whitespace-nowrap ml-4">
                            Open Reports →
                        </div>
                    </div>
                </Link>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {/* Package card */}
                <Card title="Current Package">
                    {pkg ? (
                        <>
                            <div className="text-lg font-semibold text-slate-900">{pkg.name}</div>
                            <div className="text-xs text-slate-500 mt-1 mb-3">
                                {pkg.billing_cycle} · {pkg.analytics_level} analytics
                            </div>
                            <div className="text-xs text-slate-600">
                                Limits: {pkg.limits.max_products ?? '∞'} products, {pkg.limits.max_services ?? '∞'} services,{' '}
                                {pkg.limits.max_images_per_product} images each
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-slate-500">No active package yet.</p>
                    )}
                </Card>

                {/* Subscription card */}
                <Card title="Subscription">
                    {subscription ? (
                        <>
                            <StatusPill status={subscription.status} />
                            <div className="text-xs text-slate-600 mt-3">
                                Started: {subscription.starts_at ?? '—'}
                                <br />
                                Ends: {subscription.ends_at ?? '∞ (lifetime)'}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-slate-500">No subscription on file.</p>
                    )}
                </Card>

                {/* Commission card */}
                <Card title="Commission Rule">
                    {commission ? (
                        <>
                            <div className="text-sm">
                                Type: <strong>{commission.commission_type}</strong>
                            </div>
                            {commission.percent_value && (
                                <div className="text-lg font-semibold text-slate-900 mt-1">{commission.percent_value}%</div>
                            )}
                            <div className="text-xs text-slate-500 mt-2">Scope: {commission.scope}</div>
                        </>
                    ) : (
                        <p className="text-sm text-slate-500">No commission rule resolved.</p>
                    )}
                </Card>

                {/* Features card */}
                {pkg && (
                    <Card title="Allowed Features" className="md:col-span-2">
                        <ul className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm">
                            {Object.entries(pkg.features).map(([k, v]) => (
                                <li key={k} className="flex items-center gap-2">
                                    <span className={`w-2 h-2 rounded-full ${v ? 'bg-emerald-500' : 'bg-slate-300'}`} />
                                    <span className={v ? 'text-slate-900' : 'text-slate-400 line-through'}>
                                        {k.replace(/_/g, ' ')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                {/* Profile completion */}
                <Card title="Profile Completion">
                    <div className="flex items-center gap-3">
                        <div className="flex-1 bg-slate-200 h-2 rounded-full overflow-hidden">
                            <div
                                className="bg-indigo-500 h-full transition-all"
                                style={{ width: `${profile_completion}%` }}
                            />
                        </div>
                        <span className="text-sm font-semibold text-slate-900">{profile_completion}%</span>
                    </div>
                    {vendor.status === 'approved' && (
                        <Link href="/vendor/profile" className="text-xs text-indigo-600 hover:underline mt-3 inline-block">
                            Complete your profile →
                        </Link>
                    )}
                </Card>
            </div>
        </VendorLayout>
    );
}

function StatusBanner({ status, reason }: { status: string; reason: string | null }) {
    const config = {
        pending:   { color: 'bg-amber-50 border-amber-200 text-amber-800',     title: 'Application under review', body: 'Our team will review your application shortly. You will be notified by email once a decision is made.' },
        approved:  { color: 'bg-emerald-50 border-emerald-200 text-emerald-800', title: 'Approved', body: 'Your vendor account is live. You can now manage your profile and (in Phase 3) list products.' },
        rejected:  { color: 'bg-rose-50 border-rose-200 text-rose-800',       title: 'Application rejected', body: reason ?? 'Please contact support for details.' },
        suspended: { color: 'bg-orange-50 border-orange-200 text-orange-800', title: 'Account suspended',  body: 'Your vendor actions are paused. Please contact support.' },
        closed:    { color: 'bg-slate-100 border-slate-200 text-slate-700',   title: 'Account closed', body: 'This vendor account has been closed.' },
    }[status] ?? { color: 'bg-slate-100 border-slate-200 text-slate-700', title: status, body: '' };

    return (
        <div className={`border rounded-xl px-4 py-3 mb-6 ${config.color}`}>
            <div className="font-semibold">{config.title}</div>
            {config.body && <div className="text-sm mt-1">{config.body}</div>}
        </div>
    );
}

function StatusPill({ status }: { status: string }) {
    const colors: Record<string, string> = {
        active: 'bg-emerald-100 text-emerald-800',
        pending: 'bg-amber-100 text-amber-800',
        expired: 'bg-rose-100 text-rose-800',
        cancelled: 'bg-slate-200 text-slate-700',
        grace: 'bg-orange-100 text-orange-800',
    };
    return (
        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${colors[status] ?? 'bg-slate-100 text-slate-700'}`}>
            {status}
        </span>
    );
}

function Card({ title, children, className = '' }: { title: string; children: ReactNode; className?: string }) {
    return (
        <div className={`bg-white border border-slate-200 rounded-xl p-5 ${className}`}>
            <div className="text-xs uppercase tracking-wide text-slate-500 mb-3">{title}</div>
            {children}
        </div>
    );
}
