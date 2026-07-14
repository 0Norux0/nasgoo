import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface ServiceData {
    id: number;
    name: string;
    description: string | null;
    price: string;
    currency: string;
    status: string;
    service_type: string;
    location_mode: string;
    duration_min: number;
    service_area: string | null;
    is_active: boolean;
    assigned_providers: number[];
}

interface VendorServicesEditPageProps extends SharedProps {
    service: ServiceData;
    all_providers: Array<{ id: number; name: string }>;
    service_types: string[];
    location_modes: string[];
}

export default function VendorServicesEdit() {
    const { props } = usePage<VendorServicesEditPageProps>();
    const { service, all_providers, service_types, location_modes } = props;

    const form = useForm({
        name: service.name,
        description: service.description ?? '',
        price: service.price,
        status: service.status,
        service_type: service.service_type,
        location_mode: service.location_mode,
        duration_minutes: service.duration_min,
        service_area_text: service.service_area ?? '',
        is_active: service.is_active,
        provider_ids: service.assigned_providers,
    });

    const toggleProvider = (id: number) => {
        const ids = form.data.provider_ids.includes(id)
            ? form.data.provider_ids.filter(x => x !== id)
            : [...form.data.provider_ids, id];
        form.setData('provider_ids', ids);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(`/vendor/services/${service.id}`);
    };

    return (
        <VendorLayout title={`Edit: ${service.name}`}>
            <Link href="/vendor/services" className="text-sm text-blue-600 hover:underline">&larr; My services</Link>
            <h1 className="text-2xl font-bold mt-2 mb-6">Edit service</h1>

            <form onSubmit={submit} className="max-w-2xl space-y-4">
                <div>
                    <label className="block text-sm font-medium mb-1">Name</label>
                    <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                           className="border rounded w-full px-3 py-2" required />
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Description</label>
                    <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                              className="border rounded w-full px-3 py-2" rows={4} />
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="block text-sm font-medium mb-1">Price</label>
                        <input type="number" step="0.01" value={form.data.price}
                               onChange={e => form.setData('price', e.target.value)}
                               className="border rounded w-full px-3 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Status</label>
                        <select value={form.data.status} onChange={e => form.setData('status', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Active</label>
                        <select value={form.data.is_active ? '1' : '0'}
                                onChange={e => form.setData('is_active', e.target.value === '1')}
                                className="border rounded w-full px-3 py-2">
                            <option value="1">Yes (accepting bookings)</option>
                            <option value="0">No (paused)</option>
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="block text-sm font-medium mb-1">Type</label>
                        <select value={form.data.service_type} onChange={e => form.setData('service_type', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            {service_types.map(t => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Location</label>
                        <select value={form.data.location_mode} onChange={e => form.setData('location_mode', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            {location_modes.map(m => <option key={m} value={m}>{m.replace('_', ' ')}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Duration (min)</label>
                        <input type="number" value={form.data.duration_minutes}
                               onChange={e => form.setData('duration_minutes', parseInt(e.target.value, 10) || 30)}
                               className="border rounded w-full px-3 py-2" min={5} max={1440} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Service area / cities</label>
                    <input type="text" value={form.data.service_area_text}
                           onChange={e => form.setData('service_area_text', e.target.value)}
                           className="border rounded w-full px-3 py-2" />
                </div>

                <div>
                    <label className="block text-sm font-medium mb-2">Assigned providers</label>
                    {all_providers.length === 0 ? (
                        <p className="text-sm text-gray-500">No providers yet.&nbsp;
                            <Link href="/vendor/providers" className="text-blue-600 hover:underline">Add staff first.</Link>
                        </p>
                    ) : (
                        <div className="space-y-1">
                            {all_providers.map(p => (
                                <label key={p.id} className="flex items-center gap-2">
                                    <input type="checkbox" checked={form.data.provider_ids.includes(p.id)}
                                           onChange={() => toggleProvider(p.id)} />
                                    <span>{p.name}</span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>

                <button type="submit" disabled={form.processing}
                        className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Saving…' : 'Save changes'}
                </button>
            </form>
        </VendorLayout>
    );
}
