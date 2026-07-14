import { useForm, usePage, router } from '@inertiajs/react';
import { StorefrontLayout } from '@/Layouts/StorefrontLayout';
import Container from '@/Components/Layout/Container';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';
import type { FormEvent } from 'react';

interface Preferences {
    behavioral_personalization_enabled: boolean;
    guest_merge_enabled: boolean;
    behavior_tracking_enabled: boolean;
}

interface Props extends SharedProps {
    preferences: Preferences;
    flags: {
        personalization_enabled: boolean;
        feedback_controls_enabled: boolean;
    };
}

/**
 * Phase 11B.3 §21 §22 — customer personalization settings.
 */
export default function PersonalizationSettings() {
    const t = useT();
    const { preferences, flags } = usePage<Props>().props;

    const { data, setData, post, processing } = useForm({
        behavioral_personalization_enabled: preferences.behavioral_personalization_enabled,
        guest_merge_enabled: preferences.guest_merge_enabled,
        behavior_tracking_enabled: preferences.behavior_tracking_enabled,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/account/personalization');
    };

    const handleReset = () => {
        if (window.confirm(t('personalization.settings.reset_confirm', 'Reset all personalization data?'))) {
            router.post('/personalization/reset', {}, { preserveScroll: true });
        }
    };

    return (
        <StorefrontLayout title={t('personalization.settings.title', 'Personalization')}>
            <Container className="py-8 max-w-2xl">
                <h1 className="text-2xl font-bold mb-4">
                    {t('personalization.settings.title', 'Personalization')}
                </h1>
                <p className="text-sm text-slate-600 mb-6" data-testid="personalization-explanation">
                    {t('personalization.settings.explanation',
                       'We use only your ordinary marketplace activity to personalize this page. We never use sensitive information.')}
                </p>

                {!flags.personalization_enabled && (
                    <div className="bg-amber-50 border border-amber-200 rounded p-3 mb-6 text-sm text-amber-900">
                        {t('personalization.settings.disabled_globally',
                           'Personalization is currently disabled for all users. Contact support if you have questions.')}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <Toggle
                        label={t('personalization.settings.behavioral_toggle', 'Personalized recommendations')}
                        help={t('personalization.settings.behavioral_help',
                                'Show homepage sections tailored to your browsing and purchases.')}
                        checked={data.behavioral_personalization_enabled}
                        onChange={(v) => setData('behavioral_personalization_enabled', v)}
                        testId="behavioral-toggle"
                    />

                    <Toggle
                        label={t('personalization.settings.tracking_toggle', 'Track my activity for personalization')}
                        help={t('personalization.settings.tracking_help',
                                "When off, we won't record your product views.")}
                        checked={data.behavior_tracking_enabled}
                        onChange={(v) => setData('behavior_tracking_enabled', v)}
                        testId="tracking-toggle"
                    />

                    <Toggle
                        label={t('personalization.settings.guest_merge_toggle', 'Merge my recent guest activity on sign in')}
                        help={t('personalization.settings.guest_merge_help',
                                'When on, products you viewed just before signing in will be added to your history.')}
                        checked={data.guest_merge_enabled}
                        onChange={(v) => setData('guest_merge_enabled', v)}
                        testId="guest-merge-toggle"
                    />

                    <div className="flex items-center justify-between pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            onClick={handleReset}
                            className="text-sm text-rose-700 hover:text-rose-800 underline"
                            data-testid="reset-personalization"
                        >
                            {t('personalization.settings.reset_button', 'Reset personalization data')}
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded disabled:opacity-50"
                            data-testid="save-personalization"
                        >
                            {processing
                                ? t('common.saving', 'Saving...')
                                : t('common.save', 'Save')}
                        </button>
                    </div>
                </form>
            </Container>
        </StorefrontLayout>
    );
}

interface ToggleProps {
    label: string;
    help?: string;
    checked: boolean;
    onChange: (v: boolean) => void;
    testId?: string;
}

function Toggle({ label, help, checked, onChange, testId }: ToggleProps) {
    return (
        <label className="flex items-start justify-between gap-4 p-4 border border-slate-200 rounded-lg hover:border-slate-300">
            <span className="flex-1">
                <span className="block text-sm font-medium text-slate-900">{label}</span>
                {help && <span className="block text-xs text-slate-500 mt-1">{help}</span>}
            </span>
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="h-5 w-5 mt-0.5 accent-indigo-600"
                data-testid={testId}
            />
        </label>
    );
}
