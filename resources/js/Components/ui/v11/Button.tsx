import { Link } from '@inertiajs/react';
import { ButtonHTMLAttributes, forwardRef, ReactNode } from 'react';

/**
 * Phase 11A — primary Button primitive.
 *
 * 5 variants (primary, accent, secondary, ghost, danger),
 * 3 sizes (sm, md, lg — all meet WCAG 2.5.5 min target),
 * loading state, optional href (renders as Inertia Link),
 * ARIA-correct disabled vs aria-busy.
 *
 * Replaces the older /Components/ui/Button.tsx and
 * /Components/forms/Button.tsx (both kept for backwards compat
 * during the v11A migration window).
 */
export type ButtonVariant = 'primary' | 'accent' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

const variantClasses: Record<ButtonVariant, string> = {
    primary:
        'bg-brand-800 text-white hover:bg-brand-900 active:bg-brand-900 ' +
        'focus-visible:ring-brand-500 shadow-sm',
    accent:
        'bg-accent-600 text-white hover:bg-accent-700 active:bg-accent-800 ' +
        'focus-visible:ring-accent-500 shadow-sm',
    secondary:
        'bg-white text-slate-900 border border-slate-200 ' +
        'hover:bg-slate-50 hover:border-slate-300 active:bg-slate-100 ' +
        'focus-visible:ring-brand-500',
    ghost:
        'bg-transparent text-slate-700 hover:bg-slate-100 active:bg-slate-200 ' +
        'focus-visible:ring-brand-500',
    danger:
        'bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 ' +
        'focus-visible:ring-rose-500 shadow-sm',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'h-9 px-3 text-sm rounded-lg',
    md: 'h-11 px-5 text-sm rounded-xl',   // 44px = WCAG min touch target
    lg: 'h-12 px-6 text-base rounded-xl',
};

const baseClasses =
    'inline-flex items-center justify-center gap-2 font-semibold ' +
    'transition-colors duration-150 ease-in-out ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none ' +
    'whitespace-nowrap select-none';

type BaseButtonProps = {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    leadingIcon?: ReactNode;
    trailingIcon?: ReactNode;
    fullWidth?: boolean;
    className?: string;
    children?: ReactNode;
};

type AnchorButtonProps = BaseButtonProps & {
    href: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    preserveScroll?: boolean;
};

type RegularButtonProps = BaseButtonProps &
    Omit<ButtonHTMLAttributes<HTMLButtonElement>, keyof BaseButtonProps | 'href'> & {
        href?: never;
    };

export type ButtonProps = AnchorButtonProps | RegularButtonProps;

function isLinkProps(props: ButtonProps): props is AnchorButtonProps {
    return 'href' in props && typeof props.href === 'string';
}

export const Button = forwardRef<HTMLButtonElement | HTMLAnchorElement, ButtonProps>(
    function Button(props, ref) {
        const {
            variant = 'primary',
            size = 'md',
            loading = false,
            leadingIcon,
            trailingIcon,
            fullWidth = false,
            className = '',
            children,
        } = props;

        const classes = [
            baseClasses,
            variantClasses[variant],
            sizeClasses[size],
            fullWidth ? 'w-full' : '',
            className,
        ]
            .filter(Boolean)
            .join(' ');

        const inner = (
            <>
                {loading ? (
                    <span
                        className="inline-block size-4 border-2 border-current border-r-transparent rounded-full animate-spin"
                        aria-hidden="true"
                    />
                ) : (
                    leadingIcon
                )}
                {children}
                {!loading && trailingIcon}
            </>
        );

        if (isLinkProps(props)) {
            return (
                <Link
                    ref={ref as React.Ref<HTMLAnchorElement>}
                    href={props.href}
                    method={props.method}
                    preserveScroll={props.preserveScroll}
                    className={classes}
                    aria-busy={loading || undefined}
                    aria-disabled={loading || undefined}
                >
                    {inner}
                </Link>
            );
        }

        const { variant: _v, size: _s, loading: _l, leadingIcon: _li, trailingIcon: _ti,
            fullWidth: _fw, className: _cn, children: _ch, ...rest } =
            props as RegularButtonProps;

        return (
            <button
                ref={ref as React.Ref<HTMLButtonElement>}
                {...rest}
                className={classes}
                disabled={rest.disabled || loading}
                aria-busy={loading || undefined}
                type={rest.type ?? 'button'}
            >
                {inner}
            </button>
        );
    }
);
