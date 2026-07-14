<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }
    public function viewAny(User $user): bool { return $user->can('attributes.manage') || $user->hasRole('vendor'); }
    public function view(User $user, Attribute $a): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('attributes.manage'); }
    public function update(User $user, Attribute $a): bool { return $user->can('attributes.manage'); }
    public function delete(User $user, Attribute $a): bool { return $user->can('attributes.manage'); }
}
