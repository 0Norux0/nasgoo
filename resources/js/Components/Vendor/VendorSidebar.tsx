import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, Package, PackagePlus, ShoppingBag,
    Sparkles, Calendar, BarChart3, Wallet, Receipt,
    Truck, Users, MessagesSquare, Settings, LogOut,
    Star,
} from 'lucide-react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';
import type { FC, ReactNode } from 'react';

/**
 * Phase 11B.3 v11B.3.1 §28-§32 §36 — vendor desktop side navigation.
 *
 * REPLACES the pre-v11B.3.1 hamburger-only inline nav in VendorLayout.
 * Provides:
 *   - persistent side panel on desktop (lg+)
 *   - grouped modules with icons
 *   - active-route highlighting
 *   - permission-aware (approved-vendor gating)
 *   - keyboard-accessible (native <a> tags for nav rows)
 *
 * The mobile drawer counterpart lives in VendorMobileDrawer.tsx.
 */

interface NavItem {
    href: string;
    label: string;
    icon: ReactNode;
    requiresApproved?: boolean;
    testid?: string;
    external?: boolean;
}

interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface VendorSidebarProps {
    isApprovedVendor: boolean;
    currentPath: string;
    className?: string;
    /** Called when the caller wants to close (mobile drawer). Desktop ignores. */
    onNavigate?: () => void;
}

const VendorSidebar: FC<VendorSidebarProps> = ({
    isApprovedVendor, currentPath, className = '', onNavigate,
}) => {
    const t = useT();
    const { siteSettings } = usePage<SharedProps>().props;
    const brandName = String(siteSettings?.branding?.site_name ?? 'Marketplace');
    const logoValue = siteSettings?.branding?.logo_compact_url ?? siteSettings?.branding?.logo_url;
    const logoUrl = typeof logoValue === 'string' ? logoValue : null;

    const groups: NavGroup[] = [
        {
            title: t('vendor_nav.groups.overview', 'Overview'),
            items: [
                { href: '/vendor', label: t('vendor_nav.dashboard', 'Dashboard'),
                  icon: <LayoutDashboard size={16} />, testid: 'vnav-dashboard' },
                { href: '/vendor/reports', label: t('vendor_nav.reports', 'Reports'),
                  icon: <BarChart3 size={16} />, testid: 'vnav-reports' },
            ],
        },
        {
            title: t('vendor_nav.groups.catalog', 'Catalog'),
            items: [
                { href: '/vendor/products', label: t('vendor_nav.products', 'Products'),
                  icon: <Package size={16} />, testid: 'vnav-products' },
                { href: '/vendor/products/create', label: t('vendor_nav.add_product', 'Add product'),
                  icon: <PackagePlus size={16} />, testid: 'vnav-add-product' },
                { href: '/vendor/services', label: t('vendor_nav.services', 'Services'),
                  icon: <Sparkles size={16} />, requiresApproved: true, testid: 'vnav-services' },
            ],
        },
        {
            title: t('vendor_nav.groups.orders', 'Orders & bookings'),
            items: [
                { href: '/vendor/orders', label: t('vendor_nav.orders', 'Orders'),
                  icon: <ShoppingBag size={16} />, testid: 'vnav-orders' },
                { href: '/vendor/bookings', label: t('vendor_nav.bookings', 'Bookings'),
                  icon: <Calendar size={16} />, requiresApproved: true, testid: 'vnav-bookings' },
                { href: '/vendor/reviews', label: t('vendor_nav.reviews', 'Reviews'),
                  icon: <Star size={16} />, requiresApproved: true, testid: 'vnav-reviews' },
            ],
        },
        {
            title: t('vendor_nav.groups.finance', 'Finance'),
            items: [
                { href: '/vendor/wallet', label: t('vendor_nav.wallet', 'Wallet'),
                  icon: <Wallet size={16} />, requiresApproved: true, testid: 'vnav-wallet' },
                { href: '/vendor/payouts', label: t('vendor_nav.payouts', 'Payouts'),
                  icon: <Receipt size={16} />, requiresApproved: true, testid: 'vnav-payouts' },
            ],
        },
        {
            title: t('vendor_nav.groups.suppliers', 'Suppliers'),
            items: [
                { href: '/vendor/supplier-products', label: t('vendor_nav.supplier_products', 'Supplier products'),
                  icon: <Truck size={16} />, requiresApproved: true, testid: 'vnav-supplier-products' },
                { href: '/vendor/supplier-orders', label: t('vendor_nav.supplier_orders', 'Supplier orders'),
                  icon: <Users size={16} />, requiresApproved: true, testid: 'vnav-supplier-orders' },
            ],
        },
        {
            title: t('vendor_nav.groups.communication', 'Communication'),
            items: [
                { href: '/vendor/tickets', label: t('vendor_nav.tickets', 'Support'),
                  icon: <MessagesSquare size={16} />, testid: 'vnav-tickets' },
            ],
        },
        {
            title: t('vendor_nav.groups.settings', 'Settings'),
            items: [
                { href: '/vendor/settings', label: t('vendor_nav.settings', 'Settings'),
                  icon: <Settings size={16} />, testid: 'vnav-settings' },
            ],
        },
    ];

    const isActive = (href: string): boolean => {
        if (href === '/vendor') return currentPath === '/vendor';
        return currentPath === href || currentPath.startsWith(`${href}/`);
    };

    return (
        <nav
            className={`bg-white border-e border-slate-200 flex flex-col h-full ${className}`}
            aria-label={t('vendor_nav.aria_label', 'Vendor navigation')}
            data-testid="vendor-sidebar"
        >
            {/* Brand header */}
            <div className="px-4 py-4 border-b border-slate-200 flex items-center gap-3">
                {logoUrl && (
                    <img
                        src={logoUrl as string}
                        alt=""
                        className="w-8 h-8 object-contain"
                    />
                )}
                <div className="min-w-0">
                    <div className="text-xs uppercase tracking-wide text-slate-500">
                        {t('vendor_nav.brand_eyebrow', 'Vendor')}
                    </div>
                    <div className="text-sm font-semibold text-slate-900 truncate" data-testid="vnav-brand">
                        {brandName}
                    </div>
                </div>
            </div>

            {/* Nav groups */}
            <div className="flex-1 overflow-y-auto py-2">
                {groups.map((group) => {
                    // Filter items by permission — hides items the vendor
                    // can't access. Server-side authorization is ALSO enforced
                    // on the routes; visibility is only a UX layer.
                    const visibleItems = group.items.filter(
                        (i) => !i.requiresApproved || isApprovedVendor
                    );
                    if (visibleItems.length === 0) return null;

                    return (
                        <div key={group.title} className="px-2 py-2">
                            <div className="px-2 pb-1 text-[10px] uppercase tracking-wider text-slate-400 font-medium">
                                {group.title}
                            </div>
                            <ul>
                                {visibleItems.map((item) => (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            onClick={onNavigate}
                                            className={`
                                                flex items-center gap-3 px-2 py-2 text-sm rounded-md
                                                ${isActive(item.href)
                                                    ? 'bg-indigo-50 text-indigo-700 font-medium'
                                                    : 'text-slate-700 hover:bg-slate-50'}
                                            `}
                                            data-testid={item.testid}
                                            aria-current={isActive(item.href) ? 'page' : undefined}
                                        >
                                            <span aria-hidden="true" className={isActive(item.href) ? 'text-indigo-600' : 'text-slate-400'}>
                                                {item.icon}
                                            </span>
                                            <span className="truncate">{item.label}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </div>

            {/* Logout footer */}
            <div className="border-t border-slate-200 p-2">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="w-full flex items-center gap-3 px-2 py-2 text-sm rounded-md text-slate-700 hover:bg-slate-50"
                    data-testid="vnav-logout"
                >
                    <LogOut size={16} className="text-slate-400" aria-hidden="true" />
                    <span>{t('vendor_nav.logout', 'Sign out')}</span>
                </Link>
            </div>
        </nav>
    );
};

export default VendorSidebar;
