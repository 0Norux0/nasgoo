import { Head, useForm } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface TypeOption { value: string; label: string }
interface Props {
    types: TypeOption[];
    priorities: TypeOption[];
}

export default function TicketsCreate({ types, priorities }: Props) {
    const form = useForm({
        ticket_type: types[0]?.value ?? 'general_inquiry',
        subject: '',
        body: '',
        priority: 'normal',
        order_id: null as number | null,
        booking_id: null as number | null,
        vendor_id: null as number | null,
        product_id: null as number | null,
    });

    const submit = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        form.post('/tickets');
    };

    return (
        <StorefrontLayout>
            <Head title="New Support Ticket" />
            <div className="max-w-2xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">New Support Ticket</h1>
                <form onSubmit={submit} className="space-y-4 bg-white border rounded p-6">
                    <div>
                        <label className="block text-sm font-medium mb-1">Issue type</label>
                        <select
                            className="w-full border rounded p-2"
                            value={form.data.ticket_type}
                            onChange={(e) => form.setData('ticket_type', e.target.value)}
                        >
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                        {form.errors.ticket_type && <p className="text-red-600 text-sm">{form.errors.ticket_type}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Priority</label>
                        <select
                            className="w-full border rounded p-2"
                            value={form.data.priority}
                            onChange={(e) => form.setData('priority', e.target.value)}
                        >
                            {priorities.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Subject</label>
                        <input
                            type="text"
                            className="w-full border rounded p-2"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                            data-testid="ticket-subject"
                        />
                        {form.errors.subject && <p className="text-red-600 text-sm">{form.errors.subject}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Describe your issue</label>
                        <textarea
                            rows={6}
                            className="w-full border rounded p-2"
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            data-testid="ticket-body"
                        />
                        {form.errors.body && <p className="text-red-600 text-sm">{form.errors.body}</p>}
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {form.processing ? 'Submitting...' : 'Submit Ticket'}
                    </button>
                </form>
            </div>
        </StorefrontLayout>
    );
}
