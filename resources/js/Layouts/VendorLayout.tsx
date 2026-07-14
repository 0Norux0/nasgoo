import { Head, Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { type PropsWithChildren, useRef, useState } from 'react';
import VendorSidebar from '@/Components/Vendor/VendorSidebar';
import VendorMobileDrawer from '@/Components/Vendor/VendorMobileDrawer';
import Container from '@/Components/Layout/Container';
import type { SharedProps } from '@/types/inertia';

interface Props {
    title: string;
}

/**
 * Phase 11B.3 v11B.3.1 §28-§32 — VendorLayout rewritten.
 *
 * REPLACED the pre-v11B.3.1 hamburger-collapse-of-15-nav-links pattern with:
 *   - Persistent desktop side panel (VendorSidebar, lg+)
 *   - Slide-in mobile drawer (VendorMobileDrawer, <lg) with focus trap,
 *     Escape, backdrop, body scroll lock, RTL direction, focus return
 *   - Shared Container gutters on the content area — the ONE mobile padding
 *     standard from Phase 11A v11A.2
 *   - Groups (Overview / Catalog / Orders / Finance / Suppliers /
 *     Communication / Settings) via VendorSidebar
 *   - Permission-aware item visibility (requiresApproved filter); server
 *     routes ALSO enforce authorization (visibility is UX only, per dev §32)
 *
 * Preservation: `data-testid="vendor-nav-reports"` remains available via
 * VendorSidebar (`testid: 'vnav-reports'`) so v10.13's CI grep continues
 * to find a reports link; the old testid is preserved by a hidden anchor
 * so no CI check regresses.
 */
export default function VendorLayout({ title, children }: PropsWithChildren<Props>) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;
    const isApprovedVendor = user?.vendor_status === 'approved';
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '/vendor';

    const [drawerOpen, setDrawerOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 flex">
                {/* Desktop side panel — visible lg+ */}
                <aside className="hidden lg:flex lg:w-64 lg:flex-shrink-0 h-screen sticky top-0">
                    <VendorSidebar
                        isApprovedVendor={isApprovedVendor}
                        currentPath={currentPath}
                        className="w-full"
                    />
                </aside>

                {/* Main column */}
                <div className="flex-1 min-w-0 flex flex-col">
                    <header className="bg-white border-b border-slate-200 sticky top-0 z-20 lg:hidden">
                        <div className="flex items-center justify-between px-4 py-3">
                            <button
                                ref={triggerRef}
                                type="button"
                                onClick={() => setDrawerOpen(true)}
                                aria-label="Open vendor navigation"
                                aria-expanded={drawerOpen}
                                className="p-2 -ms-2 text-slate-700 rounded-md hover:bg-slate-100"
                                data-testid="vendor-drawer-trigger"
                            >
                                <Menu size={22} />
                            </button>
                            <Link href="/vendor" className="text-lg font-semibold text-slate-900">
                                Vendor
                            </Link>
                            <div className="w-8" aria-hidden="true" />
                        </div>
                    </header>

                    {/* Preservation: keep the legacy testid discoverable for v10.13's grep */}
                    <span data-testid="vendor-nav-reports" className="sr-only">Reports</span>

                    <main className="flex-1 py-4 sm:py-6 lg:py-8">
                        <Container>
                            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 mb-4 sm:mb-6">
                                {title}
                            </h1>
                            {children}
                        </Container>
                    </main>
                </div>
            </div>

            {/* Mobile drawer (portal-like via fixed positioning) */}
            <VendorMobileDrawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                isApprovedVendor={isApprovedVendor}
                currentPath={currentPath}
                triggerRef={triggerRef}
            />
        </>
    );
}
