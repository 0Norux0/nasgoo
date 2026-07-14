import { Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { SharedProps } from '@/types/inertia';

interface ProviderOption {
    id: number;
    name: string;
    specialization: string | null;
    bio: string | null;
}

interface SlotEntry {
    time: string;
    remaining: number;
}

interface ServiceDetail {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    currency: string;
    service_type: string;
    location_mode: string;
    duration_min: number;
    service_area: string | null;
    min_lead_time: number;
    allow_pick: boolean;
    requires_address: boolean;
    vendor: { id: number; name: string; slug: string } | null;
    providers: ProviderOption[];
}

interface ServiceShowPageProps extends SharedProps {
    service: ServiceDetail;
    slots_preview_provider_id: number | null;
    slots_preview: Record<string, SlotEntry[]>;
}

/**
 * Phase 8 v8.1 — improved service detail page with calendar-style
 * date picker.
 *
 * The Phase 8.0 version used a flat dropdown of dates. v8.1 renders a
 * 14-day calendar grid (similar to most appointment-booking sites)
 * where dates with NO available slots are visibly disabled, and the
 * customer can scan availability at a glance before committing to a
 * date. Time slots remain a button grid (kept the same — a clock-style
 * picker is genuinely worse for slot-based bookings since not every
 * minute is a valid choice).
 */
export default function ServicesShow() {
    const { props } = usePage<ServiceShowPageProps>();
    const { service, slots_preview, slots_preview_provider_id } = props;
    const auth = props.auth;

    const [selectedProvider, setSelectedProvider] = useState<number | null>(
        slots_preview_provider_id ?? service.providers[0]?.id ?? null
    );
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [selectedTime, setSelectedTime] = useState<string | null>(null);

    // v12.2.1 hook-dep fix: wrap in useMemo so the reference is stable
    // across renders. Without this, `slots_preview ?? {}` allocates a new
    // empty object every render when slots_preview is null, invalidating
    // the calendar useMemo below on every re-render.
    const slotsByDate: Record<string, SlotEntry[]> = useMemo(
        () => slots_preview ?? {},
        [slots_preview],
    );

    // Build a 14-day calendar starting today. For each day, look up
    // available slots in slotsByDate. Days with no entry → disabled.
    const calendar = useMemo(() => {
        const days: Array<{ date: string; label: string; weekday: string; hasSlots: boolean; slotCount: number }> = [];
        const today = new Date();
        for (let i = 0; i < 14; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            const iso = d.toISOString().slice(0, 10);
            const weekday = d.toLocaleDateString(undefined, { weekday: 'short' });
            const label = `${d.getDate()}`;
            const slots = slotsByDate[iso] ?? [];
            days.push({
                date: iso,
                label,
                weekday,
                hasSlots: slots.length > 0,
                slotCount: slots.length,
            });
        }
        return days;
    }, [slotsByDate]);

    const form = useForm({
        service_id: service.id,
        service_provider_id: selectedProvider,
        date: '',
        time: '',
        customer_notes: '',
        service_address: service.requires_address ? {
            recipient_name: '', phone: '', country: 'Kuwait',
            city: '', block: '', street: '', building: ''
        } : null,
    });

    // Phase 8 v8.4 — typed access to backend validation errors that don't
    // map to a useForm() field. ServiceBookingService::createBooking throws
    // ValidationException(['service' => '...']) for cross-cutting checks
    // (service not active, race condition where service was archived after
    // page load, etc.). Cast widens .errors to a string-keyed record without
    // weakening to `any` — values stay `string | undefined`.
    const bookingErrors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!selectedDate || !selectedTime) {
            alert('Please select a date and time.');
            return;
        }
        form.transform(data => ({
            ...data,
            service_provider_id: selectedProvider,
            date: selectedDate,
            time: selectedTime,
        }));
        form.post('/bookings');
    };

    const currentSlots = selectedDate ? (slotsByDate[selectedDate] ?? []) : [];

    return (
        <StorefrontLayout>
            <div className="max-w-4xl mx-auto p-6">
                <Link href="/services" className="text-sm text-blue-600 hover:underline">&larr; Back to services</Link>

                <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="md:col-span-2">
                        <h1 className="text-3xl font-bold">{service.name}</h1>
                        {service.vendor && (
                            <p className="text-gray-600 mt-1">by <Link href={`/vendors/${service.vendor.slug}`} className="text-blue-600 hover:underline">{service.vendor.name}</Link></p>
                        )}

                        {service.description && <p className="mt-4 whitespace-pre-line text-gray-800">{service.description}</p>}

                        <div className="mt-4 flex flex-wrap gap-2 text-sm">
                            <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded">{service.service_type.replace('_', ' ')}</span>
                            <span className="px-2 py-1 bg-purple-100 text-purple-700 rounded">{service.location_mode.replace('_', ' ')}</span>
                            <span className="px-2 py-1 bg-green-100 text-green-700 rounded">{service.duration_min} min</span>
                            {service.service_area && <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded">{service.service_area}</span>}
                        </div>

                        {service.providers.length > 0 && (
                            <div className="mt-6">
                                <h2 className="font-semibold mb-2">Available providers</h2>
                                <div className="space-y-2">
                                    {service.providers.map(p => (
                                        <label key={p.id} className={`block border rounded p-3 cursor-pointer ${selectedProvider === p.id ? 'border-blue-500 bg-blue-50' : ''}`}>
                                            <input type="radio" name="provider" checked={selectedProvider === p.id}
                                                   onChange={() => setSelectedProvider(p.id)} className="mr-2" />
                                            <span className="font-medium">{p.name}</span>
                                            {p.specialization && <span className="text-sm text-gray-600 ml-2">— {p.specialization}</span>}
                                            {p.bio && <p className="text-xs text-gray-500 mt-1">{p.bio}</p>}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <aside className="border rounded-lg p-4 bg-white shadow-sm">
                        <div className="text-2xl font-bold mb-2">{service.price} {service.currency}</div>
                        <div className="text-sm text-gray-500 mb-4">{service.duration_min} min</div>

                        {!auth?.user ? (
                            <Link href="/login" className="block w-full bg-blue-600 text-white text-center py-2 rounded hover:bg-blue-700">
                                Log in to book
                            </Link>
                        ) : (
                            <form onSubmit={submit}>
                                {/* Phase 8 v8.1 — 14-day calendar grid (replaces the
                                    Phase 8.0 dropdown). Dates without slots show
                                    disabled, dates with slots are clickable, and
                                    the selected date is highlighted in blue. */}
                                <label className="block text-sm font-medium mb-2">Pick a date</label>
                                <div className="grid grid-cols-7 gap-1 mb-4" data-testid="calendar-grid">
                                    {calendar.map(day => {
                                        const isSelected = day.date === selectedDate;
                                        return (
                                            <button
                                                key={day.date}
                                                type="button"
                                                disabled={!day.hasSlots}
                                                onClick={() => { setSelectedDate(day.date); setSelectedTime(null); }}
                                                className={[
                                                    'p-2 text-center rounded text-xs',
                                                    isSelected ? 'bg-blue-600 text-white' : '',
                                                    !isSelected && day.hasSlots ? 'border border-slate-200 hover:bg-slate-50' : '',
                                                    !day.hasSlots ? 'bg-slate-50 text-slate-300 cursor-not-allowed' : '',
                                                ].join(' ')}
                                                aria-label={`${day.date} — ${day.hasSlots ? day.slotCount + ' slots' : 'no availability'}`}>
                                                <div className="text-[10px] uppercase opacity-80">{day.weekday}</div>
                                                <div className="text-base font-semibold">{day.label}</div>
                                                {day.hasSlots && (
                                                    <div className={`text-[10px] mt-0.5 ${isSelected ? 'text-blue-100' : 'text-green-600'}`}>
                                                        {day.slotCount}
                                                    </div>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>

                                {selectedDate && currentSlots.length === 0 && (
                                    <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mb-3">
                                        No slots available on the selected date.
                                    </p>
                                )}

                                {selectedDate && currentSlots.length > 0 && (
                                    <>
                                        <label className="block text-sm font-medium mb-2">Pick a time</label>
                                        <div className="grid grid-cols-3 gap-2 mb-3" data-testid="time-grid">
                                            {currentSlots.map(s => (
                                                <button type="button" key={s.time}
                                                        onClick={() => setSelectedTime(s.time)}
                                                        className={`px-2 py-2 border rounded text-sm font-medium ${selectedTime === s.time ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50'}`}>
                                                    {s.time}
                                                </button>
                                            ))}
                                        </div>
                                    </>
                                )}

                                <textarea value={form.data.customer_notes}
                                          onChange={e => form.setData('customer_notes', e.target.value)}
                                          placeholder="Anything we should know? (optional)"
                                          className="border rounded w-full px-3 py-2 mb-3" rows={2} />

                                <button type="submit" disabled={!selectedDate || !selectedTime || form.processing}
                                        className="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:opacity-50 font-medium">
                                    {form.processing ? 'Booking…' : 'Confirm booking'}
                                </button>

                                {form.errors.time && <p className="text-red-600 text-sm mt-2">{form.errors.time}</p>}
                                {bookingErrors.service && <p className="text-red-600 text-sm mt-2">{bookingErrors.service}</p>}
                            </form>
                        )}
                    </aside>
                </div>
            </div>
        </StorefrontLayout>
    );
}
