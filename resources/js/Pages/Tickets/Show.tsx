import { Head, useForm } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface Message {
    id: number;
    body: string;
    author_role: string;
    created_at: string;
    user: { id: number; name: string } | null;
}
interface Ticket {
    id: number;
    number: string;
    subject: string;
    status: string;
    priority: string;
    ticket_type: string;
    created_at: string;
    messages: Message[];
}

interface Props { ticket: Ticket }

export default function TicketsShow({ ticket }: Props) {
    const replyForm = useForm({ body: '' });
    const closeForm = useForm({});

    const submit = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        replyForm.post(`/tickets/${ticket.id}/reply`, {
            onSuccess: () => replyForm.reset('body'),
        });
    };

    const close = () => closeForm.post(`/tickets/${ticket.id}/close`);

    const isClosed = ticket.status === 'closed' || ticket.status === 'resolved';

    return (
        <StorefrontLayout>
            <Head title={`Ticket #${ticket.number}`} />
            <div className="max-w-3xl mx-auto p-6">
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">#{ticket.number}</h1>
                        <p className="text-lg text-slate-700">{ticket.subject}</p>
                        <p className="text-sm text-slate-500">
                            {ticket.ticket_type.replace('_', ' ')} · priority: {ticket.priority}
                        </p>
                    </div>
                    <span className={`text-xs px-2 py-1 rounded ${
                        isClosed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                    }`}>{ticket.status}</span>
                </div>

                <div className="space-y-3 mb-6">
                    {ticket.messages.map((m) => (
                        <div
                            key={m.id}
                            className={`border rounded p-4 ${
                                m.author_role === 'customer' ? 'bg-white' : 'bg-blue-50'
                            }`}
                        >
                            <div className="flex justify-between text-xs text-slate-500 mb-2">
                                <span>{m.user?.name ?? 'Unknown'} · {m.author_role}</span>
                                <span>{new Date(m.created_at).toLocaleString()}</span>
                            </div>
                            <p className="whitespace-pre-wrap text-sm">{m.body}</p>
                        </div>
                    ))}
                </div>

                {!isClosed && (
                    <>
                        <form onSubmit={submit} className="mb-4 bg-white border rounded p-4">
                            <label className="block text-sm font-medium mb-2">Add reply</label>
                            <textarea
                                rows={4}
                                className="w-full border rounded p-2 mb-2"
                                value={replyForm.data.body}
                                onChange={(e) => replyForm.setData('body', e.target.value)}
                                data-testid="ticket-reply-body"
                            />
                            {replyForm.errors.body && <p className="text-red-600 text-sm">{replyForm.errors.body}</p>}
                            <button
                                type="submit"
                                disabled={replyForm.processing}
                                className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                            >
                                {replyForm.processing ? 'Posting...' : 'Post Reply'}
                            </button>
                        </form>
                        <button
                            onClick={close}
                            className="text-sm text-slate-500 hover:text-slate-800"
                            data-testid="close-ticket-button"
                        >
                            Close this ticket
                        </button>
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
