import { useForm, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import Button from '@/Components/forms/Button';

interface Props {
    status?: string;
}

export default function VerifyEmail({ status }: Props) {
    const { post, processing } = useForm({});

    const resend = (e: FormEvent) => {
        e.preventDefault();
        post('/email/verification-notification');
    };

    return (
        <AuthLayout title="Verify your email">
            <p className="text-sm text-slate-600 mb-4">
                Thanks for signing up! Before getting started, please verify your email by clicking the link we just sent.
                If you didn&apos;t receive it, we can send another.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-700">
                    A new verification link has been sent to your email.
                </div>
            )}

            <form onSubmit={resend} className="space-y-3">
                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Sending…' : 'Resend verification email'}
                </Button>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="w-full block text-center text-sm text-slate-600 hover:underline pt-2"
                >
                    Sign out
                </Link>
            </form>
        </AuthLayout>
    );
}
