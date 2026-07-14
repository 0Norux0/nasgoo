import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageContainer, PageHeader } from '@/Components/Layout/PageContainer';
import type { SharedProps } from '@/types/inertia';

interface LicenseStatus {
    enforcement_enabled: boolean;
    configured: boolean;
    status: 'active' | 'grace' | 'expired' | 'unlicensed' | 'unconfigured';
    expires_at: string | null;
    days_remaining: number | null;
    license_holder: string | null;
    license_type: string | null;
    domain: string | null;
    app_url: string | null;
    installation_id: string;
    server_fingerprint: string;
    warning_level: 'ok' | 'notice' | 'warning' | 'urgent' | 'expired' | 'grace';
}

interface Props {
    status: LicenseStatus;
}

const STATUS_COLOR: Record<LicenseStatus['status'], string> = {
    active: 'border-emerald-200 bg-emerald-100 text-emerald-800',
    grace: 'border-amber-200 bg-amber-100 text-amber-800',
    expired: 'border-rose-200 bg-rose-100 text-rose-800',
    unlicensed: 'border-slate-200 bg-slate-100 text-slate-700',
    unconfigured: 'border-slate-200 bg-slate-100 text-slate-700',
};

const STATUS_PILL_BASE =
    'inline-block rounded-md border px-2.5 py-1 text-sm font-medium';

const CARD_BASE = 'rounded-xl border border-slate-200 bg-white p-5';

const CARD_LABEL = 'mb-2 text-xs uppercase tracking-wide text-slate-500';

const FLASH_SUCCESS =
    'mb-4 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800';

const FLASH_ERROR =
    'mb-4 rounded border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800';

const TEXTAREA_INPUT =
    'w-full rounded-md border border-slate-300 p-3 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500';

const SUBMIT_BUTTON =
    'rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50';

const CODE_CHIP = 'break-all rounded bg-slate-100 px-1.5 py-0.5 text-xs';

function buildBanner(
    level: LicenseStatus['warning_level'],
    daysRemaining: number | null,
): { cls: string; text: string } | null {
    if (level === 'expired') {
        return {
            cls: 'border-rose-200 bg-rose-50 text-rose-900',
            text: 'The marketplace license has expired.',
        };
    }
    if (level === 'grace') {
        return {
            cls: 'border-amber-200 bg-amber-50 text-amber-900',
            text: 'The license has expired but is within the grace period. Renew before it lapses.',
        };
    }
    if (level === 'urgent') {
        return {
            cls: 'border-rose-200 bg-rose-50 text-rose-900',
            text: `Only ${daysRemaining} day(s) remaining before the license expires.`,
        };
    }
    if (level === 'warning') {
        return {
            cls: 'border-amber-200 bg-amber-50 text-amber-900',
            text: `${daysRemaining} day(s) remaining before the license expires.`,
        };
    }
    if (level === 'notice') {
        return {
            cls: 'border-sky-200 bg-sky-50 text-sky-900',
            text: `${daysRemaining} day(s) remaining before the license expires.`,
        };
    }
    return null;
}

export default function Index({ status }: Props) {
    const flash = (usePage<SharedProps>().props.flash ?? {}) as {
        license_success?: string;
        license_error?: string;
    };

    const [copied, setCopied] = useState<string | null>(null);
    const copy = (label: string, value: string) => {
        navigator.clipboard.writeText(value);
        setCopied(label);
        setTimeout(() => setCopied(null), 1500);
    };

    const form = useForm<{ token: string }>({ token: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/license/activate', {
            preserveScroll: true,
            onSuccess: () => form.reset('token'),
        });
    };

    const banner = buildBanner(status.warning_level, status.days_remaining);
    const configuredLabel = status.configured
        ? 'Yes'
        : 'No — set LICENSE_PUBLIC_KEY in .env';

    return (
        <AdminLayout title="License">
            <Head title="License" />
            <PageContainer>
                <PageHeader
                    title="Marketplace License"
                    description="Manage the ownership-protection license for this installation."
                />

                {flash.license_success && (
                    <div className={FLASH_SUCCESS}>{flash.license_success}</div>
                )}
                {flash.license_error && (
                    <div className={FLASH_ERROR}>{flash.license_error}</div>
                )}

                {banner && (
                    <div className={`mb-4 rounded-lg border p-4 ${banner.cls}`}>
                        {banner.text}
                    </div>
                )}

                <div className="mb-6 grid gap-4 md:grid-cols-2">
                    <div className={CARD_BASE}>
                        <div className={CARD_LABEL}>Current status</div>
                        <div className="flex items-center gap-2">
                            <span
                                className={`${STATUS_PILL_BASE} ${STATUS_COLOR[status.status]}`}
                            >
                                {status.status.toUpperCase()}
                            </span>
                            {status.days_remaining !== null && status.status === 'active' && (
                                <span className="text-sm text-slate-600">
                                    {status.days_remaining} day(s) remaining
                                </span>
                            )}
                        </div>
                        <dl className="mt-4 space-y-1 text-sm text-slate-700">
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Enforcement</dt>
                                <dd>{status.enforcement_enabled ? 'Enabled' : 'Disabled'}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-slate-500">Public key installed</dt>
                                <dd>{configuredLabel}</dd>
                            </div>
                            {status.license_holder && (
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Holder</dt>
                                    <dd>{status.license_holder}</dd>
                                </div>
                            )}
                            {status.license_type && (
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Type</dt>
                                    <dd>{status.license_type}</dd>
                                </div>
                            )}
                            {status.domain && (
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Bound to domain</dt>
                                    <dd>{status.domain}</dd>
                                </div>
                            )}
                            {status.expires_at && (
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Expires</dt>
                                    <dd>{new Date(status.expires_at).toUTCString()}</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    <div className={CARD_BASE}>
                        <div className={CARD_LABEL}>Server identity</div>
                        <div className="space-y-3 text-sm text-slate-700">
                            <div>
                                <div className="mb-0.5 text-slate-500">Installation ID</div>
                                <div className="flex items-center gap-2">
                                    <code className={CODE_CHIP}>{status.installation_id}</code>
                                    <button
                                        type="button"
                                        className="text-xs text-indigo-600 hover:underline"
                                        onClick={() =>
                                            copy('installation_id', status.installation_id)
                                        }
                                    >
                                        {copied === 'installation_id' ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div className="mb-0.5 text-slate-500">Server fingerprint</div>
                                <div className="flex items-center gap-2">
                                    <code className={CODE_CHIP}>{status.server_fingerprint}</code>
                                    <button
                                        type="button"
                                        className="text-xs text-indigo-600 hover:underline"
                                        onClick={() =>
                                            copy('fingerprint', status.server_fingerprint)
                                        }
                                    >
                                        {copied === 'fingerprint' ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div className="mb-0.5 text-slate-500">App URL</div>
                                <code className={CODE_CHIP}>{status.app_url ?? '-'}</code>
                            </div>
                            <div className="pt-2 text-xs text-slate-500">
                                Send the installation ID + fingerprint to the license owner when
                                requesting a new token.
                            </div>
                        </div>
                    </div>
                </div>

                <div className={CARD_BASE}>
                    <div className={CARD_LABEL}>Activate a token</div>
                    <form onSubmit={submit} className="space-y-3">
                        <label
                            htmlFor="license-token"
                            className="block text-sm font-medium text-slate-700"
                        >
                            License token (paste the full three-part token here)
                        </label>
                        <textarea
                            id="license-token"
                            data-testid="license-token"
                            value={form.data.token}
                            onChange={(e) => form.setData('token', e.target.value)}
                            rows={5}
                            spellCheck={false}
                            className={TEXTAREA_INPUT}
                            placeholder="eyJhbGciOiJFZERTQSIsInR5cCI6Ik1QTElDIn0..."
                        />
                        {form.errors.token && (
                            <div className="text-sm text-rose-700">{form.errors.token}</div>
                        )}
                        <div className="flex items-center justify-between">
                            <Link
                                href="/admin"
                                className="text-sm text-slate-600 hover:underline"
                            >
                                Back to admin
                            </Link>
                            <button
                                type="submit"
                                disabled={form.processing || form.data.token.trim().length < 32}
                                data-testid="license-activate-submit"
                                className={SUBMIT_BUTTON}
                            >
                                {form.processing ? 'Verifying…' : 'Activate license'}
                            </button>
                        </div>
                    </form>
                </div>
            </PageContainer>
        </AdminLayout>
    );
}
