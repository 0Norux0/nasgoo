import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

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
    messages: Message[];
}
interface Props { ticket: Ticket }

export default function VendorTicketsShow({ ticket }: Props) {
    const form = useForm({ body: '' });
    const submit = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        form.post(`/vendor/tickets/${ticket.id}/reply`, {
            onSuccess: () => form.reset('body'),
        });
    };
    const isClosed = ticket.status === 'closed' || ticket.status === 'resolved';

    return (
        <VendorLayout title={`Ticket #${ticket.number}`}>
            <Head title={`Ticket #${ticket.number}`} />
            <div className="max-w-3xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-2">#{ticket.number}</h1>
                <p className="text-lg text-slate-700 mb-4">{ticket.subject}</p>
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
                    <form onSubmit={submit} className="bg-white border rounded p-4">
                        <label className="block text-sm font-medium mb-2">Reply as vendor</label>
                        <textarea
                            rows={4}
                            className="w-full border rounded p-2 mb-2"
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            data-testid="vendor-ticket-reply"
                        />
                        {form.errors.body && <p className="text-red-600 text-sm">{form.errors.body}</p>}
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        >
                            {form.processing ? 'Posting...' : 'Post Reply'}
                        </button>
                    </form>
                )}
            </div>
        </VendorLayout>
    );
}
