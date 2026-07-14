<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Phase 10 v10.9 — self-healing migration for the reports.view permission.
 *
 * Background: pre-v10.9 the /admin/reports route enforced authorization
 * via a Gate that called `$user->hasPermissionTo('reports.view')`. On any
 * installation that ran the project's migrations before Phase 10 added
 * the 'reports.view' permission to the catalogue, and never re-seeded
 * (`php artisan db:seed`), the permission row simply didn't exist —
 * `hasPermissionTo` would either return false or throw
 * `PermissionDoesNotExist`, producing the 403 the dev observed.
 *
 * v10.9's fix collapses the Gate to a role-based check via
 * `User::canManageAdminReports()`, so this migration is now BELT-AND-
 * SUSPENDERS rather than required. We still ship it because:
 *   (1) other admin pages may use the granular permission;
 *   (2) the seeder's catalogue is the source of truth and an existing
 *       installation that lacks the row is in an inconsistent state.
 *
 * Idempotent: firstOrCreate doesn't duplicate, givePermissionTo doesn't
 * either. Clears Spatie's permission cache so the change is effective
 * without a separate cache-reset step (which is easy to forget).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure the permission exists for guard 'web'. firstOrCreate
        //    is the canonical idempotent pattern Spatie recommends.
        $permission = Permission::firstOrCreate([
            'name'       => 'reports.view',
            'guard_name' => 'web',
        ]);

        // 2. Ensure super_admin + admin_staff have it. Both roles may
        //    or may not exist depending on installation age; firstOrCreate
        //    the roles before assigning. Vendor/customer are intentionally
        //    NOT granted — they're not allowed to see admin reports.
        foreach (['super_admin', 'admin_staff'] as $roleName) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
            ]);
            // givePermissionTo is idempotent (Spatie skips dup grants).
            // Wrap in try/catch defensively — should never throw on a
            // freshly-firstOrCreate'd permission, but if the cache is
            // mid-flight we don't want to block the migration.
            try {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            } catch (\Throwable $e) {
                // Swallow + log. The Gate is role-based anyway in v10.9,
                // so this migration is belt-and-suspenders only.
                logger()?->warning(
                    'Phase 10 v10.9 — could not grant reports.view to role',
                    ['role' => $roleName, 'error' => $e->getMessage()]
                );
            }
        }

        // 3. Clear Spatie's permission cache so subsequent hasPermissionTo
        //    calls see the new assignment immediately. Without this, the
        //    cached role-permission map could persist until the next deploy
        //    and the migration would APPEAR not to have taken effect.
        try {
            Artisan::call('permission:cache-reset');
        } catch (\Throwable) {
            // Cache reset is best-effort; the assignment is already in DB.
        }
    }

    public function down(): void
    {
        // No down — this migration repairs data; rolling it back would
        // recreate the very inconsistency it fixes.
    }
};
