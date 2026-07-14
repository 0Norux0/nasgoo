<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    /**
     * Super admin bypasses ALL ability checks.
     * (Spatie's HasRoles adds `hasRole()` to the user.)
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('vendors.view');
    }

    public function view(User $user, Vendor $vendor): bool
    {
        // Admin staff can view all; vendors can view their own
        return $user->can('vendors.view')
            || $vendor->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Any authenticated user can submit a vendor application,
        // but only one per user (enforced in the controller).
        return true;
    }

    public function update(User $user, Vendor $vendor): bool
    {
        // Admin-staff with vendors.view + ownership for vendors
        if ($user->can('vendors.view')) {
            return true;
        }
        return $vendor->user_id === $user->id && $vendor->isApproved();
    }

    public function approve(User $user, Vendor $vendor): bool
    {
        return $user->can('vendors.approve');
    }

    public function suspend(User $user, Vendor $vendor): bool
    {
        return $user->can('vendors.suspend');
    }
}
