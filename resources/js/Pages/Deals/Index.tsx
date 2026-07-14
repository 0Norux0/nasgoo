import { Head, Link } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface Promotion {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    promotion_type: string;
    discount_type: string;
    discount_value: number;
    ends_at: string | null;
    sample_products: Array<{ id: number; slug: string; name: string; price_minor: number; currency: string }>;
}

interface Props { promotions: Promotion[] }

export default function DealsIndex({ promotions }: Props) {
    return (
        <StorefrontLayout>
            <Head title="Deals & Promotions" />
            <div className="max-w-6xl mx-auto p-6">
                <h1 className="text-3xl font-bold mb-6">Deals & Promotions</h1>
                {promotions.length === 0 ? (
                    <p className="text-slate-500">No active promotions right now. Check back soon!</p>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {promotions.map((p) => (
                            <div key={p.id} className="border rounded-lg p-6 bg-white shadow-sm" data-testid="deal-card">
                                <div className="flex justify-between items-start mb-2">
                                    <h2 className="text-xl font-semibold">{p.title}</h2>
                                    <span className="bg-rose-100 text-rose-700 text-xs font-semibold px-2 py-1 rounded">
                                        {p.discount_type === 'percentage'
                                            ? `${p.discount_value}% OFF`
                                            : p.discount_type === 'free_shipping'
                                            ? 'FREE SHIPPING'
                                            : `${(p.discount_value / 1000).toFixed(3)} KWD OFF`}
                                    </span>
                                </div>
                                {p.description && <p className="text-slate-600 text-sm mb-3">{p.description}</p>}
                                {p.ends_at && (
                                    <p className="text-xs text-slate-400 mb-3">
                                        Ends: {new Date(p.ends_at).toLocaleDateString()}
                                    </p>
                                )}
                                {p.sample_products.length > 0 && (
                                    <div className="border-t pt-3 mt-3">
                                        <p className="text-xs uppercase text-slate-500 mb-2">Featured products</p>
                                        <ul className="space-y-1">
                                            {p.sample_products.map((sp) => (
                                                <li key={sp.id} className="text-sm">
                                                    <Link href={`/products/${sp.slug}`} className="text-indigo-600 hover:underline">
                                                        {sp.name}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
