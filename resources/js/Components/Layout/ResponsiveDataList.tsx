import type { ReactNode } from 'react';

/**
 * Phase 11B.3 v11B.3.1 §27 — canonical responsive data-list pattern.
 *
 * ONE data source, two renderings:
 *   - md+: semantic <table> for keyboard/screen-reader
 *   - <md: card list (one row per record)
 *
 * The caller provides:
 *   - columns: definition for the desktop table (label + cell renderer)
 *   - renderCard: a function that renders one record as a mobile card
 *   - items: the array (SAME data serves both)
 *
 * This replaces the pre-v11B.3.1 pattern where Orders / Bookings / Tickets
 * shipped a compressed desktop table that squeezed on 320px viewports.
 */

export interface ColumnDef<T> {
    key: string;
    label: string;
    className?: string;
    /** Cell renderer for the desktop table. */
    render: (row: T) => ReactNode;
    /** When true, the column is hidden on md screens (kept for lg+). */
    hideOnMd?: boolean;
}

interface ResponsiveDataListProps<T> {
    items: T[];
    columns: ColumnDef<T>[];
    /** Per-row mobile card renderer. */
    renderCard: (row: T) => ReactNode;
    /** Stable React key extractor. */
    getKey: (row: T) => string | number;
    /** Optional empty-state component if items is empty. */
    emptyState?: ReactNode;
    testId?: string;
}

export function ResponsiveDataList<T>({
    items, columns, renderCard, getKey, emptyState, testId,
}: ResponsiveDataListProps<T>) {
    if (items.length === 0 && emptyState) {
        return <div data-testid={testId ? `${testId}-empty` : 'responsive-list-empty'}>{emptyState}</div>;
    }

    return (
        <div data-testid={testId ?? 'responsive-list'}>
            {/* Desktop / tablet — table */}
            <div className="hidden md:block bg-white border border-slate-200 rounded-xl overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200">
                        <tr>
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    scope="col"
                                    className={`px-4 py-3 text-start font-medium text-slate-700 ${
                                        col.hideOnMd ? 'hidden lg:table-cell' : ''
                                    } ${col.className ?? ''}`}
                                >
                                    {col.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {items.map((row) => (
                            <tr key={getKey(row)} className="hover:bg-slate-50">
                                {columns.map((col) => (
                                    <td
                                        key={col.key}
                                        className={`px-4 py-3 align-top text-slate-800 ${
                                            col.hideOnMd ? 'hidden lg:table-cell' : ''
                                        }`}
                                    >
                                        {col.render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile — card list */}
            <ul className="md:hidden space-y-3">
                {items.map((row) => (
                    <li key={getKey(row)} data-testid="responsive-list-card">
                        {renderCard(row)}
                    </li>
                ))}
            </ul>
        </div>
    );
}
