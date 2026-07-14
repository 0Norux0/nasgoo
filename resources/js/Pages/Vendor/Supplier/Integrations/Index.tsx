import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface Integration {
    id: number;
    name: string;
    platform: string | null;
    integration_type: string;
    is_active: boolean;
    last_synced_at: string | null;
    last_sync_status: string | null;
    masked_credentials: Record<string, string>;
}

interface Platform { id: number; name: string; slug: string; integration_type: string; }

// Phase 6 v7.3 — must extend SharedProps to satisfy Inertia v2's
// `usePage<T extends PageProps>` constraint (PageProps is augmented to
// extend SharedProps in resources/js/types/inertia.d.ts).
type SupplierIntegrationsPageProps = SharedProps & {
    integrations: Integration[];
    platforms: Platform[];
};

export default function Index() {
    const { props } = usePage<SupplierIntegrationsPageProps>();
    const { integrations, platforms, flash } = props;
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<{
        supplier_platform_id: number | '';
        name: string;
        integration_type: 'manual' | 'csv' | 'api' | 'feed';
        feed_url: string;
        is_active: boolean;
        credentials: Record<string, string>;
    }>({
        supplier_platform_id: platforms[0]?.id ?? '',
        name: '',
        integration_type: 'manual',
        feed_url: '',
        is_active: true,
        credentials: { api_key: '', api_secret: '' },
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/supplier-integrations', {
            onSuccess: () => { reset(); setShowForm(false); },
        });
    };

    return (
        <VendorLayout title="Supplier Integrations">
            <div className="max-w-4xl mx-auto px-4 py-6">
                {flash?.success && <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{flash.success}</div>}
                {flash?.error && <div className="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded text-sm">{flash.error}</div>}

                <div className="flex items-center justify-between mb-4">
                    <p className="text-sm text-slate-500">
                        Per-platform integrations for your supplier products. Credentials are stored encrypted at rest and never displayed in plaintext.
                    </p>
                    {!showForm && (
                        <button onClick={() => setShowForm(true)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded">
                            + New integration
                        </button>
                    )}
                </div>

                {showForm && (
                    <form onSubmit={submit} className="bg-white border border-slate-200 rounded p-4 mb-4 space-y-3">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Platform</span>
                                <select value={data.supplier_platform_id}
                                    onChange={(e) => setData('supplier_platform_id', Number(e.target.value))}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                    {platforms.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Integration name</span>
                                <input value={data.name} onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. AliExpress catalogue Q2"
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                {errors.name && <span className="text-xs text-rose-600">{errors.name}</span>}
                            </label>
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Integration type</span>
                                <select value={data.integration_type}
                                    onChange={(e) => setData('integration_type', e.target.value as typeof data.integration_type)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                    <option value="manual">Manual</option>
                                    <option value="csv">CSV import</option>
                                    <option value="api">API-ready</option>
                                    <option value="feed">Affiliate/feed</option>
                                </select>
                            </label>
                            <label className="block">
                                <span className="text-sm text-slate-700 block mb-1">Feed URL (optional)</span>
                                <input value={data.feed_url} onChange={(e) => setData('feed_url', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </label>
                        </div>

                        {(data.integration_type === 'api' || data.integration_type === 'feed') && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded p-3">
                                <label className="block">
                                    <span className="text-sm text-slate-700 block mb-1">API key</span>
                                    <input type="password" value={data.credentials.api_key}
                                        onChange={(e) => setData('credentials', { ...data.credentials, api_key: e.target.value })}
                                        className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                </label>
                                <label className="block">
                                    <span className="text-sm text-slate-700 block mb-1">API secret</span>
                                    <input type="password" value={data.credentials.api_secret}
                                        onChange={(e) => setData('credentials', { ...data.credentials, api_secret: e.target.value })}
                                        className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                </label>
                                <p className="text-xs text-slate-500 sm:col-span-2">Stored encrypted at rest. Only the last 4 characters are displayed on subsequent views.</p>
                            </div>
                        )}

                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)} />
                            <span>Active</span>
                        </label>

                        <div className="flex gap-2">
                            <button type="submit" disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                                {processing ? 'Saving…' : 'Create integration'}
                            </button>
                            <button type="button" onClick={() => { reset(); setShowForm(false); }}
                                className="text-sm text-slate-600 px-3 py-1.5">Cancel</button>
                        </div>
                    </form>
                )}

                <div className="bg-white border border-slate-200 rounded">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-700 text-xs uppercase">
                            <tr>
                                <th className="text-left px-3 py-2">Name</th>
                                <th className="text-left px-3 py-2">Platform</th>
                                <th className="text-left px-3 py-2">Type</th>
                                <th className="text-left px-3 py-2">Credentials</th>
                                <th className="text-left px-3 py-2">Last sync</th>
                                <th className="text-left px-3 py-2">Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            {integrations.length === 0 ? (
                                <tr><td colSpan={6} className="text-center text-slate-500 py-6">No integrations yet.</td></tr>
                            ) : integrations.map((i) => (
                                <tr key={i.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2 text-slate-900">{i.name}</td>
                                    <td className="px-3 py-2 text-slate-600">{i.platform}</td>
                                    <td className="px-3 py-2"><span className="text-xs bg-slate-100 rounded px-1.5 py-0.5">{i.integration_type}</span></td>
                                    <td className="px-3 py-2 text-xs text-slate-500 font-mono">
                                        {Object.keys(i.masked_credentials).length === 0 ? '—' :
                                            Object.entries(i.masked_credentials).map(([k, v]) => (
                                                <div key={k}>{k}: {v}</div>
                                            ))}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">
                                        {i.last_synced_at ?? 'never'}
                                        {i.last_sync_status && <span className="ml-1 px-1.5 py-0.5 bg-slate-100 rounded">{i.last_sync_status}</span>}
                                    </td>
                                    <td className="px-3 py-2">{i.is_active ? '✓' : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </VendorLayout>
    );
}
