<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Auth\Access\HandlesAuthorization;

class WishlistPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;  // own list, scoped in controller
    }

    public function view(User $user, Wishlist $entry): bool
    {
        return $user->id === $entry->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Wishlist $entry): bool
    {
        return $user->id === $entry->user_id;
    }
}
