import { Link, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { PageContainer, PageHeader, EmptyState } from '@/Components/Layout/PageContainer';
import { ResponsiveDataList, type ColumnDef } from '@/Components/Layout/ResponsiveDataList';
import type { SharedProps } from '@/types/inertia';

interface BookingRow {
    id: number;
    number: string;
    status: string;
    service_name: string | null;
    service_slug: string | null;
    vendor_name: string | null;
    provider_name: string | null;
    date: string | null;
    time: string;
    duration_min: number;
    price: string;
    currency: string;
    is_active: boolean;
}

interface BookingsIndexPageProps extends SharedProps {
    bookings: { data: BookingRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}

const STATUS_COLORS: Record<string, string> = {
    confirmed: 'bg-emerald-100 text-emerald-800',
    accepted:  'bg-emerald-100 text-emerald-800',
    completed: 'bg-emerald-100 text-emerald-800',
    pending:         'bg-amber-100 text-amber-800',
    pending_payment: 'bg-amber-100 text-amber-800',
    rejected:  'bg-rose-100 text-rose-800',
    cancelled: 'bg-rose-100 text-rose-800',
    no_show:   'bg-rose-100 text-rose-800',
};

function StatusBadge({ status }: { status: string }) {
    const cls = STATUS_COLORS[status] ?? 'bg-slate-100 text-slate-700';
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}`}>
            {status.replace(/_/g, ' ')}
        </span>
    );
}

/**
 * Phase 11B.3 v11B.3.1 §25 — Bookings responsive redesign.
 */
export default function BookingsIndex() {
    const { props } = usePage<BookingsIndexPageProps>();
    const { bookings } = props;

    const columns: ColumnDef<BookingRow>[] = [
        {
            key: 'service',
            label: 'Service',
            render: (b) => (
                <Link
                    href={`/bookings/${b.id}`}
                    className="text-indigo-600 hover:underline font-medium"
                >
                    {b.service_name ?? '—'}
                </Link>
            ),
        },
        {
            key: 'provider',
            label: 'Provider',
            render: (b) => <span className="text-slate-600">{b.provider_name ?? b.vendor_name ?? '—'}</span>,
        },
        {
            key: 'when',
            label: 'When',
            render: (b) => <span className="text-slate-600">{b.date ?? '—'} {b.time}</span>,
        },
        {
            key: 'status',
            label: 'Status',
            render: (b) => <StatusBadge status={b.status} />,
        },
        {
            key: 'total',
            label: 'Total',
            className: 'text-end',
            render: (b) => <span className="font-medium text-slate-900">{b.price} {b.currency}</span>,
        },
    ];

    const renderCard = (b: BookingRow) => (
        <article
            className="bg-white border border-slate-200 rounded-xl p-4"
            data-testid="booking-mobile-card"
        >
            <div className="flex items-start justify-between gap-3 mb-2">
                <Link
                    href={`/bookings/${b.id}`}
                    className="text-sm font-semibold text-indigo-600 hover:underline"
                >
                    {b.service_name ?? '—'}
                </Link>
                <StatusBadge status={b.status} />
            </div>
            <dl className="grid grid-cols-2 gap-y-1 text-xs text-slate-600 mb-3">
                <dt className="text-slate-500">Provider</dt>
                <dd className="text-end text-slate-800">{b.provider_name ?? b.vendor_name ?? '—'}</dd>
                <dt className="text-slate-500">Date</dt>
                <dd className="text-end text-slate-800">{b.date ?? '—'}</dd>
                <dt className="text-slate-500">Time</dt>
                <dd className="text-end text-slate-800">{b.time} ({b.duration_min}m)</dd>
                <dt className="text-slate-500">Total</dt>
                <dd className="text-end font-semibold text-slate-900">{b.price} {b.currency}</dd>
            </dl>
            <Link
                href={`/bookings/${b.id}`}
                className="inline-flex items-center justify-center w-full text-sm font-medium text-indigo-600 border border-indigo-200 rounded-md py-2 hover:bg-indigo-50"
            >
                View details
            </Link>
        </article>
    );

    return (
        <StorefrontLayout title="My bookings">
            <PageContainer>
                <PageHeader
                    title="My bookings"
                    description={`${bookings.data.length} booking${bookings.data.length === 1 ? '' : 's'}`}
                    testId="bookings-page-title"
                />
                <ResponsiveDataList
                    items={bookings.data}
                    columns={columns}
                    renderCard={renderCard}
                    getKey={(b) => b.id}
                    emptyState={
                        <EmptyState
                            title="No bookings yet"
                            description="Book a service and it will appear here."
                            icon={<Calendar size={32} aria-hidden="true" />}
                            action={
                                <Link
                                    href="/services"
                                    className="inline-block rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700"
                                >
                                    Browse services
                                </Link>
                            }
                            testId="bookings-empty-state"
                        />
                    }
                    testId="bookings-list"
                />
            </PageContainer>
        </StorefrontLayout>
    );
}
