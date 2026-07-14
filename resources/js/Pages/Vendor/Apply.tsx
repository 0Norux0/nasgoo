import type { ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import Button from '@/Components/forms/Button';

interface Package {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_minor: number;
    currency: string;
    billing_cycle: string;
    max_products: number | null;
    allow_video: boolean;
    allow_3d: boolean;
    allow_services: boolean;
    allow_dropshipping: boolean;
    default_admin_commission_percent: string;
}

interface Props {
    packages: Package[];
}

interface Form {
    business_name: string;
    business_email: string;
    business_phone: string;
    business_type: 'individual' | 'company';
    description: string;
    country: string;
    city: string;
    address: string;
    commercial_license_no: string;
    tax_id: string;
    payout_method: string;
    vendor_package_id: number | string;
    logo: File | null;
    banner: File | null;
    license_document: File | null;
    id_document: File | null;
    agree_terms: boolean;
    [key: string]: string | number | boolean | File | null;
}

export default function VendorApply({ packages }: Props) {
    const { data, setData, post, processing, errors } = useForm<Form>({
        business_name: '',
        business_email: '',
        business_phone: '',
        business_type: 'individual',
        description: '',
        country: 'KW',
        city: '',
        address: '',
        commercial_license_no: '',
        tax_id: '',
        payout_method: '',
        vendor_package_id: packages[0]?.id ?? '',
        logo: null,
        banner: null,
        license_document: null,
        id_document: null,
        agree_terms: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/apply', { forceFormData: true });
    };

    return (
        <VendorLayout title="Become a Vendor">
            <form onSubmit={submit} className="space-y-6">
                <Section title="Business details">
                    <Field label="Business name" error={errors.business_name}>
                        <input value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} required className={inputCls} />
                    </Field>
                    <Field label="Business email" error={errors.business_email}>
                        <input type="email" value={data.business_email} onChange={(e) => setData('business_email', e.target.value)} required className={inputCls} />
                    </Field>
                    <Field label="Business phone" error={errors.business_phone}>
                        <input type="tel" value={data.business_phone} onChange={(e) => setData('business_phone', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Business type" error={errors.business_type}>
                        <select value={data.business_type} onChange={(e) => setData('business_type', e.target.value as 'individual' | 'company')} className={inputCls}>
                            <option value="individual">Individual</option>
                            <option value="company">Company</option>
                        </select>
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Location">
                    <Field label="Country (ISO-2)" error={errors.country}>
                        <input value={data.country} onChange={(e) => setData('country', e.target.value.toUpperCase())} maxLength={2} required className={inputCls} />
                    </Field>
                    <Field label="City" error={errors.city}>
                        <input value={data.city} onChange={(e) => setData('city', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Full address" error={errors.address} className="md:col-span-2">
                        <textarea value={data.address} onChange={(e) => setData('address', e.target.value)} rows={2} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Legal (optional)">
                    <Field label="Commercial license #" error={errors.commercial_license_no}>
                        <input value={data.commercial_license_no} onChange={(e) => setData('commercial_license_no', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Tax / VAT #" error={errors.tax_id}>
                        <input value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Documents (max 5 MB; PDF / JPG / PNG)">
                    <Field label="Logo" error={errors.logo}>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label="Banner" error={errors.banner}>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={(e) => setData('banner', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label="License document" error={errors.license_document}>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setData('license_document', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label="ID document" error={errors.id_document}>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setData('id_document', e.target.files?.[0] ?? null)} />
                    </Field>
                </Section>

                <Section title="Payout">
                    <Field label="Payout method" error={errors.payout_method}>
                        <input value={data.payout_method} onChange={(e) => setData('payout_method', e.target.value)} placeholder="bank_transfer / wallet" className={inputCls} />
                    </Field>
                </Section>

                {/* Package picker */}
                <div className="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 className="text-lg font-semibold text-slate-900 mb-4">Select a starting package</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {packages.map((p) => {
                            const selected = String(data.vendor_package_id) === String(p.id);
                            return (
                                <label
                                    key={p.id}
                                    className={`block border rounded-xl p-4 cursor-pointer transition ${
                                        selected ? 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-slate-900">{p.name}</span>
                                        <input
                                            type="radio"
                                            name="vendor_package_id"
                                            checked={selected}
                                            onChange={() => setData('vendor_package_id', p.id)}
                                            className="text-indigo-600"
                                        />
                                    </div>
                                    <div className="text-xs text-slate-500 mt-1">
                                        {(p.price_minor / 100).toFixed(2)} {p.currency} / {p.billing_cycle}
                                    </div>
                                    <div className="text-xs text-slate-600 mt-2">
                                        Up to {p.max_products ?? '∞'} products · Commission: {p.default_admin_commission_percent}%
                                    </div>
                                    <ul className="text-xs text-slate-500 mt-2 space-y-0.5">
                                        {p.allow_video && <li>✓ Video</li>}
                                        {p.allow_3d && <li>✓ 3D views</li>}
                                        {p.allow_services && <li>✓ Service listings</li>}
                                        {p.allow_dropshipping && <li>✓ Dropshipping</li>}
                                    </ul>
                                </label>
                            );
                        })}
                    </div>
                    {errors.vendor_package_id && <p className="text-sm text-rose-600 mt-2">{errors.vendor_package_id}</p>}
                </div>

                {/* Terms */}
                <label className="flex items-start gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={data.agree_terms}
                        onChange={(e) => setData('agree_terms', e.target.checked)}
                        className="mt-1 rounded border-slate-300 text-indigo-600"
                    />
                    <span>I agree to the marketplace Terms & Conditions and Privacy Policy.</span>
                </label>
                {errors.agree_terms && <p className="text-sm text-rose-600">{errors.agree_terms}</p>}

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Submitting…' : 'Submit application'}
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
