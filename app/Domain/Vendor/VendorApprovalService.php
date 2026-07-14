<?php

declare(strict_types=1);

namespace App\Domain\Vendor;

use App\Domain\Audit\AuditLogger;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use App\Models\VendorPackage;
use App\Models\VendorSubscription;
use App\Notifications\Vendor\VendorApprovedNotification;
use App\Notifications\Vendor\VendorRejectedNotification;
use App\Notifications\Vendor\VendorSuspendedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the vendor-status side effects so admins (Filament + future API)
 * can't accidentally approve without role/subscription/commission being set.
 */
final class VendorApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Approve a pending vendor. Wraps everything in a transaction:
     *  - flips status → approved
     *  - sets approved_at / approved_by
     *  - ensures vendor role is assigned
     *  - creates an active subscription if none exists
     *  - creates a default commission rule if none exists
     *  - audit-logs the approval
     *  - sends the notification (queueable)
     */
    public function approve(Vendor $vendor, VendorPackage $package, ?int $approverId = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $package, $approverId) {
            $before = ['status' => $vendor->status];

            $vendor->status       = Vendor::STATUS_APPROVED;
            $vendor->approved_at  = now();
            $vendor->approved_by  = $approverId ?? Auth::id();
            $vendor->rejection_reason = null;
            $vendor->save();

            // Ensure the underlying user has the 'vendor' role
            $user = $vendor->user;
            if ($user && ! $user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }

            // Activate (or create) a subscription
            if (! $vendor->activeSubscription) {
                VendorSubscription::create([
                    'vendor_id'         => $vendor->id,
                    'vendor_package_id' => $package->id,
                    'starts_at'         => now(),
                    'ends_at'           => $this->computeEndsAt($package),
                    'status'            => VendorSubscription::STATUS_ACTIVE,
                    'auto_renew'        => false,
                    'amount_paid_minor' => 0, // free trial / admin grant
                    'currency'          => $package->currency,
                ]);
            }

            // Create a default vendor-scoped commission rule if none exists
            if (! $vendor->commissionRules()->where('scope', VendorCommissionRule::SCOPE_VENDOR)->exists()) {
                VendorCommissionRule::create([
                    'vendor_id'        => $vendor->id,
                    'scope'            => VendorCommissionRule::SCOPE_VENDOR,
                    'scope_id'         => $vendor->id,
                    'product_type'     => 'any',
                    'payment_method'   => 'any',
                    'calculation_base' => 'selling_price',
                    'commission_type'  => VendorCommissionRule::TYPE_PERCENT,
                    'percent_value'    => $package->default_admin_commission_percent,
                    'currency'         => $package->currency,
                    'priority'         => 50,
                    'effective_from'   => now(),
                    'is_active'        => true,
                ]);
            }

            $this->audit->log(
                action: 'vendor.approved',
                subject: $vendor,
                before: $before,
                after: ['status' => $vendor->status, 'package' => $package->slug],
            );

            // Send notification (templated via Phase 1 notification_templates)
            try {
                $user?->notify(new VendorApprovedNotification($vendor));
            } catch (\Throwable) {
                // notifications are best-effort; never block approval on mail failure
            }

            return $vendor->fresh();
        });
    }

    public function reject(Vendor $vendor, string $reason, ?int $rejectorId = null): Vendor
    {
        $before = ['status' => $vendor->status];

        $vendor->status            = Vendor::STATUS_REJECTED;
        $vendor->rejection_reason  = $reason;
        $vendor->approved_at       = null;
        $vendor->approved_by       = $rejectorId ?? Auth::id();
        $vendor->save();

        $this->audit->log(
            action: 'vendor.rejected',
            subject: $vendor,
            before: $before,
            after: ['status' => $vendor->status, 'reason' => $reason],
        );

        try {
            $vendor->user?->notify(new VendorRejectedNotification($vendor, $reason));
        } catch (\Throwable) {}

        return $vendor->fresh();
    }

    public function suspend(Vendor $vendor, ?string $reason = null): Vendor
    {
        $before = ['status' => $vendor->status];

        $vendor->status = Vendor::STATUS_SUSPENDED;
        if ($reason) {
            $vendor->admin_notes = trim(($vendor->admin_notes ?? '') . "\n[suspended] " . $reason);
        }
        $vendor->save();

        $this->audit->log(
            action: 'vendor.suspended',
            subject: $vendor,
            before: $before,
            after: ['status' => $vendor->status, 'reason' => $reason],
        );

        try {
            $vendor->user?->notify(new VendorSuspendedNotification($vendor, $reason));
        } catch (\Throwable) {}

        return $vendor->fresh();
    }

    public function reopen(Vendor $vendor): Vendor
    {
        $before = ['status' => $vendor->status];
        $vendor->status = Vendor::STATUS_PENDING;
        $vendor->save();
        $this->audit->log('vendor.reopened', $vendor, $before, ['status' => $vendor->status]);
        return $vendor->fresh();
    }

    private function computeEndsAt(VendorPackage $package): ?\Carbon\Carbon
    {
        return match ($package->billing_cycle) {
            'monthly'  => now()->addMonth(),
            'yearly'   => now()->addYear(),
            'lifetime' => null,
            default    => now()->addMonth(),
        };
    }
}
