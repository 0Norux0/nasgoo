<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

/**
 * Phase 10 v10.10 — idempotent admin reports access repair, as a seeder.
 *
 * Per dev §7: the developer needs to repair their existing database
 * without `migrate:fresh`. This seeder is the seed-form of the
 * `reports:repair-access` Artisan command, so it can be run via:
 *
 *   php artisan db:seed --class=EnsureAdminReportsAccessSeeder
 *
 * It's also hooked into DatabaseSeeder so every `php artisan db:seed`
 * after v10.10 also runs it — defense against the issue ever recurring.
 *
 * What it does:
 *   - Find admin@marketplace.test (the canonical demo admin)
 *   - Ensure status = 'active'
 *   - Ensure super_admin role exists (guard: web)
 *   - Assign super_admin to the user if not already
 *   - Clear Spatie's permission cache
 *
 * If the admin user doesn't exist, the seeder is a no-op rather than
 * an error. For non-default admin emails, the developer should use the
 * `reports:repair-access EMAIL` command directly.
 */
class EnsureAdminReportsAccessSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@marketplace.test';
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->command?->warn("  EnsureAdminReportsAccessSeeder: {$email} not found — skipping (run repair command for custom admin email).");
            return;
        }

        $changed = false;

        if ($user->status !== 'active') {
            $user->status = 'active';
            $user->save();
            $changed = true;
        }

        $role = Role::firstOrCreate([
            'name'       => 'super_admin',
            'guard_name' => 'web',
        ]);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
            $changed = true;
        }

        try {
            Artisan::call('permission:cache-reset');
        } catch (\Throwable) {
            // Best-effort
        }

        $user = $user->fresh();
        $works = $user?->canManageAdminReports() ?? false;

        if ($changed) {
            $this->command?->info("  EnsureAdminReportsAccessSeeder: repaired {$email}; canManageAdminReports = " . var_export($works, true));
        } else {
            $this->command?->info("  EnsureAdminReportsAccessSeeder: {$email} already configured correctly.");
        }
    }
}
