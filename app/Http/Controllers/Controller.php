<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Phase 4 v5.5 — Laravel 11 ships the base Controller empty by default. We
 * use `$this->authorize(...)` in controllers that gate access via Policy
 * classes (OrderController, VendorOrderController, VendorProductController),
 * so the AuthorizesRequests trait must be on the base class. Without it,
 * every authorize() call throws "Call to undefined method".
 *
 * Policies live in app/Policies/ and Laravel 11 auto-discovers them by the
 * App\Models\X → App\Policies\XPolicy naming convention.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
