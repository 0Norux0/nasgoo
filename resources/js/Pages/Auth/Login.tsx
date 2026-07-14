import { useForm, Link, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/forms/Button';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

interface Props {
    redirect: string | null;
}

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
    redirect: string;
    [key: string]: string | boolean;
}

export default function Login({ redirect }: Props) {
    const t = useT();
    const { flash } = usePage<SharedProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
        redirect: redirect ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title={t('auth.sign_in_title')}>
            {/* v3.3 — show a clear hint when an admin tried /login by mistake */}
            {flash.error && (
                <div className="mb-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                    {flash.error}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                {/* preserve ?redirect= through the POST */}
                <input type="hidden" name="redirect" value={data.redirect} />

                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1">
                        {t('auth.email')}
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="email"
                        required
                        className="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border"
                    />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <div className="flex items-center justify-between mb-1">
                        <label htmlFor="password" className="block text-sm font-medium text-slate-700">
                            {t('auth.password')}
                        </label>
                        <Link href="/forgot-password" className="text-xs text-indigo-600 hover:underline">
                            {t('auth.forgot_password')}
                        </Link>
                    </div>
                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                        required
                        className="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border"
                    />
                    {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                </div>

                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    {t('auth.remember_me')}
                </label>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? t('auth.signing_in') : t('nav.sign_in')}
                </Button>

                <p className="text-center text-sm text-slate-600 pt-2">
                    {t('auth.no_account')}{' '}
                    <Link href="/register" className="text-indigo-600 hover:underline font-medium">
                        {t('auth.create_one')}
                    </Link>
                </p>

                <div className="pt-4 mt-4 border-t border-slate-100 text-center">
                    <p className="text-sm text-slate-500 mb-1">{t('auth.vendor_subtitle')}</p>
                    <Link
                        href="/login?redirect=/vendor/apply"
                        className="text-sm text-indigo-600 hover:underline font-medium"
                    >
                        {t('auth.vendor_cta_arrow')}
                    </Link>
                </div>

                <div className="pt-2 text-center text-xs text-slate-400">
                    {t('auth.admin_redirect_hint')}
                </div>
            </form>
        </AuthLayout>
    );
}
