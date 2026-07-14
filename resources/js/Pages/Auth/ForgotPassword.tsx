import { useForm, Link, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/forms/Button';
import type { SharedProps } from '@/types/inertia';

export default function ForgotPassword() {
    const { flash } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors } = useForm<{ email: string; [key: string]: string }>({
        email: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout title="Reset your password">
            <p className="text-sm text-slate-600 mb-4">
                Enter your email and we&apos;ll send you a reset link.
            </p>

            {flash.success && (
                <div className="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        className="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border"
                    />
                    {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                </div>

                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Sending…' : 'Send reset link'}
                </Button>

                <p className="text-center text-sm text-slate-600 pt-2">
                    <Link href="/login" className="text-indigo-600 hover:underline">
                        Back to sign in
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
