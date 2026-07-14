import { Head } from '@inertiajs/react';

interface Props {
    reason: string;
}

export default function Blocked({ reason }: Props) {
    return (
        <>
            <Head title="Marketplace unavailable" />
            <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
                <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <span aria-hidden="true">!</span>
                    </div>
                    <h1 className="mb-2 text-lg font-semibold text-slate-900">
                        Marketplace unavailable
                    </h1>
                    <p className="mb-4 text-sm text-slate-600">{reason}</p>
                    <p className="text-xs text-slate-500">
                        This is a temporary condition. Please contact your administrator.
                    </p>
                </div>
            </div>
        </>
    );
}
