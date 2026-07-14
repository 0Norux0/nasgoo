<?php
declare(strict_types=1);
namespace App\Domain\Promotion;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;

/**
 * Phase 9 — validates a coupon code against the current cart context.
 *
 * Returns a status DTO with success flag + error reason + computed
 * discount. Errors map 1:1 to user-facing messages so the cart page
 * can show "expired", "minimum order not met", etc. without leaking
 * internal logic.
 */
class CouponValidator
{
    public const OK = 'ok';
    public const NOT_FOUND = 'not_found';
    public const INACTIVE = 'inactive';
    public const NOT_STARTED = 'not_started';
    public const EXPIRED = 'expired';
    public const CURRENCY_MISMATCH = 'currency_mismatch';
    public const MIN_ORDER_NOT_MET = 'min_order_not_met';
    public const USAGE_LIMIT_REACHED = 'usage_limit_reached';
    public const PER_USER_LIMIT_REACHED = 'per_user_limit_reached';
    public const NOT_ASSIGNED_TO_USER = 'not_assigned_to_user';
    public const VENDOR_MISMATCH = 'vendor_mismatch';

    public static function reasonMessage(string $reason): string
    {
        return match ($reason) {
            self::OK => 'Coupon applied.',
            self::NOT_FOUND => 'Coupon code not found.',
            self::INACTIVE => 'This coupon is no longer active.',
            self::NOT_STARTED => 'This coupon is not yet active.',
            self::EXPIRED => 'This coupon has expired.',
            self::CURRENCY_MISMATCH => 'This coupon cannot be used with your cart currency.',
            self::MIN_ORDER_NOT_MET => 'Your order does not meet the minimum amount for this coupon.',
            self::USAGE_LIMIT_REACHED => 'This coupon has reached its total usage limit.',
            self::PER_USER_LIMIT_REACHED => 'You have already used this coupon the maximum number of times.',
            self::NOT_ASSIGNED_TO_USER => 'This coupon is not available to your account.',
            self::VENDOR_MISMATCH => 'This coupon only applies to products from a specific vendor.',
            default => 'This coupon cannot be applied.',
        };
    }

    /**
     * Validate `$code` against `$cart` for `$user`. Returns:
     *   [
     *     'ok' => bool,
     *     'reason' => string (one of the constants above),
     *     'discount_minor' => int,
     *     'coupon' => Coupon|null,
     *   ]
     */
    public static function validate(string $code, Cart $cart, User $user): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            return ['ok' => false, 'reason' => self::NOT_FOUND, 'discount_minor' => 0, 'coupon' => null];
        }

        if (! $coupon->is_active) {
            return ['ok' => false, 'reason' => self::INACTIVE, 'discount_minor' => 0, 'coupon' => $coupon];
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return ['ok' => false, 'reason' => self::NOT_STARTED, 'discount_minor' => 0, 'coupon' => $coupon];
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return ['ok' => false, 'reason' => self::EXPIRED, 'discount_minor' => 0, 'coupon' => $coupon];
        }
        if ($coupon->currency !== $cart->currency) {
            return ['ok' => false, 'reason' => self::CURRENCY_MISMATCH, 'discount_minor' => 0, 'coupon' => $coupon];
        }
        if ($coupon->min_order_minor !== null && $cart->subtotal_minor < $coupon->min_order_minor) {
            return ['ok' => false, 'reason' => self::MIN_ORDER_NOT_MET, 'discount_minor' => 0, 'coupon' => $coupon];
        }

        // Customer-specific coupon
        if ($coupon->assigned_user_id !== null && $coupon->assigned_user_id !== $user->id) {
            return ['ok' => false, 'reason' => self::NOT_ASSIGNED_TO_USER, 'discount_minor' => 0, 'coupon' => $coupon];
        }

        // Usage limits
        if ($coupon->usage_limit !== null) {
            $totalUsed = CouponUsage::where('coupon_id', $coupon->id)->count();
            if ($totalUsed >= $coupon->usage_limit) {
                return ['ok' => false, 'reason' => self::USAGE_LIMIT_REACHED, 'discount_minor' => 0, 'coupon' => $coupon];
            }
        }
        $userUsed = CouponUsage::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();
        if ($userUsed >= $coupon->per_user_limit) {
            return ['ok' => false, 'reason' => self::PER_USER_LIMIT_REACHED, 'discount_minor' => 0, 'coupon' => $coupon];
        }

        // Vendor-specific coupon — every cart line's product must be from that vendor
        if ($coupon->vendor_id !== null) {
            $vendorIds = $cart->items()->with('product:id,vendor_id')
                ->get()
                ->pluck('product.vendor_id')
                ->filter()
                ->unique();
            if ($vendorIds->isNotEmpty() && (! $vendorIds->contains($coupon->vendor_id) || $vendorIds->count() > 1)) {
                return ['ok' => false, 'reason' => self::VENDOR_MISMATCH, 'discount_minor' => 0, 'coupon' => $coupon];
            }
        }

        return [
            'ok' => true,
            'reason' => self::OK,
            'discount_minor' => $coupon->computeDiscountMinor((int) $cart->subtotal_minor),
            'coupon' => $coupon,
        ];
    }
}
