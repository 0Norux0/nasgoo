import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface PayoutRow {
    id: number;
    amount: string;
    currency: string;
    status: 'pending' | 'approved' | 'rejected' | 'paid';
    payout_method: string;
    transfer_reference: string | null;
    rejection_reason: string | null;
    requested_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    paid_at: string | null;
}

interface Wallet {
    currency: string;
    lifetime_earnings: string;
    in_escrow: string;
    releasing: string;
    released: string;
    reserved: string;
    paid_out: string;
    available: string;
    pending: string;
    available_minor: number;
}

interface Props {
    wallet: Wallet;
    history: PayoutRow[];
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-sky-100 text-sky-800',
    rejected: 'bg-rose-100 text-rose-800',
    paid: 'bg-emerald-100 text-emerald-800',
};

export default function VendorWallet({ wallet, history }: Props) {
    const page = usePage<SharedProps>();
    const flashSuccess = (page.props.flash as { success?: string } | undefined)?.success;
    const flashError = (page.props.flash as { error?: string } | undefined)?.error;
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        amount_minor: wallet.available_minor,
        payout_method: 'bank_transfer',
        iban: '',
        bank_name: '',
        account_holder_name: '',
        notes: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/vendor/wallet/payouts', {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowForm(false); },
        });
    }

    return (
        <VendorLayout title="Wallet & Payouts">
            <div className="max-w-6xl mx-auto px-4 py-8">

                {flashSuccess && <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{flashSuccess}</div>}
                {flashError && <div className="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded text-sm">{flashError}</div>}

                {/* Balance summary */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    <Stat label="Available" value={wallet.available} currency={wallet.currency} highlight />
                    <Stat label="Pending" value={wallet.pending} currency={wallet.currency} sub="In escrow + releasing" />
                    <Stat label="Lifetime" value={wallet.lifetime_earnings} currency={wallet.currency} />
                    <Stat label="Paid out" value={wallet.paid_out} currency={wallet.currency} />
                </div>

                <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6">
                    <h2 className="font-medium text-slate-900 mb-3">Balance breakdown</h2>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                        <BreakdownRow label="In escrow (paid, not delivered)" value={`${wallet.in_escrow} ${wallet.currency}`} />
                        <BreakdownRow label="Releasing (delivered, in cooling-off)" value={`${wallet.releasing} ${wallet.currency}`} />
                        <BreakdownRow label="Released (after cooling-off)" value={`${wallet.released} ${wallet.currency}`} />
                        <BreakdownRow label="Reserved for pending/approved payouts" value={`${wallet.reserved} ${wallet.currency}`} />
                        <BreakdownRow label="Paid out lifetime" value={`${wallet.paid_out} ${wallet.currency}`} />
                        <BreakdownRow label="Available for new payout" value={`${wallet.available} ${wallet.currency}`} bold />
                    </div>
                </div>

                {/* Request payout — v6.2: always visible, disabled when balance=0 with explanation */}
                <div className="bg-white border border-slate-200 rounded-lg p-4 mb-6">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                        <h2 className="font-medium text-slate-900">Request a payout</h2>
                        {wallet.available_minor > 0 ? (
                            !showForm && (
                                <button onClick={() => setShowForm(true)}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded">
                                    New request
                                </button>
                            )
                        ) : (
                            <span className="text-xs text-slate-500 italic">Available balance is zero — see below</span>
                        )}
                    </div>

                    {/* v6.2 — explain WHY available is zero (instead of just "no available balance yet").
                        Show the breakdown so the vendor knows exactly when funds release. */}
                    {wallet.available_minor <= 0 && (
                        <div className="mt-3 p-3 bg-amber-50 border border-amber-200 rounded text-sm">
                            <p className="font-medium text-amber-900 mb-1">No funds available for payout right now.</p>
                            <p className="text-amber-800">Earnings become available after this sequence:</p>
                            <ol className="list-decimal ml-5 mt-1 text-amber-800 space-y-0.5">
                                <li>Customer pays for the order.</li>
                                <li>Order is marked delivered (by admin or vendor).</li>
                                <li>A 7-day cooling-off period elapses (covers refund window).</li>
                            </ol>
                            <div className="mt-2 grid grid-cols-2 gap-1 text-xs">
                                <span className="text-amber-800">Currently in escrow (paid, not delivered):</span>
                                <span className="text-amber-900 font-medium">{wallet.in_escrow} {wallet.currency}</span>
                                <span className="text-amber-800">Currently releasing (delivered, in cooling-off):</span>
                                <span className="text-amber-900 font-medium">{wallet.releasing} {wallet.currency}</span>
                                <span className="text-amber-800">Reserved by pending/approved payout requests:</span>
                                <span className="text-amber-900 font-medium">{wallet.reserved} {wallet.currency}</span>
                            </div>
                            {Number(wallet.reserved) > 0 && (
                                <p className="mt-2 text-xs text-amber-800">
                                    You have a pending or approved payout request reserving your released balance.
                                    The reservation releases back if the request is rejected.
                                </p>
                            )}
                        </div>
                    )}

                    {/* Form: visible only when balance > 0 AND user clicked "New request" */}
                    {showForm && wallet.available_minor > 0 && (
                        <form onSubmit={submit} className="mt-3 grid gap-3 md:grid-cols-2">
                            <FormField label={`Amount (minor units; max ${wallet.available_minor})`} error={errors.amount_minor}>
                                <input type="number" min={1} max={wallet.available_minor}
                                    value={data.amount_minor} onChange={(e) => setData('amount_minor', Number(e.target.value))}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                                <span className="text-xs text-slate-500 block mt-0.5">
                                    Maximum payable: {wallet.available} {wallet.currency} ({wallet.available_minor} minor units).
                                </span>
                            </FormField>
                            <FormField label="Payout method" error={errors.payout_method}>
                                <select value={data.payout_method} onChange={(e) => setData('payout_method', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="other">Other</option>
                                </select>
                            </FormField>
                            <FormField label="Bank name" error={errors.bank_name}>
                                <input value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </FormField>
                            <FormField label="IBAN" error={errors.iban}>
                                <input value={data.iban} onChange={(e) => setData('iban', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </FormField>
                            <FormField label="Account holder name" error={errors.account_holder_name}>
                                <input value={data.account_holder_name} onChange={(e) => setData('account_holder_name', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </FormField>
                            <FormField label="Notes (optional)" error={errors.notes}>
                                <input value={data.notes} onChange={(e) => setData('notes', e.target.value)}
                                    className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            </FormField>
                            <div className="md:col-span-2 flex gap-2 mt-1">
                                <button type="submit" disabled={processing}
                                    className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                                    {processing ? 'Submitting…' : 'Submit request'}
                                </button>
                                <button type="button" onClick={() => setShowForm(false)} className="text-slate-600 text-sm px-3 py-1.5">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    )}
                </div>

                {/* History */}
                <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
                    <h2 className="font-medium text-slate-900 p-4 border-b border-slate-200">Payout history</h2>
                    {history.length === 0 ? (
                        <p className="p-4 text-sm text-slate-500">No payout requests yet.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th className="px-4 py-2">#</th>
                                    <th className="px-4 py-2">Amount</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Method</th>
                                    <th className="px-4 py-2">Requested</th>
                                    <th className="px-4 py-2">Resolved</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((r) => (
                                    <tr key={r.id} className="border-t border-slate-100">
                                        <td className="px-4 py-2 text-slate-500">#{r.id}</td>
                                        <td className="px-4 py-2 font-medium">{r.amount} {r.currency}</td>
                                        <td className="px-4 py-2">
                                            <span className={`inline-block px-2 py-0.5 rounded text-xs ${STATUS_COLORS[r.status] ?? ''}`}>
                                                {r.status}
                                            </span>
                                            {r.rejection_reason && <p className="text-xs text-rose-600 mt-1">{r.rejection_reason}</p>}
                                            {r.transfer_reference && <p className="text-xs text-slate-500 mt-1">Ref: {r.transfer_reference}</p>}
                                        </td>
                                        <td className="px-4 py-2 text-slate-600">{r.payout_method}</td>
                                        <td className="px-4 py-2 text-slate-500 text-xs">{r.requested_at}</td>
                                        <td className="px-4 py-2 text-slate-500 text-xs">
                                            {r.paid_at ?? r.rejected_at ?? r.approved_at ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </VendorLayout>
    );
}

function Stat({ label, value, currency, highlight, sub }: { label: string; value: string; currency: string; highlight?: boolean; sub?: string }) {
    return (
        <div className={`border rounded-lg p-3 ${highlight ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 bg-white'}`}>
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`mt-1 text-lg font-semibold ${highlight ? 'text-indigo-900' : 'text-slate-900'}`}>
                {value} <span className="text-sm font-normal text-slate-500">{currency}</span>
            </p>
            {sub && <p className="text-xs text-slate-400 mt-0.5">{sub}</p>}
        </div>
    );
}

function BreakdownRow({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
    return (
        <div className={`flex justify-between border-b border-slate-100 pb-1 ${bold ? 'font-semibold text-slate-900' : 'text-slate-700'}`}>
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}

function FormField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="text-xs text-slate-600 block">
            <span className="block mb-1">{label}</span>
            {children}
            {error && <span className="block mt-1 text-rose-600">{error}</span>}
        </label>
    );
}
