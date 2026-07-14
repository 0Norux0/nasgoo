import type { ReactNode } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import Button from '@/Components/forms/Button';
import type { SharedProps } from '@/types/inertia';

interface VendorProfile {
    id: number;
    business_name: string;
    business_email: string;
    business_phone: string | null;
    description: string | null;
    country: string;
    city: string | null;
    address: string | null;
    logo_path: string | null;
    banner_path: string | null;
    payout_method: string | null;
}

interface Props {
    vendor: VendorProfile;
}

interface ProfileForm {
    business_name: string;
    business_phone: string;
    description: string;
    country: string;
    city: string;
    address: string;
    payout_method: string;
    payout_details: Record<string, string> | null;
    logo: File | null;
    banner: File | null;
    [key: string]: string | File | Record<string, string> | null;
}

export default function VendorProfile({ vendor }: Props) {
    const { flash } = usePage<SharedProps>().props;

    const { data, setData, post, processing, errors } = useForm<ProfileForm>({
        business_name:  vendor.business_name ?? '',
        business_phone: vendor.business_phone ?? '',
        description:    vendor.description ?? '',
        country:        vendor.country ?? 'KW',
        city:           vendor.city ?? '',
        address:        vendor.address ?? '',
        payout_method:  vendor.payout_method ?? '',
        payout_details: null,
        logo:           null,
        banner:         null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/profile', { forceFormData: true });
    };

    return (
        <VendorLayout title="Edit Profile">
            {flash.success && (
                <div className="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <Section title="Business">
                    <Field label="Business name" error={errors.business_name}>
                        <input value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} required className={inputCls} />
                    </Field>
                    <Field label="Business phone" error={errors.business_phone}>
                        <input type="tel" value={data.business_phone} onChange={(e) => setData('business_phone', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={4} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Location">
                    <Field label="Country (ISO-2)" error={errors.country}>
                        <input value={data.country} onChange={(e) => setData('country', e.target.value.toUpperCase())} maxLength={2} required className={inputCls} />
                    </Field>
                    <Field label="City" error={errors.city}>
                        <input value={data.city} onChange={(e) => setData('city', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Address" error={errors.address} className="md:col-span-2">
                        <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Media">
                    <Field label="Logo" error={errors.logo}>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} />
                        {vendor.logo_path && (
                            <p className="text-xs text-slate-500 mt-1">Current: {vendor.logo_path}</p>
                        )}
                    </Field>
                    <Field label="Banner" error={errors.banner}>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(e) => setData('banner', e.target.files?.[0] ?? null)} />
                        {vendor.banner_path && (
                            <p className="text-xs text-slate-500 mt-1">Current: {vendor.banner_path}</p>
                        )}
                    </Field>
                </Section>

                <Section title="Payout">
                    <Field label="Payout method" error={errors.payout_method}>
                        <input
                            value={data.payout_method}
                            onChange={(e) => setData('payout_method', e.target.value)}
                            placeholder="bank_transfer / wallet"
                            className={inputCls}
                        />
                    </Field>
                    <div className="md:col-span-2 text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded p-3">
                        🔒 Payout details are stored encrypted at rest. Updating them creates an audit log entry.
                    </div>
                </Section>

                <div className="flex justify-end gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </form>
        </VendorLayout>
    );
}

const inputCls = 'w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border';

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-5">
            <h2 className="text-lg font-semibold text-slate-900 mb-4">{title}</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">{children}</div>
        </div>
    );
}

function Field({ label, error, children, className = '' }: { label: string; error?: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-sm text-rose-600">{error}</p>}
        </div>
    );
}
