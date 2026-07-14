import { Link, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { SharedProps } from '@/types/inertia';
import { useT } from '@/lib/i18n';
import Container from '@/Components/Layout/Container';

interface ServiceCard {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    currency: string;
    duration_min: number | null;
    service_type: string | null;
    location_mode: string | null;
    service_area: string | null;
    vendor: { id: number; name: string; slug: string } | null;
    providers: string[];
}

interface ServicesIndexPageProps extends SharedProps {
    filters: { service_type?: string; location_mode?: string; area?: string; q?: string };
    services: { data: ServiceCard[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    service_types: string[];
    location_modes: string[];
}

export default function ServicesIndex() {
    const { props } = usePage<ServicesIndexPageProps>();
    const { services, filters, service_types, location_modes } = props;
    const t = useT();

    return (
        <StorefrontLayout>
            <Container className="py-6 lg:py-10">
                <h1 className="text-2xl font-bold mb-6">{t('nav.services')}</h1>

                {/* Filters */}
                <form method="get" className="mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded">
                    <input type="text" name="q" defaultValue={filters.q ?? ''}
                           placeholder={t('catalog.search_placeholder')} className="border rounded px-3 py-2" />
                    <select name="service_type" defaultValue={filters.service_type ?? ''} className="border rounded px-3 py-2">
                        <option value="">{t('common.no')}—{t('catalog.filter_by')}</option>
                        {service_types.map(t => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                    </select>
                    <select name="location_mode" defaultValue={filters.location_mode ?? ''} className="border rounded px-3 py-2">
                        <option value="">{t('common.no')}—{t('service.location')}</option>
                        {location_modes.map(m => <option key={m} value={m}>{m.replace('_', ' ')}</option>)}
                    </select>
                    <input type="text" name="area" defaultValue={filters.area ?? ''}
                           placeholder={t('service.location')} className="border rounded px-3 py-2" />
                    <button type="submit" className="sm:col-span-4 bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                        Apply filters
                    </button>
                </form>

                {services.data.length === 0 ? (
                    <p className="text-slate-600">{t('catalog.no_products')}</p>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {services.data.map(svc => (
                            <Link key={svc.id} href={`/services/${svc.slug}`}
                                  className="block border rounded-lg p-4 hover:shadow-lg transition">
                                <h2 className="font-semibold text-lg">{svc.name}</h2>
                                {svc.vendor && <p className="text-sm text-slate-700">by {svc.vendor.name}</p>}
                                {svc.description && <p className="text-sm text-slate-700 mt-2">{svc.description}</p>}
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="font-semibold">{svc.price} {svc.currency}</span>
                                    {svc.duration_min && <span className="text-xs text-slate-600">{svc.duration_min} min</span>}
                                </div>
                                <div className="mt-2 flex flex-wrap gap-1 text-xs">
                                    {svc.service_type && <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded">
                                        {svc.service_type.replace('_', ' ')}
                                    </span>}
                                    {svc.location_mode && <span className="px-2 py-1 bg-purple-100 text-purple-700 rounded">
                                        {svc.location_mode.replace('_', ' ')}
                                    </span>}
                                    {svc.service_area && <span className="px-2 py-1 bg-slate-100 text-slate-700 rounded">
                                        {svc.service_area}
                                    </span>}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                <div className="mt-6 flex gap-2 flex-wrap">
                    {services.links.map((link, i) => link.url ? (
                        <Link key={i} href={link.url}
                              className={`px-3 py-1 border rounded text-sm ${link.active ? 'bg-blue-600 text-white' : ''}`}
                              dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                        <span key={i} className="px-3 py-1 border rounded text-sm text-slate-500"
                              dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            </Container>
        </StorefrontLayout>
    );
}
