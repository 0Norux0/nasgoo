import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface AvailabilityRow {
    id: number;
    day_of_week: number;
    day_name: string;
    start_time: string;
    end_time: string;
    slot_duration_minutes: number;
    max_bookings_per_slot: number;
    break_start_time: string | null;
    break_end_time: string | null;
    is_active: boolean;
}

interface BlockedDateRow {
    id: number;
    date: string;
    reason: string | null;
}

interface AvailabilityPageProps extends SharedProps {
    provider: { id: number; name: string };
    availabilities: AvailabilityRow[];
    blocked_dates: BlockedDateRow[];
    days_of_week: Record<string, string>;
}

export default function ProviderAvailability() {
    const { props } = usePage<AvailabilityPageProps>();
    const { provider, availabilities, blocked_dates, days_of_week } = props;

    const availForm = useForm({
        day_of_week: 1,
        start_time: '10:00',
        end_time: '20:00',
        slot_duration_minutes: 30,
        max_bookings_per_slot: 1,
        break_start_time: '13:00',
        break_end_time: '14:00',
        is_active: true,
    });

    const blockForm = useForm({ date: '', reason: '' });

    const submitAvail = (e: FormEvent) => {
        e.preventDefault();
        availForm.post(`/vendor/providers/${provider.id}/availability`);
    };

    const submitBlock = (e: FormEvent) => {
        e.preventDefault();
        blockForm.post(`/vendor/providers/${provider.id}/blocked-dates`, {
            onSuccess: () => blockForm.reset(),
        });
    };

    const unblock = (id: number) => {
        if (!confirm('Unblock this date?')) return;
        useForm({}).delete(`/vendor/providers/${provider.id}/blocked-dates/${id}`);
    };

    // Use the props.days_of_week so it's not unused; this also drives the rendered dropdown.
    const dayEntries = Object.entries(days_of_week);

    return (
        <VendorLayout title={`Availability — ${provider.name}`}>
            <Link href="/vendor/providers" className="text-sm text-blue-600 hover:underline">&larr; Providers</Link>
            <h1 className="text-2xl font-bold mt-2 mb-6">Availability — {provider.name}</h1>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Weekly schedule */}
                <div>
                    <h2 className="font-semibold mb-3">Weekly schedule</h2>
                    {availabilities.length === 0 ? (
                        <p className="text-sm text-gray-500 mb-3">No working days defined yet.</p>
                    ) : (
                        <table className="w-full text-sm border-collapse mb-4">
                            <thead>
                                <tr className="border-b text-left text-gray-600">
                                    <th className="py-2">Day</th>
                                    <th className="py-2">Hours</th>
                                    <th className="py-2">Slot</th>
                                    <th className="py-2">Max/slot</th>
                                    <th className="py-2">Break</th>
                                </tr>
                            </thead>
                            <tbody>
                                {availabilities.map(a => (
                                    <tr key={a.id} className="border-b">
                                        <td className="py-2">{a.day_name}</td>
                                        <td className="py-2">{a.start_time} – {a.end_time}</td>
                                        <td className="py-2">{a.slot_duration_minutes} min</td>
                                        <td className="py-2">{a.max_bookings_per_slot}</td>
                                        <td className="py-2">
                                            {a.break_start_time && a.break_end_time
                                                ? `${a.break_start_time} – ${a.break_end_time}`
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    <form onSubmit={submitAvail} className="border rounded p-3 bg-gray-50 space-y-2">
                        <h3 className="font-medium text-sm">Add / update a weekday</h3>
                        <select value={availForm.data.day_of_week}
                                onChange={e => availForm.setData('day_of_week', parseInt(e.target.value, 10))}
                                className="border rounded px-2 py-1 w-full">
                            {dayEntries.map(([n, name]) => (
                                <option key={n} value={n}>{name}</option>
                            ))}
                        </select>
                        <div className="grid grid-cols-2 gap-2">
                            <input type="time" value={availForm.data.start_time}
                                   onChange={e => availForm.setData('start_time', e.target.value)}
                                   className="border rounded px-2 py-1" required />
                            <input type="time" value={availForm.data.end_time}
                                   onChange={e => availForm.setData('end_time', e.target.value)}
                                   className="border rounded px-2 py-1" required />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <input type="number" value={availForm.data.slot_duration_minutes}
                                   onChange={e => availForm.setData('slot_duration_minutes', parseInt(e.target.value, 10) || 30)}
                                   placeholder="Slot min" className="border rounded px-2 py-1" min={5} />
                            <input type="number" value={availForm.data.max_bookings_per_slot}
                                   onChange={e => availForm.setData('max_bookings_per_slot', parseInt(e.target.value, 10) || 1)}
                                   placeholder="Max/slot" className="border rounded px-2 py-1" min={1} />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <input type="time" value={availForm.data.break_start_time}
                                   onChange={e => availForm.setData('break_start_time', e.target.value)}
                                   placeholder="Break start" className="border rounded px-2 py-1" />
                            <input type="time" value={availForm.data.break_end_time}
                                   onChange={e => availForm.setData('break_end_time', e.target.value)}
                                   placeholder="Break end" className="border rounded px-2 py-1" />
                        </div>
                        <button type="submit" disabled={availForm.processing}
                                className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                            {availForm.processing ? 'Saving…' : 'Save day'}
                        </button>
                    </form>
                </div>

                {/* Blocked dates */}
                <div>
                    <h2 className="font-semibold mb-3">Blocked dates (vacations / holidays)</h2>
                    {blocked_dates.length === 0 ? (
                        <p className="text-sm text-gray-500 mb-3">No blocked dates.</p>
                    ) : (
                        <ul className="space-y-1 mb-4">
                            {blocked_dates.map(b => (
                                <li key={b.id} className="flex justify-between border rounded px-3 py-2 text-sm">
                                    <span>{b.date} {b.reason && <span className="text-gray-500">— {b.reason}</span>}</span>
                                    <button onClick={() => unblock(b.id)} className="text-red-600 hover:underline text-xs">Unblock</button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form onSubmit={submitBlock} className="border rounded p-3 bg-gray-50 space-y-2">
                        <h3 className="font-medium text-sm">Block a date</h3>
                        <input type="date" value={blockForm.data.date}
                               onChange={e => blockForm.setData('date', e.target.value)}
                               className="border rounded px-2 py-1 w-full" required />
                        <input type="text" value={blockForm.data.reason}
                               onChange={e => blockForm.setData('reason', e.target.value)}
                               placeholder="Reason (optional, e.g. National Day)"
                               className="border rounded px-2 py-1 w-full" />
                        <button type="submit" disabled={blockForm.processing}
                                className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 disabled:opacity-50">
                            {blockForm.processing ? 'Blocking…' : 'Block date'}
                        </button>
                    </form>
                </div>
            </div>
        </VendorLayout>
    );
}
