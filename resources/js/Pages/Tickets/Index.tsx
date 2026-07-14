import { Head, Link } from '@inertiajs/react';
import { MessageCircle, Plus } from 'lucide-react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { PageContainer, PageHeader, EmptyState } from '@/Components/Layout/PageContainer';
import { ResponsiveDataList, type ColumnDef } from '@/Components/Layout/ResponsiveDataList';

interface Ticket {
    id: number;
    number: string;
    subject: string;
    ticket_type: string;
    priority: string;
    status: string;
    last_replied_at: string | null;
    created_at: string;
    order: { number: string } | null;
}

interface Props { tickets: { data: Ticket[]; links: unknown[] } }

const STATUS_COLORS: Record<string, string> = {
    open:            'bg-blue-100 text-blue-800',
    in_progress:     'bg-amber-100 text-amber-800',
    awaiting_reply:  'bg-amber-100 text-amber-800',
    resolved:        'bg-emerald-100 text-emerald-800',
    closed:          'bg-slate-100 text-slate-700',
};

const PRIORITY_COLORS: Record<string, string> = {
    urgent: 'text-rose-700',
    high:   'text-orange-700',
    normal: 'text-slate-600',
    low:    'text-slate-500',
};

/**
 * Phase 11B.3 v11B.3.1 §26 — Support responsive redesign.
 */
export default function TicketsIndex({ tickets }: Props) {
    const columns: ColumnDef<Ticket>[] = [
        {
            key: 'subject',
            label: 'Subject',
            render: (t) => (
                <Link
                    href={`/tickets/${t.id}`}
                    className="text-indigo-600 hover:underline font-medium"
                >
                    {t.subject}
                </Link>
            ),
        },
        {
            key: 'number',
            label: 'Number',
            render: (t) => <span className="font-mono text-xs text-slate-500">{t.number}</span>,
        },
        {
            key: 'type',
            label: 'Type',
            render: (t) => <span className="text-slate-600 capitalize">{t.ticket_type.replace(/_/g, ' ')}</span>,
            hideOnMd: true,
        },
        {
            key: 'priority',
            label: 'Priority',
            render: (t) => <span className={`text-xs font-medium capitalize ${PRIORITY_COLORS[t.priority] ?? ''}`}>{t.priority}</span>,
        },
        {
            key: 'status',
            label: 'Status',
            render: (t) => (
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[t.status] ?? 'bg-slate-100 text-slate-700'}`}>
                    {t.status.replace(/_/g, ' ')}
                </span>
            ),
        },
        {
            key: 'updated',
            label: 'Last activity',
            render: (t) => <span className="text-slate-500 text-xs">{t.last_replied_at ?? t.created_at}</span>,
            hideOnMd: true,
        },
    ];

    const renderCard = (t: Ticket) => (
        <article
            className="bg-white border border-slate-200 rounded-xl p-4"
            data-testid="ticket-mobile-card"
        >
            <div className="flex items-start justify-between gap-3 mb-2">
                <Link
                    href={`/tickets/${t.id}`}
                    className="text-sm font-semibold text-indigo-600 hover:underline"
                >
                    {t.subject}
                </Link>
                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${STATUS_COLORS[t.status] ?? 'bg-slate-100 text-slate-700'}`}>
                    {t.status.replace(/_/g, ' ')}
                </span>
            </div>
            <div className="text-xs text-slate-500 mb-2 font-mono">{t.number}</div>
            <dl className="grid grid-cols-2 gap-y-1 text-xs text-slate-600 mb-3">
                <dt className="text-slate-500">Type</dt>
                <dd className="text-end text-slate-800 capitalize">{t.ticket_type.replace(/_/g, ' ')}</dd>
                <dt className="text-slate-500">Priority</dt>
                <dd className={`text-end font-medium capitalize ${PRIORITY_COLORS[t.priority] ?? ''}`}>{t.priority}</dd>
                <dt className="text-slate-500">Last update</dt>
                <dd className="text-end text-slate-800">{t.last_replied_at ?? t.created_at}</dd>
            </dl>
            <Link
                href={`/tickets/${t.id}`}
                className="inline-flex items-center justify-center w-full text-sm font-medium text-indigo-600 border border-indigo-200 rounded-md py-2 hover:bg-indigo-50"
            >
                View ticket
            </Link>
        </article>
    );

    return (
        <StorefrontLayout>
            <Head title="My Support Tickets" />
            <PageContainer>
                <PageHeader
                    title="My support tickets"
                    testId="tickets-page-title"
                    actions={
                        <Link
                            href="/tickets/create"
                            className="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm font-medium"
                            data-testid="create-ticket-button"
                        >
                            <Plus size={14} aria-hidden="true" />
                            New ticket
                        </Link>
                    }
                />
                <ResponsiveDataList
                    items={tickets.data}
                    columns={columns}
                    renderCard={renderCard}
                    getKey={(t) => t.id}
                    emptyState={
                        <EmptyState
                            title="No tickets yet"
                            description="Create a ticket if you need help."
                            icon={<MessageCircle size={32} aria-hidden="true" />}
                            action={
                                <Link
                                    href="/tickets/create"
                                    className="inline-block rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700"
                                >
                                    New ticket
                                </Link>
                            }
                            testId="tickets-empty-state"
                        />
                    }
                    testId="tickets-list"
                />
            </PageContainer>
        </StorefrontLayout>
    );
}
