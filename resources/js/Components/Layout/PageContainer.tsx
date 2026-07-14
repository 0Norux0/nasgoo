import type { PropsWithChildren, ReactNode } from 'react';
import Container from './Container';

/**
 * Phase 11B.3 v11B.3.1 §17 — shared page layout primitives.
 *
 * PageContainer:  wraps Container + adds vertical rhythm consistent across
 *                 Orders / Bookings / Tickets / account pages.
 * PageHeader:     title + optional description + action slot, with consistent
 *                 spacing and mobile stacking (dev §17 §31).
 * EmptyState:     one canonical empty state — icon + heading + description +
 *                 optional CTA. Replaces per-page ad-hoc empties.
 *
 * All use Container's px-4 sm:px-6 lg:px-8 xl:px-10 scale — the ONE mobile
 * padding standard per dev §18.
 */

interface PageContainerProps {
    className?: string;
}

export function PageContainer({ children, className = '' }: PropsWithChildren<PageContainerProps>) {
    return (
        <div className="py-4 sm:py-6 lg:py-8">
            <Container className={className}>
                {children}
            </Container>
        </div>
    );
}

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
    testId?: string;
}

export function PageHeader({ title, description, actions, testId }: PageHeaderProps) {
    return (
        <div className="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1
                    className="text-xl sm:text-2xl font-semibold text-slate-900"
                    data-testid={testId ?? 'page-header-title'}
                >
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-slate-600 mt-1">{description}</p>
                )}
            </div>
            {actions && <div className="flex items-center gap-2 flex-wrap">{actions}</div>}
        </div>
    );
}

interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    testId?: string;
}

export function EmptyState({ title, description, icon, action, testId }: EmptyStateProps) {
    return (
        <div
            className="bg-white border border-slate-200 rounded-xl p-8 sm:p-12 text-center"
            data-testid={testId ?? 'empty-state'}
        >
            {icon && <div className="text-slate-400 mb-3 flex justify-center">{icon}</div>}
            <h3 className="text-base sm:text-lg font-medium text-slate-900">{title}</h3>
            {description && (
                <p className="text-sm text-slate-500 mt-1 max-w-md mx-auto">{description}</p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
