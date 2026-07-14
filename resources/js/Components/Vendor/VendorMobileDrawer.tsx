import { useEffect, useRef, useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import VendorSidebar from './VendorSidebar';
import type { SharedProps } from '@/types/inertia';
import type { FC } from 'react';

/**
 * Phase 11B.3 v11B.3.1 §30 §36 — vendor mobile drawer.
 *
 * Wraps VendorSidebar in a slide-in panel with:
 *   - backdrop (click closes)
 *   - close button (X)
 *   - focus trap while open (Tab / Shift+Tab loop)
 *   - Escape closes
 *   - body scroll locked while open
 *   - RTL: slides in from the RIGHT in Arabic (start-side of RTL)
 *   - focus returns to the trigger after close
 *   - closes automatically after navigation (VendorSidebar's onNavigate)
 */

interface VendorMobileDrawerProps {
    open: boolean;
    onClose: () => void;
    isApprovedVendor: boolean;
    currentPath: string;
    /** The element that opened the drawer, to restore focus on close. */
    triggerRef?: React.RefObject<HTMLElement | null>;
}

const VendorMobileDrawer: FC<VendorMobileDrawerProps> = ({
    open, onClose, isApprovedVendor, currentPath, triggerRef,
}) => {
    const { app } = usePage<SharedProps>().props;
    const isRTL   = app.direction === 'rtl';
    const panelRef = useRef<HTMLDivElement>(null);
    const closeButtonRef = useRef<HTMLButtonElement>(null);

    // Focus trap
    const trapFocus = useCallback((e: KeyboardEvent) => {
        if (!open || !panelRef.current) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
            return;
        }
        if (e.key !== 'Tab') return;

        const focusables = panelRef.current.querySelectorAll<HTMLElement>(
            'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])'
        );
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last  = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }, [open, onClose]);

    // On open: lock body scroll + move focus into panel + wire trap.
    // On close: restore body scroll + return focus to trigger.
    useEffect(() => {
        if (!open) return;

        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // Focus the close button on open
        const focusTimer = setTimeout(() => closeButtonRef.current?.focus(), 50);

        document.addEventListener('keydown', trapFocus);

        return () => {
            document.body.style.overflow = prevOverflow;
            document.removeEventListener('keydown', trapFocus);
            clearTimeout(focusTimer);
            // Restore focus to the trigger
            triggerRef?.current?.focus();
        };
    }, [open, trapFocus, triggerRef]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 lg:hidden"
            role="dialog"
            aria-modal="true"
            aria-label="Vendor navigation"
            data-testid="vendor-mobile-drawer"
        >
            {/* Backdrop */}
            <button
                type="button"
                onClick={onClose}
                aria-label="Close menu"
                className="absolute inset-0 bg-slate-900/50 cursor-pointer"
                data-testid="vendor-drawer-backdrop"
                tabIndex={-1}
            />

            {/* Panel — slides from start side (left in LTR, right in RTL) */}
            <div
                ref={panelRef}
                className={`
                    absolute inset-y-0 ${isRTL ? 'end-0' : 'start-0'}
                    w-72 max-w-[85%] bg-white shadow-xl
                    flex flex-col
                `}
                data-testid="vendor-drawer-panel"
            >
                <div className="flex items-center justify-between p-3 border-b border-slate-200">
                    <span className="text-sm font-semibold text-slate-900">
                        Vendor menu
                    </span>
                    <button
                        ref={closeButtonRef}
                        type="button"
                        onClick={onClose}
                        aria-label="Close vendor menu"
                        className="p-2 text-slate-500 hover:text-slate-800 rounded-md"
                        data-testid="vendor-drawer-close"
                    >
                        <X size={18} />
                    </button>
                </div>
                <div className="flex-1 overflow-hidden">
                    <VendorSidebar
                        isApprovedVendor={isApprovedVendor}
                        currentPath={currentPath}
                        onNavigate={onClose}
                        className="border-e-0"
                    />
                </div>
            </div>
        </div>
    );
};

export default VendorMobileDrawer;
