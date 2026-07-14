import { useState } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
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
                        onChange={(v) => { setData(key, v); }}
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

interface FieldEditorProps {
    group: string;
    fieldKey: string;
    value: JsonValue;
    onChange: (v: JsonValue) => void;
    sectionsRegistry: SectionsRegistry;
}

function FieldEditor({ group, fieldKey, value, onChange }: FieldEditorProps) {
    const label = fieldKey.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

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

function isTranslatableValue(v: unknown): boolean {
    return (
        v !== null &&
        typeof v === 'object' &&
        !Array.isArray(v) &&
        'en' in (v as Record<string, unknown>)
    );
}
