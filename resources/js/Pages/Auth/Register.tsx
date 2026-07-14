import { useForm, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/forms/Button';
import { useT } from '@/lib/i18n';

interface Props {
    redirect: string | null;
}

interface RegisterForm {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    terms: boolean;
    redirect: string;
    [key: string]: string | boolean;
}

export default function Register({ redirect }: Props) {
    const t = useT();
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '', email: '', phone: '', password: '', password_confirmation: '', terms: false,
        redirect: redirect ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/register', { onFinish: () => reset('password', 'password_confirmation') });
    };

    const inputCls = 'w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border';

    return (
        <AuthLayout title={t('auth.register_title')}>
            <form onSubmit={submit} className="space-y-4">
                {/* preserve ?redirect= through the POST */}
                <input type="hidden" name="redirect" value={data.redirect} />
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">{t('auth.full_name')}</label>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" required className={inputCls} />
                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">{t('auth.email')}</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" required className={inputCls} />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">{t('auth.phone_optional')}</label>
                    <input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} autoComplete="tel" placeholder="+965 0000 0000" className={inputCls} />
                    {errors.phone && <p className="mt-1 text-sm text-red-600">{errors.phone}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">{t('auth.password')}</label>
                    <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" required className={inputCls} />
                    {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">{t('auth.confirm_password')}</label>
                    <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} autoComplete="new-password" required className={inputCls} />
                </div>

                <label className="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={data.terms} onChange={(e) => setData('terms', e.target.checked)} className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <span>{t('auth.agree_terms')}</span>
                </label>
                {errors.terms && <p className="text-sm text-red-600">{errors.terms}</p>}

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? t('auth.creating_account') : t('nav.register')}
                </Button>

                <p className="text-center text-sm text-slate-600 pt-2">
                    {t('auth.has_account')}{' '}
                    <Link href="/login" className="text-indigo-600 hover:underline font-medium">{t('nav.sign_in')}</Link>
                </p>

                <div className="pt-4 mt-4 border-t border-slate-100 text-center">
                    <p className="text-sm text-slate-500 mb-1">{t('auth.vendor_subtitle')}</p>
                    <Link
                        href="/register?redirect=/vendor/apply"
                        className="text-sm text-indigo-600 hover:underline font-medium"
                    >
                        {t('auth.vendor_cta_arrow')}
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
