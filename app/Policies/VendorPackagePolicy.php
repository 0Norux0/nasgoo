<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\User;
use App\Models\VendorPackage;

class VendorPackagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }
    public function viewAny(User $user): bool { return $user->can('vendor_packages.manage') || $user->can('vendors.view'); }
    public function view(User $user, VendorPackage $package): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('vendor_packages.manage'); }
    public function update(User $user, VendorPackage $package): bool { return $user->can('vendor_packages.manage'); }
    public function delete(User $user, VendorPackage $package): bool { return $user->can('vendor_packages.manage'); }
}
