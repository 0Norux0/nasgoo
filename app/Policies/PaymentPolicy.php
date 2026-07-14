<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->can('payments.view')) return true;
        return $user->id === $payment->order?->user_id;
    }

    public function capture(User $user, Payment $payment): bool { return $user->can('payments.capture'); }
    public function refund(User $user, Payment $payment): bool  { return $user->can('payments.refund'); }
}
