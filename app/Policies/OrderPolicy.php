<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    /**
     * Customer can view their own order; vendor can view any order that has
     * at least one item belonging to them; admin can view all.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->id === $order->user_id) return true;
        if ($user->can('orders.view.any')) return true;
        if ($user->hasRole('vendor') && $user->vendor) {
            return $order->items()->where('vendor_id', $user->vendor->id)->exists();
        }
        return false;
    }

    public function confirm(User $user, Order $order): bool { return $user->can('orders.confirm'); }
    public function ship(User $user, Order $order): bool    { return $user->can('orders.ship'); }
    public function deliver(User $user, Order $order): bool { return $user->can('orders.deliver'); }
    public function cancel(User $user, Order $order): bool
    {
        if ($user->can('orders.cancel')) return true;
        // Customer can self-cancel if still pending payment / pending fulfillment
        return $user->id === $order->user_id
            && in_array($order->status, [Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, Order::STATUS_CONFIRMED], true);
    }
    public function refund(User $user, Order $order): bool { return $user->can('orders.refund'); }
}
