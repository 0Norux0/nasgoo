<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\User;
use App\Models\VendorCommissionRule;

class VendorCommissionRulePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }
    public function viewAny(User $user): bool { return $user->can('commissions.manage') || $user->can('vendors.view'); }
    public function view(User $user, VendorCommissionRule $rule): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('commissions.manage'); }
    public function update(User $user, VendorCommissionRule $rule): bool { return $user->can('commissions.manage'); }
    public function delete(User $user, VendorCommissionRule $rule): bool { return $user->can('commissions.manage'); }
}
