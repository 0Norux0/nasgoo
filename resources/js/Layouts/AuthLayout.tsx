import { Head, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import type { SharedProps } from '@/types/inertia';

interface Props {
    title: string;
}

export default function AuthLayout({ title, children }: PropsWithChildren<Props>) {
    const { app, marketplace } = usePage<SharedProps>().props;

    return (
        <>
            <Head title={title} />
            <div
                className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-slate-50 flex flex-col"
                dir={app.direction}
            >
                <header className="px-6 py-4 flex items-center justify-between">
                    <a href="/" className="text-lg font-semibold text-slate-900">
                        {app.name}
                    </a>
                    <div className="text-sm text-slate-500">
                        {marketplace.default_currency} · {app.locale.toUpperCase()}
                    </div>
                </header>

                <main className="flex-1 flex items-center justify-center px-4 py-8">
                    <div className="w-full max-w-md bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8">
                        <h1 className="text-2xl font-semibold text-slate-900 mb-1">{title}</h1>
                        <div className="h-1 w-12 bg-indigo-500 rounded-full mb-6" />
                        {children}
                    </div>
                </main>

                <footer className="px-6 py-4 text-center text-xs text-slate-400">
                    © {new Date().getFullYear()} {app.name}
                </footer>
            </div>
        </>
    );
}
