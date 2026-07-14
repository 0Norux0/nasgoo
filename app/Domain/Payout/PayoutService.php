<?php

declare(strict_types=1);

namespace App\Domain\Payout;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayoutService
{
    public function __construct(
        private readonly VendorWalletService $wallet,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Vendor creates a payout request.
     *
     * Validates that the requested amount is positive and ≤ available balance
     * (already net of pending/approved requests, so concurrent requests can't
     * double-spend).
     */
    public function request(Vendor $vendor, int $amountMinor, array $payoutDetails, string $method = VendorPayoutRequest::METHOD_BANK_TRANSFER): VendorPayoutRequest
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Payout amount must be greater than zero.');
        }

        return DB::transaction(function () use ($vendor, $amountMinor, $payoutDetails, $method) {
            $balance = $this->wallet->balanceFor($vendor);
            if ($amountMinor > $balance['available_balance_minor']) {
                throw new RuntimeException(sprintf(
                    'Requested amount (%d) exceeds available balance (%d).',
                    $amountMinor,
                    $balance['available_balance_minor'],
                ));
            }

            $request = VendorPayoutRequest::create([
                'vendor_id'              => $vendor->id,
                'requested_amount_minor' => $amountMinor,
                'currency'               => $balance['currency'],
                'status'                 => VendorPayoutRequest::STATUS_PENDING,
                'payout_method'          => $method,
                'payout_details'         => $payoutDetails,
                'requested_at'           => now(),
            ]);

            $this->audit->log('vendor_payout.requested', $request, null, $request->toArray(),
                notes: "Vendor {$vendor->business_name} requested " . number_format($amountMinor / 100, 2) . ' ' . $balance['currency']);

            return $request->fresh();
        });
    }

    /** Admin approves a pending request (reserves the amount for payout). */
    public function approve(VendorPayoutRequest $request, User $admin, ?string $notes = null): VendorPayoutRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException("Only pending requests can be approved (status: {$request->status}).");
        }

        return DB::transaction(function () use ($request, $admin, $notes) {
            $before = $request->toArray();
            $request->update([
                'status'       => VendorPayoutRequest::STATUS_APPROVED,
                'approved_at'  => now(),
                'processed_by' => $admin->id,
                'admin_notes'  => $notes,
            ]);
            $this->audit->log('vendor_payout.approved', $request, $before, $request->fresh()->toArray(), notes: $notes);
            return $request->fresh();
        });
    }

    /** Admin rejects a pending request with a reason. */
    public function reject(VendorPayoutRequest $request, User $admin, string $reason): VendorPayoutRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException("Only pending requests can be rejected (status: {$request->status}).");
        }

        return DB::transaction(function () use ($request, $admin, $reason) {
            $before = $request->toArray();
            $request->update([
                'status'           => VendorPayoutRequest::STATUS_REJECTED,
                'rejected_at'      => now(),
                'processed_by'     => $admin->id,
                'rejection_reason' => $reason,
            ]);
            $this->audit->log('vendor_payout.rejected', $request, $before, $request->fresh()->toArray(), notes: $reason);
            return $request->fresh();
        });
    }

    /** Admin marks an approved request as paid (transfer completed). */
    public function markPaid(VendorPayoutRequest $request, User $admin, string $transferReference): VendorPayoutRequest
    {
        if (! $request->isApproved()) {
            throw new RuntimeException("Only approved requests can be marked paid (status: {$request->status}).");
        }

        return DB::transaction(function () use ($request, $admin, $transferReference) {
            $before = $request->toArray();
            $request->update([
                'status'             => VendorPayoutRequest::STATUS_PAID,
                'paid_at'            => now(),
                'processed_by'       => $admin->id,
                'transfer_reference' => $transferReference,
            ]);
            $this->audit->log('vendor_payout.paid', $request, $before, $request->fresh()->toArray(),
                notes: "Transfer reference: {$transferReference}");
            return $request->fresh();
        });
    }
}
