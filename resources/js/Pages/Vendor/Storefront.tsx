import { Head, Link, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types/inertia';

interface StorefrontProduct {
    slug: string;
    name: string;
    price: string;
    currency: string;
    thumb: string | null;
    category: string | null;
    featured: boolean;
}

interface Props {
    vendor: {
        business_name: string;
        slug: string;
        description: string | null;
        logo_path: string | null;
        banner_path: string | null;
        country: string;
        city: string | null;
        rating_avg: number;
        rating_count: number;
        sales_count: number;
        featured: boolean;
        created_at: string | null;
    };
    products: StorefrontProduct[];
}

export default function VendorStorefront({ vendor, products }: Props) {
    const { app, auth } = usePage<SharedProps>().props;
    const user = auth.user;

    return (
        <>
            <Head title={vendor.business_name} />
            <div className="min-h-screen bg-slate-50" dir={app.direction}>
                {/* Top bar */}
                <header className="bg-white border-b border-slate-200">
                    <div className="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between text-sm">
                        <Link href="/" className="font-semibold text-slate-900">{app.name}</Link>
                        <nav className="flex items-center gap-4">
                            {user ? (
                                <>
                                    <span className="text-slate-500">{user.name}</span>
                                    {user.is_admin && <Link href="/admin" className="text-indigo-600 hover:underline">Admin</Link>}
                                    {user.roles.includes('vendor') && <Link href="/vendor" className="text-indigo-600 hover:underline">My vendor</Link>}
                                    <Link href="/logout" method="post" as="button" className="text-rose-600">Logout</Link>
                                </>
                            ) : (
                                <>
                                    <Link href="/login" className="text-slate-600 hover:text-slate-900">Sign in</Link>
                                    <Link href="/register" className="text-indigo-600 hover:underline">Register</Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Banner */}
                <div className="h-44 bg-gradient-to-r from-indigo-500 to-fuchsia-500 relative">
                    {vendor.banner_path && (
                        <div className="absolute inset-0 bg-black/30 flex items-center justify-center text-white/60 text-xs">
                            [banner: {vendor.banner_path}]
                        </div>
                    )}
                </div>

                {/* Header card */}
                <div className="max-w-5xl mx-auto px-4 -mt-12 relative z-10">
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex items-center gap-4">
                        <div className="w-20 h-20 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-2xl font-bold text-slate-400">
                            {vendor.logo_path ? '🛍️' : vendor.business_name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-slate-900">{vendor.business_name}</h1>
                                {vendor.featured && (
                                    <span className="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full">Featured</span>
                                )}
                            </div>
                            <div className="text-sm text-slate-500 mt-1">
                                {[vendor.city, vendor.country].filter(Boolean).join(', ')}
                                {vendor.created_at && <> · Member since {vendor.created_at}</>}
                            </div>
                            <div className="text-sm text-slate-600 mt-1">
                                ⭐ {vendor.rating_avg.toFixed(1)} <span className="text-slate-400">({vendor.rating_count} reviews)</span>
                                {' · '}
                                {vendor.sales_count} sales
                            </div>
                        </div>
                    </div>
                </div>

                {/* Description */}
                {vendor.description && (
                    <div className="max-w-5xl mx-auto px-4 mt-6">
                        <div className="bg-white border border-slate-200 rounded-xl p-5">
                            <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-2">About</h2>
                            <p className="text-sm text-slate-700 whitespace-pre-line">{vendor.description}</p>
                        </div>
                    </div>
                )}

                {/* Products */}
                <div className="max-w-5xl mx-auto px-4 mt-6 mb-12">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">Products</h2>
                    {products.length === 0 ? (
                        <div className="bg-white border-2 border-dashed border-slate-200 rounded-xl p-8 text-center text-slate-400 text-sm">
                            This vendor hasn't published any products yet.
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {products.map((p) => (
                                <Link
                                    key={p.slug}
                                    href={`/products/${p.slug}`}
                                    className="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-indigo-300 hover:shadow-sm transition"
                                >
                                    <div className="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                                        {p.thumb ? (
                                            <img
                                                src={p.thumb}
                                                alt={p.name}
                                                loading="lazy"
                                                className="w-full h-full object-cover"
                                                onError={(e) => {
                                                    const el = e.currentTarget;
                                                    el.style.display = 'none';
                                                    el.nextElementSibling?.classList.remove('hidden');
                                                }}
                                            />
                                        ) : null}
                                        <span className={`text-3xl text-slate-300 ${p.thumb ? 'hidden' : ''}`}>🛍️</span>
                                    </div>
                                    <div className="p-3">
                                        {p.featured && (
                                            <span className="inline-block mb-1 px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[10px] rounded font-medium">
                                                Featured
                                            </span>
                                        )}
                                        <div className="text-sm font-medium text-slate-900 line-clamp-2 min-h-[2.5rem]">{p.name}</div>
                                        <div className="text-sm font-semibold text-slate-900 mt-1">{p.price} {p.currency}</div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
