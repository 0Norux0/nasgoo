import { Head, Link } from '@inertiajs/react';

interface Props {
    available: boolean;
    reason: string | null;
}

export default function Status({ available, reason }: Props) {
    return (
        <>
            <Head title="Marketplace status" />
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 className="mb-2 text-lg font-semibold text-slate-900">Marketplace</h1>
                    {available ? (
                        <>
                            <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                                Currently available.
                            </div>
                            <Link
                                href="/"
                                className="inline-block text-sm text-indigo-600 hover:underline"
                            >
                                Continue to storefront
                            </Link>
                        </>
                    ) : (
                        <>
                            <div className="mb-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                                {reason ?? 'The marketplace is temporarily unavailable.'}
                            </div>
                            <div className="text-xs text-slate-500">
                                Please check back later. If you believe this is an error, contact
                                the site administrator.
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
