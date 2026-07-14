<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\User;
use App\Models\VendorSubscription;

class VendorSubscriptionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }
    public function viewAny(User $user): bool { return $user->can('vendor_subscriptions.manage') || $user->can('vendors.view'); }
    public function view(User $user, VendorSubscription $sub): bool { return $this->viewAny($user) || $sub->vendor?->user_id === $user->id; }
    public function create(User $user): bool { return $user->can('vendor_subscriptions.manage'); }
    public function update(User $user, VendorSubscription $sub): bool { return $user->can('vendor_subscriptions.manage'); }
    public function delete(User $user, VendorSubscription $sub): bool { return $user->can('vendor_subscriptions.manage'); }
}
