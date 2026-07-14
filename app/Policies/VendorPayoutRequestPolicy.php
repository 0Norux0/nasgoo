<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPayoutRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendorPayoutRequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_staff'])) {
            return true;
        }
        return null;
    }

    /** Vendor sees their own requests; admin sees all (via before). */
    public function viewAny(User $user): bool
    {
        return $user->vendor !== null;
    }

    public function view(User $user, VendorPayoutRequest $request): bool
    {
        return $user->vendor?->id === $request->vendor_id;
    }

    public function create(User $user): bool
    {
        return $user->vendor?->isApproved() ?? false;
    }

    /** Only admin (via before). Vendors cannot directly mutate after submission. */
    public function update(User $user, VendorPayoutRequest $request): bool { return false; }
    public function delete(User $user, VendorPayoutRequest $request): bool { return false; }
}
