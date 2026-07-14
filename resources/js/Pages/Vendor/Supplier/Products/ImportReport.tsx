import { Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface RowError { row: number; errors: string[]; }
interface Batch {
    id: number;
    original_filename: string | null;
    platform: string | null;
    status: string;
    dry_run: boolean;
    total_rows: number;
    successful_rows: number;
    failed_rows: number;
    errors: RowError[];
    processed_at: string | null;
}

export default function ImportReport({ batch }: { batch: Batch }) {
    return (
        <VendorLayout title="CSV import report">
            <div className="max-w-4xl mx-auto px-4 py-6">
                <div className="bg-white border border-slate-200 rounded p-4 mb-4">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <div className="font-medium text-slate-900">{batch.original_filename ?? 'unnamed.csv'}</div>
                            <div className="text-xs text-slate-500">
                                Platform: {batch.platform ?? '—'} · Processed: {batch.processed_at ?? '—'}
                            </div>
                        </div>
                        {batch.dry_run && <span className="bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded">DRY RUN</span>}
                    </div>
                    <div className="grid grid-cols-3 gap-3 mt-3 text-sm">
                        <div><span className="text-slate-500">Total rows:</span> <strong>{batch.total_rows}</strong></div>
                        <div><span className="text-emerald-600">Succeeded:</span> <strong>{batch.successful_rows}</strong></div>
                        <div><span className="text-rose-600">Failed:</span> <strong>{batch.failed_rows}</strong></div>
                    </div>
                </div>

                {batch.errors.length > 0 && (
                    <div className="bg-white border border-rose-200 rounded p-4 mb-4">
                        <h3 className="font-medium text-rose-800 mb-2">Row errors</h3>
                        <ul className="text-sm space-y-1">
                            {batch.errors.map((e, i) => (
                                <li key={i} className="border-b border-rose-50 last:border-0 py-1">
                                    <strong>Row {e.row}:</strong>
                                    <ul className="ml-4 list-disc text-rose-700">
                                        {e.errors.map((msg, j) => <li key={j}>{msg}</li>)}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex gap-2">
                    <Link href="/vendor/supplier-products"
                        className="text-sm text-indigo-600 hover:underline">← Back to supplier products</Link>
                    {batch.dry_run && (
                        <Link href="/vendor/supplier-products/csv"
                            className="text-sm text-indigo-600 hover:underline">Re-run without dry run</Link>
                    )}
                </div>
            </div>
        </VendorLayout>
    );
}
