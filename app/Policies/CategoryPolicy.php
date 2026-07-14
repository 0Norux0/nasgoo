<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }
    public function viewAny(User $user): bool   { return true; } // categories are public-readable
    public function view(User $user, Category $c): bool { return true; }
    public function create(User $user): bool   { return $user->can('categories.manage'); }
    public function update(User $user, Category $c): bool { return $user->can('categories.manage'); }
    public function delete(User $user, Category $c): bool { return $user->can('categories.manage'); }
}
