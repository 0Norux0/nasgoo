import { useState } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageContainer, PageHeader } from '@/Components/Layout/PageContainer';
import type { SharedProps } from '@/types/inertia';

type JsonValue = unknown;
type SiteSettingsFormData = Record<string, JsonValue>;
type LooseSiteSettingsFormData = Record<string, any>;

interface SectionsRegistry {
    [key: string]: {
        key: string;
        component: string;
        default_settings: SiteSettingsFormData;
        required_feature: string | null;
        allows_translation: boolean;
    };
}

interface Props extends SharedProps {
    settings: Record<string, SiteSettingsFormData>;
    sections_registry: SectionsRegistry;
}

type GroupName = 'branding' | 'appearance' | 'header' | 'homepage'
                | 'footer' | 'contact' | 'social' | 'seo' | 'mobile'
                | 'vendor_intelligence';
type HomepageSectionSettings = Record<string, JsonValue>;

const GROUPS: GroupName[] = [
    'branding', 'appearance', 'header', 'homepage',
    'footer', 'contact', 'social', 'seo', 'mobile',
    // Phase 11B.4 v11B.4.3 Fix 1 — vendor_intelligence tab.
    // v11B.4.2 fixed the route regex + validation branch on the backend
    // but the tab still didn't appear in the UI because this GROUPS
    // const didn't include it. That was the developer's remaining
    // concern for issue 1.
    'vendor_intelligence',
];

// v11B.4.3 — human-readable tab labels for groups whose key doesn't
// display well in Title Case (e.g. snake_case). Falls back to the key
// otherwise.
const GROUP_LABELS: Record<GroupName, string> = {
    branding: 'Branding',
    appearance: 'Appearance',
    header: 'Header',
    homepage: 'Homepage',
    footer: 'Footer',
    contact: 'Contact',
    social: 'Social',
    seo: 'SEO',
    mobile: 'Mobile',
    vendor_intelligence: 'Vendor Intelligence',
};

/**
 * Phase 11B.3 v11B.3.1 §13 — Admin site settings.
 *
 * MVP: tabbed groups, per-group form, translatable-value dual inputs (en+ar).
 * Colors show a live swatch preview. Reset-per-group button. Save invalidates
 * cache server-side so the storefront reflects changes on the next page load.
 */
export default function SiteSettingsIndex() {
    const { settings, sections_registry } = usePage<Props>().props;
    const [activeGroup, setActiveGroup] = useState<GroupName>('branding');

    return (
        <AdminLayout title="Site settings">
            <PageContainer>
                <PageHeader
                    title="Site settings"
                    description="Change branding, colors, header, footer, and social links without editing source code."
                    testId="site-settings-title"
                />

                {/* Tabs */}
                <nav
                    className="border-b border-slate-200 mb-6 overflow-x-auto"
                    role="tablist"
                    aria-label="Settings groups"
                >
                    <div className="flex gap-1 min-w-max">
                        {GROUPS.map((g) => (
                            <button
                                key={g}
                                type="button"
                                role="tab"
                                aria-selected={activeGroup === g}
                                onClick={() => setActiveGroup(g)}
                                className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap ${
                                    activeGroup === g
                                        ? 'border-indigo-600 text-indigo-700'
                                        : 'border-transparent text-slate-600 hover:text-slate-800'
                                }`}
                                data-testid={`settings-tab-${g}`}
                            >
                                {GROUP_LABELS[g] ?? g}
                            </button>
                        ))}
                    </div>
                </nav>

                <GroupEditor
                    group={activeGroup}
                    values={settings[activeGroup] ?? {}}
                    sectionsRegistry={sections_registry}
                />
            </PageContainer>
        </AdminLayout>
    );
}

interface GroupEditorProps {
    group: GroupName;
    values: SiteSettingsFormData;
    sectionsRegistry: SectionsRegistry;
}

function GroupEditor({ group, values, sectionsRegistry }: GroupEditorProps) {
    // v12.2.3 lint fix: explicit generic on useForm with SiteSettingsFormData
    // (a `Record<string, JsonValue>` alias) eliminates BOTH the type-inference
    // ambiguity AND the @typescript-eslint/no-explicit-any warning. See
    // JsonValue definition at the top of this file.
    const { data, setData, post, processing } = useForm<LooseSiteSettingsFormData>(
        values as LooseSiteSettingsFormData,
    );

    const updateField = (key: string, value: JsonValue) => {
        if (group === 'branding' && key === 'logo_url' && typeof value === 'string') {
            const next: LooseSiteSettingsFormData = { ...data, logo_url: value };

            for (const target of ['logo_dark_url', 'logo_compact_url', 'email_logo_url', 'social_image_url', 'favicon_url']) {
                const current = typeof next[target] === 'string' ? next[target] as string : '';

                if (current === '' || current === DEFAULT_BRAND_MEDIA[target]) {
                    next[target] = value;
                }
            }

            setData(next);
            return;
        }

        setData(key, value);
    };

    const handleSave = () => post(`/admin/site-settings/${group}`);

    const handleReset = () => {
        if (window.confirm(`Reset ${group} settings to defaults?`)) {
            router.post(`/admin/site-settings/${group}/reset`, {}, { preserveScroll: true });
        }
    };

    return (
        <div>
            <div className="bg-white border border-slate-200 rounded-xl p-4 sm:p-6 space-y-4">
                {(Object.entries(data) as [string, JsonValue][]).map(([key, val]) => (
                    <FieldEditor
                        key={key}
                        group={group}
                        fieldKey={key}
                        value={val}
                        onChange={(v) => { updateField(key, v); }}
                        sectionsRegistry={sectionsRegistry}
                    />
                ))}
            </div>

            <div className="flex items-center justify-between mt-4">
                <button
                    type="button"
                    onClick={handleReset}
                    className="text-sm text-rose-700 hover:text-rose-800 underline"
                    data-testid={`reset-${group}`}
                >
                    Reset to defaults
                </button>
                <button
                    type="button"
                    onClick={handleSave}
                    disabled={processing}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-md disabled:opacity-50"
                    data-testid={`save-${group}`}
                >
                    {processing ? 'Saving…' : 'Save changes'}
                </button>
            </div>
        </div>
    );
}

const DEFAULT_BRAND_MEDIA: Record<string, string> = {
    logo_dark_url: '/images/logo-dark.svg',
    logo_compact_url: '/images/logo-compact.svg',
    email_logo_url: '/images/logo.svg',
    social_image_url: '/images/og-default.png',
    favicon_url: '/favicon.ico',
};

interface FieldEditorProps {
    group: string;
    fieldKey: string;
    value: JsonValue;
    onChange: (v: JsonValue) => void;
    sectionsRegistry: SectionsRegistry;
}

function FieldEditor({ group, fieldKey, value, onChange, sectionsRegistry }: FieldEditorProps) {
    const label = fieldLabel(group, fieldKey);

    if (group === 'homepage' && fieldKey === 'section_order' && Array.isArray(value)) {
        return (
            <div>
                <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
                <input
                    type="text"
                    value={value.join(', ')}
                    onChange={(e) => {
                        onChange(e.target.value.split(',').map((item) => item.trim()).filter(Boolean));
                    }}
                    className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm font-mono"
                    data-testid={`${group}-${fieldKey}`}
                />
            </div>
        );
    }

    if (group === 'homepage' && fieldKey === 'sections' && isRecord(value)) {
        return (
            <HomepageSectionsEditor
                value={value}
                sectionsRegistry={sectionsRegistry}
                onChange={onChange}
            />
        );
    }

    if (isImageField(group, fieldKey, value)) {
        return (
            <ImageUrlEditor
                group={group}
                fieldKey={fieldKey}
                label={label}
                value={(value as string) ?? ''}
                onChange={onChange}
            />
        );
    }

    // Translatable: value is { en, ar, ... }
    if (isTranslatableValue(value)) {
        const t = value as Record<string, string>;
        return (
            <div>
                <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    {(['en', 'ar'] as const).map((lang) => (
                        <div key={lang}>
                            <span className="text-xs text-slate-500 uppercase">{lang}</span>
                            <input
                                type="text"
                                value={t[lang] ?? ''}
                                dir={lang === 'ar' ? 'rtl' : 'ltr'}
                                onChange={(e) => onChange({ ...t, [lang]: e.target.value })}
                                className="w-full mt-0.5 px-3 py-2 border border-slate-300 rounded-md text-sm"
                                data-testid={`${group}-${fieldKey}-${lang}`}
                            />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    // Boolean
    if (typeof value === 'boolean') {
        return (
            <label className="flex items-center justify-between p-2 border border-slate-100 rounded-md">
                <span className="text-sm font-medium text-slate-800">{label}</span>
                <input
                    type="checkbox"
                    checked={value}
                    onChange={(e) => onChange(e.target.checked)}
                    className="h-4 w-4 accent-indigo-600"
                    data-testid={`${group}-${fieldKey}`}
                />
            </label>
        );
    }

    // Color (heuristic: starts with #)
    if (typeof value === 'string' && /^#[0-9a-f]{3,8}$/i.test(value)) {
        return (
            <div>
                <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        className="h-9 w-14 border border-slate-300 rounded"
                        data-testid={`${group}-${fieldKey}-color`}
                    />
                    <input
                        type="text"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        className="flex-1 px-3 py-2 border border-slate-300 rounded-md text-sm font-mono"
                        data-testid={`${group}-${fieldKey}-hex`}
                    />
                </div>
            </div>
        );
    }

    // Arrays / objects: read-only JSON display (MVP)
    if (typeof value === 'object' && value !== null) {
        return (
            <div>
                <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
                <pre className="text-xs bg-slate-50 border border-slate-200 rounded p-2 overflow-x-auto">
                    {JSON.stringify(value, null, 2)}
                </pre>
                <p className="text-xs text-slate-400 mt-1">Complex value — edit via JSON in a future release.</p>
            </div>
        );
    }

    // Phase 11B.4 v11B.4.3 Fix 1 — number input for numeric values.
    // Ensures values save as `5`, not `"5"`, so Laravel's integer/numeric
    // validation on the vendor_intelligence group receives the right type.
    if (typeof value === 'number') {
        const isFloat = !Number.isInteger(value);
        return (
            <div>
                <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
                <input
                    type="number"
                    step={isFloat ? '0.01' : '1'}
                    value={value}
                    onChange={(e) => {
                        const v = e.target.value;
                        // Empty → 0; NaN protection; preserve float/int distinction.
                        const parsed = isFloat ? parseFloat(v) : parseInt(v, 10);
                        onChange(Number.isNaN(parsed) ? 0 : parsed);
                    }}
                    className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                    data-testid={`${group}-${fieldKey}`}
                />
            </div>
        );
    }

    // String
    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
            <input
                type="text"
                value={(value as string) ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                data-testid={`${group}-${fieldKey}`}
            />
        </div>
    );
}

function HomepageSectionsEditor({
    value,
    sectionsRegistry,
    onChange,
}: {
    value: Record<string, JsonValue>;
    sectionsRegistry: SectionsRegistry;
    onChange: (v: JsonValue) => void;
}) {
    const updateSection = (sectionKey: string, next: HomepageSectionSettings) => {
        onChange({ ...value, [sectionKey]: next });
    };

    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-2">Homepage sections</label>
            <div className="space-y-3">
                {Object.keys(sectionsRegistry).map((sectionKey) => {
                    const current = isRecord(value[sectionKey])
                        ? value[sectionKey] as HomepageSectionSettings
                        : { ...(sectionsRegistry[sectionKey]?.default_settings ?? {}) };
                    const title = sectionKey.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
                    const cardImages = Array.isArray(current.card_images) ? current.card_images : null;

                    return (
                        <div key={sectionKey} className="border border-slate-200 rounded-md p-3 space-y-3">
                            <label className="flex items-center justify-between gap-3">
                                <span className="text-sm font-semibold text-slate-800">{title}</span>
                                <input
                                    type="checkbox"
                                    checked={(current.enabled as boolean | undefined) ?? true}
                                    onChange={(e) => updateSection(sectionKey, { ...current, enabled: e.target.checked })}
                                    className="h-4 w-4 accent-indigo-600"
                                    data-testid={`homepage-section-${sectionKey}-enabled`}
                                />
                            </label>

                            {'heading' in current && isTranslatableValue(current.heading) && (
                                <TranslatableTextInputs
                                    group="homepage"
                                    fieldKey={`${sectionKey}-heading`}
                                    label="Heading"
                                    value={current.heading as Record<string, string>}
                                    onChange={(next) => updateSection(sectionKey, { ...current, heading: next })}
                                />
                            )}

                            {'subheading' in current && isTranslatableValue(current.subheading) && (
                                <TranslatableTextInputs
                                    group="homepage"
                                    fieldKey={`${sectionKey}-subheading`}
                                    label="Subheading"
                                    value={current.subheading as Record<string, string>}
                                    onChange={(next) => updateSection(sectionKey, { ...current, subheading: next })}
                                />
                            )}

                            {'image_url' in current && (
                                <ImageUrlEditor
                                    group="homepage"
                                    fieldKey={`${sectionKey}-image_url`}
                                    label="Image"
                                    value={(current.image_url as string) ?? ''}
                                    onChange={(next) => updateSection(sectionKey, { ...current, image_url: next })}
                                />
                            )}

                            {cardImages && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-800 mb-2">
                                        Hero card images
                                    </label>
                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                        {([0, 1, 2, 3] as const).map((index) => (
                                            <ImageUrlEditor
                                                key={index}
                                                group="homepage"
                                                fieldKey={`${sectionKey}-card_images-${index}`}
                                                label={`Image ${index + 1}`}
                                                value={typeof cardImages[index] === 'string' ? cardImages[index] : ''}
                                                onChange={(next) => {
                                                    const images = [...cardImages] as JsonValue[];

                                                    while (images.length < 4) {
                                                        images.push('');
                                                    }

                                                    images[index] = next;
                                                    updateSection(sectionKey, { ...current, card_images: images.slice(0, 4) });
                                                }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {'cta_url' in current && (
                                <TextInput
                                    group="homepage"
                                    fieldKey={`${sectionKey}-cta_url`}
                                    label="CTA URL"
                                    value={(current.cta_url as string) ?? ''}
                                    onChange={(next) => updateSection(sectionKey, { ...current, cta_url: next })}
                                />
                            )}

                            {'cta_label' in current && isTranslatableValue(current.cta_label) && (
                                <TranslatableTextInputs
                                    group="homepage"
                                    fieldKey={`${sectionKey}-cta_label`}
                                    label="CTA label"
                                    value={current.cta_label as Record<string, string>}
                                    onChange={(next) => updateSection(sectionKey, { ...current, cta_label: next })}
                                />
                            )}

                            {'limit' in current && typeof current.limit === 'number' && (
                                <NumberInput
                                    group="homepage"
                                    fieldKey={`${sectionKey}-limit`}
                                    label="Limit"
                                    value={current.limit}
                                    onChange={(next) => updateSection(sectionKey, { ...current, limit: next })}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function ImageUrlEditor({
    group,
    fieldKey,
    label,
    value,
    onChange,
}: {
    group: string;
    fieldKey: string;
    label: string;
    value: string;
    onChange: (v: JsonValue) => void;
}) {
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (file: File | null) => {
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        formData.append('group', group);
        formData.append('key', fieldKey);

        setUploading(true);
        setError(null);

        try {
            const response = await axios.post<{ url: string }>('/admin/site-settings/upload-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            onChange(response.data.url);
        } catch {
            setError('Upload failed.');
        } finally {
            setUploading(false);
        }
    };

    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
            <div className="grid gap-2 sm:grid-cols-[96px_1fr]">
                <div className="h-20 w-24 rounded-md border border-slate-200 bg-slate-50 overflow-hidden grid place-items-center">
                    {value ? (
                        <img src={value} alt="" className="h-full w-full object-contain" />
                    ) : (
                        <span className="text-xs text-slate-400">No image</span>
                    )}
                </div>
                <div className="space-y-2">
                    <input
                        type="text"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                        data-testid={`${group}-${fieldKey}`}
                    />
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon"
                        onChange={(e) => void upload(e.currentTarget.files?.[0] ?? null)}
                        disabled={uploading}
                        className="block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 disabled:opacity-50"
                        data-testid={`${group}-${fieldKey}-upload`}
                    />
                    {error && <p className="text-xs text-rose-600">{error}</p>}
                </div>
            </div>
        </div>
    );
}

function TextInput({
    group,
    fieldKey,
    label,
    value,
    onChange,
}: {
    group: string;
    fieldKey: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                data-testid={`${group}-${fieldKey}`}
            />
        </div>
    );
}

function NumberInput({
    group,
    fieldKey,
    label,
    value,
    onChange,
}: {
    group: string;
    fieldKey: string;
    label: string;
    value: number;
    onChange: (v: number) => void;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
            <input
                type="number"
                value={value}
                onChange={(e) => onChange(Number.parseInt(e.target.value, 10) || 0)}
                className="w-full px-3 py-2 border border-slate-300 rounded-md text-sm"
                data-testid={`${group}-${fieldKey}`}
            />
        </div>
    );
}

function TranslatableTextInputs({
    group,
    fieldKey,
    label,
    value,
    onChange,
}: {
    group: string;
    fieldKey: string;
    label: string;
    value: Record<string, string>;
    onChange: (v: Record<string, string>) => void;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-800 mb-1">{label}</label>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                {(['en', 'ar'] as const).map((lang) => (
                    <div key={lang}>
                        <span className="text-xs text-slate-500 uppercase">{lang}</span>
                        <input
                            type="text"
                            value={value[lang] ?? ''}
                            dir={lang === 'ar' ? 'rtl' : 'ltr'}
                            onChange={(e) => onChange({ ...value, [lang]: e.target.value })}
                            className="w-full mt-0.5 px-3 py-2 border border-slate-300 rounded-md text-sm"
                            data-testid={`${group}-${fieldKey}-${lang}`}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

function isTranslatableValue(v: unknown): boolean {
    return (
        v !== null &&
        typeof v === 'object' &&
        !Array.isArray(v) &&
        'en' in (v as Record<string, unknown>)
    );
}

function isRecord(v: unknown): v is Record<string, JsonValue> {
    return v !== null && typeof v === 'object' && !Array.isArray(v);
}

function isImageField(group: string, fieldKey: string, value: JsonValue): boolean {
    if (typeof value !== 'string') return false;
    if (group === 'branding' && fieldKey.endsWith('_url')) return true;
    if (group === 'seo' && fieldKey.includes('image')) return true;
    if (group === 'homepage' && fieldKey.includes('image')) return true;
    if (group === 'footer' && fieldKey.includes('image')) return true;
    if (group === 'social' && fieldKey.includes('image')) return true;
    return false;
}

function fieldLabel(group: string, fieldKey: string): string {
    const labels: Record<string, string> = {
        'branding.logo_url': 'Universal brand image',
        'branding.logo_dark_url': 'Dark logo override',
        'branding.logo_compact_url': 'Compact logo override',
        'branding.email_logo_url': 'Email logo override',
        'branding.social_image_url': 'Social sharing image override',
        'branding.favicon_url': 'Favicon override',
        'seo.default_og_image': 'Default social sharing image',
    };

    return labels[`${group}.${fieldKey}`]
        ?? fieldKey.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}
