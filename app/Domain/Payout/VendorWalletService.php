<?php

declare(strict_types=1);

namespace App\Domain\Payout;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;

/**
 * Phase 5 — vendor wallet calculations.
 *
 * No separate wallet table — balances are computed live from
 * order_items.vendor_earning_minor + the existing earnings_release_at +
 * vendor_payout_requests state.
 *
 *   - lifetime_earnings:  sum of vendor_earning_minor on items where order.payment_status='paid'
 *   - in_escrow:          paid but order not yet delivered (delivery risk)
 *   - releasing:          delivered, but earnings_release_at > now() (cooling-off period)
 *   - released:           delivered AND earnings_release_at <= now()
 *   - reserved_for_payout: sum of pending + approved payout requests (not yet paid out)
 *   - paid_out:           sum of paid payout requests
 *   - available_balance:  released - reserved_for_payout - paid_out
 *   - pending_balance:    in_escrow + releasing
 */
final class VendorWalletService
{
    /**
     * @return array{
     *   currency: string,
     *   lifetime_earnings_minor: int,
     *   in_escrow_minor: int,
     *   releasing_minor: int,
     *   released_minor: int,
     *   reserved_for_payout_minor: int,
     *   paid_out_minor: int,
     *   available_balance_minor: int,
     *   pending_balance_minor: int,
     * }
     */
    public function balanceFor(Vendor $vendor): array
    {
        $currency = $vendor->payoutRequests()->latest()->value('currency') ?? 'KWD';

        $paidItems = OrderItem::query()
            ->where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q->where('payment_status', Order::PAY_PAID));

        $lifetime = (int) (clone $paidItems)->sum('vendor_earning_minor');

        // In-escrow: order paid but NOT delivered yet
        $inEscrow = (int) (clone $paidItems)
            ->whereHas('order', fn ($q) => $q->whereNull('delivered_at'))
            ->sum('vendor_earning_minor');

        // Delivered but earnings_release_at in the future
        $releasing = (int) OrderItem::query()
            ->where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q
                ->where('payment_status', Order::PAY_PAID)
                ->whereNotNull('delivered_at')
                ->where('earnings_release_at', '>', now())
            )->sum('vendor_earning_minor');

        // Delivered AND release time has passed
        $released = (int) OrderItem::query()
            ->where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q
                ->where('payment_status', Order::PAY_PAID)
                ->whereNotNull('delivered_at')
                ->where('earnings_release_at', '<=', now())
            )->sum('vendor_earning_minor');

        $reserved = (int) $vendor->payoutRequests()
            ->reservedForBalance()
            ->sum('requested_amount_minor');

        $paidOut = (int) $vendor->payoutRequests()
            ->where('status', VendorPayoutRequest::STATUS_PAID)
            ->sum('requested_amount_minor');

        $available = max(0, $released - $reserved - $paidOut);
        $pending   = $inEscrow + $releasing;

        return [
            'currency'                  => $currency,
            'lifetime_earnings_minor'   => $lifetime,
            'in_escrow_minor'           => $inEscrow,
            'releasing_minor'           => $releasing,
            'released_minor'            => $released,
            'reserved_for_payout_minor' => $reserved,
            'paid_out_minor'            => $paidOut,
            'available_balance_minor'   => $available,
            'pending_balance_minor'     => $pending,
        ];
    }
}
