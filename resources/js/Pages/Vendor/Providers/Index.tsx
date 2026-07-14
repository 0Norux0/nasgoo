import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface ProviderRow {
    id: number;
    name: string;
    slug: string;
    specialization: string | null;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    services_count: number;
}

interface VendorProvidersIndexPageProps extends SharedProps {
    providers: { data: ProviderRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}

export default function VendorProvidersIndex() {
    const { props } = usePage<VendorProvidersIndexPageProps>();
    const { providers } = props;
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        name: '', email: '', phone: '', bio: '',
        specialization: '', qualification: '', is_active: true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/vendor/providers', {
            onSuccess: () => { form.reset(); setShowForm(false); },
        });
    };

    return (
        <VendorLayout title="Service Providers">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Service Providers / Staff</h1>
                <button onClick={() => setShowForm(!showForm)}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    {showForm ? 'Cancel' : '+ Add provider'}
                </button>
            </div>

            {showForm && (
                <form onSubmit={submit} className="border rounded p-4 mb-6 bg-gray-50 space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium mb-1">Name</label>
                            <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                                   className="border rounded w-full px-3 py-2" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Specialization</label>
                            <input type="text" value={form.data.specialization}
                                   onChange={e => form.setData('specialization', e.target.value)}
                                   className="border rounded w-full px-3 py-2"
                                   placeholder="e.g. Cardiology, AC repair" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-sm font-medium mb-1">Email</label>
                            <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)}
                                   className="border rounded w-full px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Phone</label>
                            <input type="text" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)}
                                   className="border rounded w-full px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Qualification / Experience</label>
                        <input type="text" value={form.data.qualification}
                               onChange={e => form.setData('qualification', e.target.value)}
                               className="border rounded w-full px-3 py-2"
                               placeholder="e.g. MBBS, MD / 8 years experience" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Bio</label>
                        <textarea value={form.data.bio} onChange={e => form.setData('bio', e.target.value)}
                                  className="border rounded w-full px-3 py-2" rows={2} />
                    </div>
                    <button type="submit" disabled={form.processing}
                            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        {form.processing ? 'Adding…' : 'Add provider'}
                    </button>
                </form>
            )}

            {providers.data.length === 0 ? (
                <div className="border rounded p-8 text-center text-gray-500">
                    No providers yet. Click "+ Add provider" to create one.
                </div>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left text-gray-600">
                            <th className="py-2">Name</th>
                            <th className="py-2">Specialization</th>
                            <th className="py-2">Contact</th>
                            <th className="py-2">Services</th>
                            <th className="py-2">Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {providers.data.map(p => (
                            <tr key={p.id} className="border-b hover:bg-gray-50">
                                <td className="py-3 font-medium">{p.name}</td>
                                <td className="py-3">{p.specialization || <span className="text-gray-400">—</span>}</td>
                                <td className="py-3 text-xs">
                                    {p.email && <div>{p.email}</div>}
                                    {p.phone && <div>{p.phone}</div>}
                                </td>
                                <td className="py-3">{p.services_count}</td>
                                <td className="py-3">
                                    <span className={`px-2 py-1 rounded text-xs ${p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                        {p.is_active ? 'Yes' : 'No'}
                                    </span>
                                </td>
                                <td className="py-3 text-right">
                                    <Link href={`/vendor/providers/${p.id}/availability`}
                                          className="text-blue-600 hover:underline">Availability</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </VendorLayout>
    );
}
