<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Payout\PayoutService;
use App\Domain\Payout\VendorWalletService;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 5 — vendor wallet + payout request flow.
 *
 * All routes are behind 'vendor.gate' middleware (EnsureVendor) so the
 * authenticated user has a vendor profile in $request->attributes->get('vendor').
 */
class VendorWalletController extends Controller
{
    public function __construct(
        private readonly VendorWalletService $wallet,
        private readonly PayoutService $payouts,
    ) {}

    public function show(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $balance = $this->wallet->balanceFor($vendor);

        $history = $vendor->payoutRequests()
            ->latest('requested_at')
            ->limit(50)
            ->get(['id', 'requested_amount_minor', 'currency', 'status', 'payout_method', 'transfer_reference', 'rejection_reason', 'requested_at', 'approved_at', 'rejected_at', 'paid_at']);

        return Inertia::render('Vendor/Wallet', [
            'wallet' => [
                'currency'          => $balance['currency'],
                'lifetime_earnings' => number_format($balance['lifetime_earnings_minor'] / 100, 3),
                'in_escrow'         => number_format($balance['in_escrow_minor'] / 100, 3),
                'releasing'         => number_format($balance['releasing_minor'] / 100, 3),
                'released'          => number_format($balance['released_minor'] / 100, 3),
                'reserved'          => number_format($balance['reserved_for_payout_minor'] / 100, 3),
                'paid_out'          => number_format($balance['paid_out_minor'] / 100, 3),
                'available'         => number_format($balance['available_balance_minor'] / 100, 3),
                'pending'           => number_format($balance['pending_balance_minor'] / 100, 3),
                'available_minor'   => $balance['available_balance_minor'],
            ],
            'history' => $history->map(fn (VendorPayoutRequest $r) => [
                'id'                 => $r->id,
                'amount'             => number_format($r->requested_amount_minor / 100, 3),
                'currency'           => $r->currency,
                'status'             => $r->status,
                'payout_method'      => $r->payout_method,
                'transfer_reference' => $r->transfer_reference,
                'rejection_reason'   => $r->rejection_reason,
                'requested_at'       => $r->requested_at?->toDateTimeString(),
                'approved_at'        => $r->approved_at?->toDateTimeString(),
                'rejected_at'        => $r->rejected_at?->toDateTimeString(),
                'paid_at'            => $r->paid_at?->toDateTimeString(),
            ])->values(),
        ]);
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount_minor'         => ['required', 'integer', 'min:1'],
            'payout_method'        => ['nullable', 'string', 'in:bank_transfer,other'],
            'iban'                 => ['nullable', 'string', 'max:50'],
            'bank_name'            => ['nullable', 'string', 'max:120'],
            'account_holder_name'  => ['nullable', 'string', 'max:120'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $payoutDetails = array_filter([
            'iban'                => $data['iban']                ?? null,
            'bank_name'           => $data['bank_name']           ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'notes'               => $data['notes']               ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $this->payouts->request(
                $vendor,
                (int) $data['amount_minor'],
                $payoutDetails,
                $data['payout_method'] ?? VendorPayoutRequest::METHOD_BANK_TRANSFER,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout request submitted. Admin will review it shortly.');
    }
}
