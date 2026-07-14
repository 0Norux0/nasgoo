import { Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { PageContainer, PageHeader, EmptyState } from '@/Components/Layout/PageContainer';
import { ResponsiveDataList, type ColumnDef } from '@/Components/Layout/ResponsiveDataList';

interface OrderRow {
    id: number;
    number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    total: string;
    currency: string;
    items_count: number;
    placed_at: string | null;
}

interface Props {
    orders: {
        data: OrderRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
}

/**
 * Phase 11B.3 v11B.3.1 §23 — My Orders responsive redesign.
 *
 * Pre-v11B.3.1: compressed desktop table that squeezed 6 columns onto a
 * 320px viewport, forcing horizontal scroll.
 *
 * v11B.3.1: shared ResponsiveDataList primitive.
 *   - md+: semantic table with headers (same 6 columns)
 *   - <md: card list — each order stacks number/date/status/badges/total
 *
 * ONE data source (`orders.data`). No duplicated business logic.
 * Shared PageContainer + PageHeader + EmptyState for visual consistency
 * with Bookings and Support.
 */
export default function OrdersIndex({ orders }: Props) {
    const columns: ColumnDef<OrderRow>[] = [
        {
            key: 'number',
            label: 'Order',
            render: (o) => (
                <Link href={`/orders/${o.id}`} className="font-mono text-indigo-600 hover:underline">
                    {o.number}
                </Link>
            ),
        },
        {
            key: 'date',
            label: 'Date',
            render: (o) => <span className="text-slate-600">{o.placed_at ?? '—'}</span>,
        },
        {
            key: 'status',
            label: 'Status',
            render: (o) => <StatusBadge status={o.status} />,
        },
        {
            key: 'payment',
            label: 'Payment',
            render: (o) => <span className="text-slate-600 capitalize">{o.payment_status}</span>,
            hideOnMd: true,
        },
        {
            key: 'items',
            label: 'Items',
            className: 'text-end',
            render: (o) => <span className="text-slate-800">{o.items_count}</span>,
        },
        {
            key: 'total',
            label: 'Total',
            className: 'text-end',
            render: (o) => <span className="font-medium text-slate-900">{o.total} {o.currency}</span>,
        },
    ];

    const renderCard = (o: OrderRow) => (
        <article
            className="bg-white border border-slate-200 rounded-xl p-4"
            data-testid="order-mobile-card"
        >
            <div className="flex items-start justify-between gap-3 mb-2">
                <Link
                    href={`/orders/${o.id}`}
                    className="font-mono text-sm text-indigo-600 hover:underline"
                    data-testid="order-card-number"
                >
                    {o.number}
                </Link>
                <StatusBadge status={o.status} />
            </div>
            <dl className="grid grid-cols-2 gap-y-1 text-xs text-slate-600 mb-3">
                <dt className="text-slate-500">Placed</dt>
                <dd className="text-end text-slate-800">{o.placed_at ?? '—'}</dd>
                <dt className="text-slate-500">Payment</dt>
                <dd className="text-end text-slate-800 capitalize">{o.payment_status}</dd>
                <dt className="text-slate-500">Items</dt>
                <dd className="text-end text-slate-800">{o.items_count}</dd>
                <dt className="text-slate-500">Total</dt>
                <dd className="text-end font-semibold text-slate-900">{o.total} {o.currency}</dd>
            </dl>
            <Link
                href={`/orders/${o.id}`}
                className="inline-flex items-center justify-center w-full text-sm font-medium text-indigo-600 border border-indigo-200 rounded-md py-2 hover:bg-indigo-50"
                data-testid="order-card-view-details"
            >
                View details
            </Link>
        </article>
    );

    return (
        <StorefrontLayout title="My orders">
            <PageContainer>
                <PageHeader
                    title="My orders"
                    description={`${orders.total} total`}
                    testId="orders-page-title"
                />
                <ResponsiveDataList
                    items={orders.data}
                    columns={columns}
                    renderCard={renderCard}
                    getKey={(o) => o.id}
                    emptyState={
                        <EmptyState
                            title="No orders yet"
                            description="Once you place an order it will appear here."
                            icon={<ShoppingBag size={32} aria-hidden="true" />}
                            action={
                                <Link
                                    href="/products"
                                    className="inline-block rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700"
                                >
                                    Browse products
                                </Link>
                            }
                            testId="orders-empty-state"
                        />
                    }
                    testId="orders-list"
                />

                {/* Pagination (preserved from pre-v11B.3.1) */}
                {orders.data.length > 0 && orders.links.length > 3 && (
                    <div className="mt-6 flex justify-center flex-wrap gap-1" data-testid="orders-pagination">
                        {orders.links.map((l) => (
                            <Link
                                key={l.label}
                                href={l.url ?? '#'}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                                className={`text-sm px-3 py-1 rounded ${
                                    l.active
                                        ? 'bg-indigo-600 text-white'
                                        : l.url
                                            ? 'text-slate-700 hover:bg-slate-100'
                                            : 'text-slate-400 cursor-default'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </StorefrontLayout>
    );
}

const STATUS_COLORS: Record<string, string> = {
    completed: 'bg-emerald-100 text-emerald-800',
    paid:      'bg-emerald-100 text-emerald-800',
    confirmed: 'bg-blue-100 text-blue-800',
    shipped:   'bg-blue-100 text-blue-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    pending_payment: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-rose-100 text-rose-800',
    refunded:  'bg-slate-100 text-slate-700',
};

function StatusBadge({ status }: { status: string }) {
    const cls = STATUS_COLORS[status] ?? 'bg-slate-100 text-slate-700';
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}`}>
            {status.replace(/_/g, ' ')}
        </span>
    );
}
