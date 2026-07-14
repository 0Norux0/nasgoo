import { HTMLAttributes, ReactNode, ElementType } from 'react';

/**
 * Phase 11A — Card primitive.
 *
 * 3 variants: default (flat with border), elevated (subtle shadow),
 * interactive (hover lift for clickable cards like products).
 */
export type CardVariant = 'default' | 'elevated' | 'interactive';

const cardVariants: Record<CardVariant, string> = {
    default: 'bg-white border border-slate-200 rounded-2xl',
    elevated: 'bg-white border border-slate-200 rounded-2xl shadow-card',
    interactive:
        'bg-white border border-slate-200 rounded-2xl shadow-card ' +
        'hover:shadow-card-hover hover:border-slate-300 ' +
        'transition-shadow duration-200 ease-out',
};

type CardOwnProps = {
    variant?: CardVariant;
    padding?: 'none' | 'sm' | 'md' | 'lg';
    as?: ElementType;
    className?: string;
    children?: ReactNode;
};

type CardProps = CardOwnProps & Omit<HTMLAttributes<HTMLElement>, keyof CardOwnProps>;

export function Card({
    variant = 'default',
    padding = 'md',
    as: Comp = 'div',
    className = '',
    children,
    ...rest
}: CardProps) {
    const paddingClass = {
        none: '',
        sm: 'p-4',
        md: 'p-6',
        lg: 'p-8',
    }[padding];

    return (
        <Comp
            className={`${cardVariants[variant]} ${paddingClass} ${className}`.trim()}
            {...rest}
        >
            {children}
        </Comp>
    );
}

/**
 * Phase 11A — Badge primitive.
 *
 * 6 variants matching the design system. All use rounded-full + uppercase
 * tracking for that "label" look. Text-xs for compact badges, text-sm for
 * featured prominent ones.
 */
export type BadgeVariant = 'promo' | 'stock' | 'new' | 'trust' | 'warning' | 'danger' | 'neutral';
export type BadgeSize = 'sm' | 'md';

const badgeVariants: Record<BadgeVariant, string> = {
    promo:   'bg-gold-100 text-gold-900',
    stock:   'bg-accent-100 text-accent-900',
    new:     'bg-brand-100 text-brand-800',
    trust:   'bg-accent-50 text-accent-700 border border-accent-200',
    warning: 'bg-gold-50 text-gold-800 border border-gold-200',
    danger:  'bg-rose-50 text-rose-700 border border-rose-200',
    neutral: 'bg-slate-100 text-slate-700',
};

const badgeSizes: Record<BadgeSize, string> = {
    sm: 'px-2 py-0.5 text-[11px]',
    md: 'px-2.5 py-1 text-xs',
};

export function Badge({
    variant = 'neutral',
    size = 'md',
    className = '',
    children,
    icon,
}: {
    variant?: BadgeVariant;
    size?: BadgeSize;
    className?: string;
    children: ReactNode;
    icon?: ReactNode;
}) {
    return (
        <span
            className={
                'inline-flex items-center gap-1 font-semibold rounded-full ' +
                'uppercase tracking-wide whitespace-nowrap ' +
                badgeVariants[variant] + ' ' +
                badgeSizes[size] + ' ' +
                className
            }
        >
            {icon}
            {children}
        </span>
    );
}

/**
 * Phase 11A — SectionHeading.
 *
 * Consistent section title styling for homepage and major pages.
 * Optional CTA link/button rendered on the end side.
 */
export function SectionHeading({
    eyebrow,
    title,
    subtitle,
    align = 'start',
    cta,
}: {
    eyebrow?: string;
    title: string;
    subtitle?: string;
    align?: 'start' | 'center';
    cta?: ReactNode;
}) {
    const isCenter = align === 'center';
    return (
        <div
            className={
                'mb-8 sm:mb-10 flex flex-wrap items-end gap-4 ' +
                (isCenter ? 'flex-col text-center' : 'justify-between')
            }
        >
            <div className={isCenter ? 'mx-auto max-w-2xl' : 'max-w-2xl'}>
                {eyebrow && (
                    <p className="text-xs font-semibold uppercase tracking-widest text-brand-700 mb-2">
                        {eyebrow}
                    </p>
                )}
                <h2 className="font-display text-2xl sm:text-3xl font-bold text-slate-900">
                    {title}
                </h2>
                {subtitle && (
                    <p className="mt-2 text-sm sm:text-base text-slate-600">
                        {subtitle}
                    </p>
                )}
            </div>
            {cta && !isCenter && <div className="shrink-0">{cta}</div>}
        </div>
    );
}

/**
 * Phase 11A — TrustBadge (homepage trust indicators).
 *
 * Compact icon + label + body for trust-building rows.
 */
export function TrustBadge({
    icon,
    title,
    body,
}: {
    icon: ReactNode;
    title: string;
    body: string;
}) {
    return (
        <div className="flex items-start gap-3 sm:flex-col sm:items-center sm:text-center sm:gap-2">
            <div className="shrink-0 size-10 rounded-xl bg-brand-50 text-brand-700 grid place-items-center">
                {icon}
            </div>
            <div>
                <p className="font-semibold text-slate-900 text-sm sm:text-base">
                    {title}
                </p>
                <p className="text-xs sm:text-sm text-slate-600">{body}</p>
            </div>
        </div>
    );
}
