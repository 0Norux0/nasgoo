import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combines clsx + tailwind-merge so duplicate Tailwind utilities
 * cancel cleanly (later wins).
 *
 * Usage: cn('px-2 py-1', condition && 'px-4') → 'py-1 px-4'
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
