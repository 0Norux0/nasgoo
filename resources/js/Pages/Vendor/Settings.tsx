import { useForm, usePage } from '@inertiajs/react';
import { CreditCard, FileText, Bell } from 'lucide-react';
import VendorLayout from '@/Layouts/VendorLayout';
import { PageContainer, PageHeader } from '@/Components/Layout/PageContainer';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';
import type { FormEvent } from 'react';

interface VendorProfile {
    id: number;
    business_name: string;
    business_email: string;
    business_type: string;
    country: string;
    status: string;
    description: string;
    phone: string;
    address: string;
    logo_url: string | null;
    website: string;
}

interface Props extends SharedProps {
    vendor: VendorProfile;
    features: {
        payouts_configured: boolean;
        documents_uploaded: boolean;
        notifications_ready: boolean;
    };
}

/**
 * Phase 11B.3 v11B.3.2 §20 — Vendor Settings page.
 *
 * Editable in v11B.3.2: business_name, business_email, description, phone,
 * address, website. Anything sensitive (status, package_id, verification)
 * stays admin-managed.
 *
 * "Coming soon" placeholders (dev §20) with clear labels:
 *   - Payout details
 *   - Documents
 *   - Notification preferences
 */
export default function VendorSettingsPage() {
    const { vendor, features } = usePage<Props>().props;
    const t = useT();

    const { data, setData, patch, processing, errors } = useForm({
        business_name:  vendor.business_name,
        business_email: vendor.business_email,
        description:    vendor.description,
        phone:          vendor.phone,
        address:        vendor.address,
        website:        vendor.website,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        patch('/vendor/settings');
    };

    return (
        <VendorLayout title={t('vendor.settings.title', 'Vendor settings')}>
            <PageContainer>
                <PageHeader
                    title={t('vendor.settings.title', 'Vendor settings')}
                    description={t('vendor.settings.subtitle', 'Manage your store profile, contact information, and preferences.')}
                    testId="vendor-settings-title"
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                    {/* Store profile — editable */}
                    <form
                        onSubmit={handleSubmit}
                        className="lg:col-span-2 bg-white border border-slate-200 rounded-xl p-4 sm:p-6 space-y-4"
                        data-testid="vendor-settings-form"
                    >
                        <h2 className="text-base font-semibold text-slate-900">
                            {t('vendor.settings.store_profile', 'Store profile')}
                        </h2>

                        <Field
                            label={t('vendor.settings.business_name', 'Business name')}
                            error={errors.business_name}
                        >
                            <input
                                type="text"
                                value={data.business_name}
                                onChange={(e) => setData('business_name', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                data-testid="vendor-settings-business-name"
                                required
                            />
                        </Field>

                        <Field
                            label={t('vendor.settings.business_email', 'Business email')}
                            error={errors.business_email}
                        >
                            <input
                                type="email"
                                value={data.business_email}
                                onChange={(e) => setData('business_email', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                data-testid="vendor-settings-business-email"
                                required
                            />
                        </Field>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field
                                label={t('vendor.settings.phone', 'Phone')}
                                error={errors.phone}
                            >
                                <input
                                    type="tel"
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                    data-testid="vendor-settings-phone"
                                />
                            </Field>

                            <Field
                                label={t('vendor.settings.website', 'Website')}
                                error={errors.website}
                            >
                                <input
                                    type="url"
                                    placeholder="https://"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                    data-testid="vendor-settings-website"
                                />
                            </Field>
                        </div>

                        <Field
                            label={t('vendor.settings.address', 'Address')}
                            error={errors.address}
                        >
                            <input
                                type="text"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                data-testid="vendor-settings-address"
                            />
                        </Field>

                        <Field
                            label={t('vendor.settings.description', 'Store description')}
                            error={errors.description}
                        >
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={4}
                                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                                data-testid="vendor-settings-description"
                            />
                        </Field>

                        <div className="flex justify-end pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-md disabled:opacity-50"
                                data-testid="vendor-settings-save"
                            >
                                {processing
                                    ? t('common.saving', 'Saving…')
                                    : t('common.save', 'Save changes')}
                            </button>
                        </div>
                    </form>

                    {/* Status + placeholders for upcoming sections */}
                    <div className="space-y-4">
                        <div className="bg-white border border-slate-200 rounded-xl p-4">
                            <h3 className="text-sm font-semibold text-slate-900 mb-2">
                                {t('vendor.settings.account_status', 'Account status')}
                            </h3>
                            <p className="text-xs text-slate-600 mb-1">
                                <span className="font-medium capitalize">{vendor.status}</span>
                            </p>
                            <p className="text-xs text-slate-500">
                                {t('vendor.settings.status_admin_managed', 'Status is managed by the marketplace admin.')}
                            </p>
                        </div>

                        <PlaceholderCard
                            icon={<CreditCard size={16} aria-hidden="true" />}
                            title={t('vendor.settings.payouts', 'Payout details')}
                            configured={features.payouts_configured}
                            testId="vendor-settings-payouts-placeholder"
                        />
                        <PlaceholderCard
                            icon={<FileText size={16} aria-hidden="true" />}
                            title={t('vendor.settings.documents', 'Business documents')}
                            configured={features.documents_uploaded}
                            testId="vendor-settings-documents-placeholder"
                        />
                        <PlaceholderCard
                            icon={<Bell size={16} aria-hidden="true" />}
                            title={t('vendor.settings.notifications', 'Notification preferences')}
                            configured={features.notifications_ready}
                            testId="vendor-settings-notifications-placeholder"
                        />
                    </div>
                </div>
            </PageContainer>
        </VendorLayout>
    );
}

function Field({
    label, error, children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">
                {label}
            </label>
            {children}
            {error && (
                <p className="text-xs text-rose-600 mt-1" role="alert">{error}</p>
            )}
        </div>
    );
}

function PlaceholderCard({
    icon, title, configured, testId,
}: {
    icon: React.ReactNode;
    title: string;
    configured: boolean;
    testId?: string;
}) {
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-4" data-testid={testId}>
            <div className="flex items-center gap-2 mb-2 text-slate-800">
                <span className="text-slate-400">{icon}</span>
                <span className="text-sm font-semibold">{title}</span>
            </div>
            {configured ? (
                <p className="text-xs text-emerald-700">
                    ✓ Configured
                </p>
            ) : (
                <p className="text-xs text-slate-500">
                    Coming soon — contact support if you need to update this now.
                </p>
            )}
        </div>
    );
}
