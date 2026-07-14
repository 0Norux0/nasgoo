import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Platform { id: number; name: string; default_currency: string; }

// Phase 6 v7.3 — local function-arg type; renamed from "PageProps" to avoid
// shadowing the augmented global type from @inertiajs/core. (Not used with
// usePage<>, so no SharedProps extension needed.)
interface CsvImportPageProps {
    platforms: Platform[];
    csv_columns: string[];
}

export default function CsvImport({ platforms, csv_columns }: CsvImportPageProps) {
    const { data, setData, post, processing, errors, progress } = useForm<{
        supplier_platform_id: number | '';
        csv: File | null;
        dry_run: boolean;
    }>({
        supplier_platform_id: platforms[0]?.id ?? '',
        csv: null,
        dry_run: true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/supplier-products/csv', { forceFormData: true });
    };

    return (
        <VendorLayout title="CSV import — supplier products">
            <div className="max-w-3xl mx-auto px-4 py-6">
                <p className="text-sm text-slate-500 mb-4">
                    Bulk-import supplier products from a CSV file. Recommended workflow:
                    upload with <strong>Dry run</strong> first to validate; then re-upload with Dry run off to commit.
                </p>

                <form onSubmit={submit} className="bg-white border border-slate-200 rounded-lg p-4 space-y-3">
                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">Supplier platform</span>
                        <select value={data.supplier_platform_id}
                            onChange={(e) => setData('supplier_platform_id', Number(e.target.value))}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                            {platforms.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                        {errors.supplier_platform_id && <span className="text-xs text-rose-600">{errors.supplier_platform_id}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">CSV file (max 2MB)</span>
                        <input type="file" accept=".csv,text/csv"
                            onChange={(e) => setData('csv', e.target.files?.[0] ?? null)}
                            className="text-sm" />
                        {errors.csv && <span className="text-xs text-rose-600 block">{errors.csv}</span>}
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.dry_run}
                            onChange={(e) => setData('dry_run', e.target.checked)} />
                        <span>Dry run (validate only — do not save supplier products)</span>
                    </label>

                    {progress && (
                        <div className="text-xs text-slate-500">Uploading: {progress.percentage}%</div>
                    )}

                    <button type="submit" disabled={processing || !data.csv}
                        className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                        {processing ? 'Uploading…' : data.dry_run ? 'Validate (dry run)' : 'Import'}
                    </button>
                </form>

                <div className="mt-6 bg-slate-50 border border-slate-200 rounded p-4 text-sm text-slate-700">
                    <h3 className="font-medium mb-2">Expected CSV columns</h3>
                    <ul className="grid grid-cols-2 sm:grid-cols-3 gap-x-2 gap-y-1 text-xs">
                        {csv_columns.map((c) => (
                            <li key={c} className="font-mono">
                                {c}
                                {(c === 'title' || c === 'supplier_cost') && <span className="text-rose-600">*</span>}
                            </li>
                        ))}
                    </ul>
                    <p className="text-xs text-slate-500 mt-2"><span className="text-rose-600">*</span> required. Header row must be the first line.</p>
                    <p className="text-xs text-slate-500 mt-1">image_url accepts a single URL or multiple separated by <code>|</code> or <code>,</code>.</p>
                </div>
            </div>
        </VendorLayout>
    );
}
