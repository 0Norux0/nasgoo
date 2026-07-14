import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import type { SharedProps } from '@/types/inertia';

interface Props {
    title: string;
    children: ReactNode;
}

/**
 * Phase 10 v10.1 — AdminLayout for Inertia-rendered admin pages.
 *
 * The Filament admin panel covers the bulk of admin work, but Phase 10
 * shipped a dedicated /admin/reports Inertia page for the rich
 * dashboard. Without this layout file the page module failed to
 * resolve at build time, which is the root cause of the developer's
 * "admin reports doesn't exist" report.
 *
 * Phase 10 v10.5 — fixed TS2344 by using the canonical SharedProps
 * type rather than an incomplete inline `{ auth: PageAuth }`. The
 * project's `@inertiajs/core` is augmented so PageProps extends
 * SharedProps (see resources/js/types/inertia.d.ts); a partial
 * `{ auth: ... }` does not satisfy that constraint. The bad type was
 * the silent reason `npm run typecheck` failed across v10.1-v10.4 →
 * `npm run build` failed → no React layouts/pages reached the browser.
 *
 * Mobile-first: header collapses to a hamburger at < md.
 */
export default function AdminLayout({ title, children }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <div className="min-h-screen bg-slate-50">
            <Head title={`${title} · Admin`} />
            <header className="bg-white border-b border-slate-200 sticky top-0 z-30">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/admin" className="font-semibold text-slate-900">
                            Admin
                        </Link>
                        <nav className="hidden md:flex gap-4 text-sm">
                            <a href="/admin" className="text-slate-600 hover:text-slate-900">Filament panel</a>
                            <Link href="/admin/reports" className="text-indigo-700 font-medium">Reports</Link>
                        </nav>
                    </div>
                    <div className="flex items-center gap-3">
                        {auth?.user && (
                            <span className="hidden sm:inline text-sm text-slate-600">{auth.user.email}</span>
                        )}
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="text-sm text-slate-600 hover:text-slate-900"
                        >
                            Log out
                        </Link>
                        <button
                            type="button"
                            onClick={() => setMobileOpen((v) => !v)}
                            className="md:hidden p-2 -mr-2 rounded text-slate-600 hover:bg-slate-100"
                            aria-label="Open menu"
                            aria-expanded={mobileOpen}
                        >
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                {mobileOpen
                                    ? <path d="M6 6l12 12M6 18L18 6" strokeLinecap="round" />
                                    : <path d="M4 6h16M4 12h16M4 18h16" strokeLinecap="round" />}
                            </svg>
                        </button>
                    </div>
                </div>
                {mobileOpen && (
                    <div className="md:hidden border-t border-slate-200 bg-white">
                        <nav className="flex flex-col py-2 px-4 text-sm" onClick={() => setMobileOpen(false)}>
                            <a href="/admin" className="py-2 text-slate-600">Filament panel</a>
                            <Link href="/admin/reports" className="py-2 text-indigo-700 font-medium">Reports</Link>
                        </nav>
                    </div>
                )}
            </header>
            <main className="max-w-7xl mx-auto px-4 sm:px-6 py-6">{children}</main>
        </div>
    );
}
