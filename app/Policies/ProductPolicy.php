<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        // Vendor can view their own products regardless of status
        if ($product->vendor && $product->vendor->user_id === $user->id) {
            return true;
        }
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        // Vendor must have an approved profile to create products
        return $user->hasRole('vendor')
            && $user->vendor?->isApproved()
            && $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->can('products.update')) return true;
        return $product->vendor?->user_id === $user->id
            && in_array($product->status, [Product::STATUS_DRAFT, Product::STATUS_REJECTED], true);
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->can('products.delete')) return true;
        return $product->vendor?->user_id === $user->id && $product->isDraft();
    }

    public function publish(User $user, Product $product): bool
    {
        return $user->can('products.publish');
    }

    public function feature(User $user, Product $product): bool
    {
        return $user->can('products.feature');
    }
}
