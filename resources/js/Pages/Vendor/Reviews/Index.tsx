import { Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface ReviewRow {
    id: number;
    product_name: string | null;
    product_slug: string | null;
    customer_name: string | null;
    rating: number;
    title: string | null;
    body: string | null;
    status: 'pending' | 'approved' | 'rejected';
    is_verified_purchase: boolean;
    created_at: string | null;
}

interface Props {
    reviews: {
        data: ReviewRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
};

export default function VendorReviewsIndex({ reviews }: Props) {
    return (
        <VendorLayout title="Reviews">
            <div className="max-w-6xl mx-auto px-4 py-8">
                <div className="flex items-baseline justify-between mb-6">
                    <p className="text-sm text-slate-500">{reviews.total} review{reviews.total === 1 ? '' : 's'}</p>
                </div>

                {reviews.data.length === 0 ? (
                    <div className="bg-white border border-slate-200 rounded-lg p-12 text-center text-slate-500">
                        No reviews yet. Reviews are submitted by customers after delivery and require admin approval before becoming public.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {reviews.data.map((r) => (
                            <div key={r.id} className="bg-white border border-slate-200 rounded-lg p-4">
                                <div className="flex items-start justify-between gap-3 mb-2">
                                    <div>
                                        <Link href={`/products/${r.product_slug}`} className="font-medium text-slate-900 hover:text-indigo-700">
                                            {r.product_name}
                                        </Link>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            by {r.customer_name ?? 'Anonymous'} · {r.created_at}
                                            {r.is_verified_purchase && <span className="ml-2 text-emerald-700">✓ Verified purchase</span>}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        <span className="text-amber-500 text-sm">{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}</span>
                                        <span className={`text-xs px-2 py-0.5 rounded ${STATUS_COLORS[r.status] ?? ''}`}>{r.status}</span>
                                    </div>
                                </div>
                                {r.title && <p className="font-medium text-slate-800">{r.title}</p>}
                                {r.body && <p className="text-sm text-slate-700 mt-1 whitespace-pre-line">{r.body}</p>}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </VendorLayout>
    );
}
