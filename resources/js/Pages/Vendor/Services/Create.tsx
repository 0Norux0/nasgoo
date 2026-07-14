import { useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface VendorServicesCreatePageProps extends SharedProps {
    service_types: string[];
    location_modes: string[];
}

export default function VendorServicesCreate() {
    const { props } = usePage<VendorServicesCreatePageProps>();
    const { service_types, location_modes } = props;

    const form = useForm({
        name: '', description: '',
        price: '', currency: 'KWD',
        service_type: service_types[0] ?? 'appointment',
        location_mode: location_modes[0] ?? 'provider_location',
        duration_minutes: 30,
        service_area_text: '',
        min_lead_time_minutes: 60,
        max_advance_days: 30,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/vendor/services');
    };

    return (
        <VendorLayout title="New Service">
            <h1 className="text-2xl font-bold mb-6">New Service</h1>

            <form onSubmit={submit} className="max-w-2xl space-y-4">
                <div>
                    <label className="block text-sm font-medium mb-1">Service name</label>
                    <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                           className="border rounded w-full px-3 py-2" required />
                    {form.errors.name && <p className="text-red-600 text-sm">{form.errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Description</label>
                    <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                              className="border rounded w-full px-3 py-2" rows={4} />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium mb-1">Price</label>
                        <input type="number" step="0.01" value={form.data.price}
                               onChange={e => form.setData('price', e.target.value)}
                               className="border rounded w-full px-3 py-2" required />
                        {form.errors.price && <p className="text-red-600 text-sm">{form.errors.price}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Currency</label>
                        <select value={form.data.currency} onChange={e => form.setData('currency', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            <option value="KWD">KWD</option>
                            <option value="USD">USD</option>
                            <option value="AED">AED</option>
                            <option value="PKR">PKR</option>
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium mb-1">Service type</label>
                        <select value={form.data.service_type} onChange={e => form.setData('service_type', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            {service_types.map(t => <option key={t} value={t}>{t.replace('_', ' ')}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Location mode</label>
                        <select value={form.data.location_mode} onChange={e => form.setData('location_mode', e.target.value)}
                                className="border rounded w-full px-3 py-2">
                            {location_modes.map(m => <option key={m} value={m}>{m.replace('_', ' ')}</option>)}
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="block text-sm font-medium mb-1">Duration (min)</label>
                        <input type="number" value={form.data.duration_minutes}
                               onChange={e => form.setData('duration_minutes', parseInt(e.target.value, 10) || 30)}
                               className="border rounded w-full px-3 py-2" required min={5} max={1440} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Min lead time (min)</label>
                        <input type="number" value={form.data.min_lead_time_minutes}
                               onChange={e => form.setData('min_lead_time_minutes', parseInt(e.target.value, 10) || 0)}
                               className="border rounded w-full px-3 py-2" min={0} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Max advance (days)</label>
                        <input type="number" value={form.data.max_advance_days}
                               onChange={e => form.setData('max_advance_days', parseInt(e.target.value, 10) || 30)}
                               className="border rounded w-full px-3 py-2" min={1} max={365} />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium mb-1">Service area / cities (optional)</label>
                    <input type="text" value={form.data.service_area_text}
                           onChange={e => form.setData('service_area_text', e.target.value)}
                           placeholder="e.g. Kuwait City, Salmiya"
                           className="border rounded w-full px-3 py-2" />
                </div>

                <button type="submit" disabled={form.processing}
                        className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Creating…' : 'Create service'}
                </button>
            </form>
        </VendorLayout>
    );
}
