<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

/**
 * Phase 10 v10.10 — self-healing repair command for admin reports access.
 *
 * Per dev §7: an idempotent repair that fixes the developer's existing
 * admin account in their existing database, without requiring
 * `migrate:fresh`.
 *
 * Usage:
 *   php artisan reports:repair-access
 *   php artisan reports:repair-access admin@example.com
 *
 * What it does (in order):
 *   1. Look up the user by email
 *   2. Force status = 'active' if it isn't already
 *   3. Ensure the 'super_admin' role exists with guard 'web'
 *   4. Assign super_admin to the user if they don't already have it
 *   5. Clear Spatie's permission cache
 *   6. Re-run the diagnostic so the dev SEES the new state
 *
 * Idempotent: re-running has no additional effect. NEVER touches other
 * users. NEVER removes existing roles. NEVER touches password, email,
 * or other sensitive fields.
 */
final class RepairAdminReportsAccessCommand extends Command
{
    protected $signature = 'reports:repair-access
                            {email? : Email of the user to repair (default: admin@marketplace.test)}
                            {--no-confirm : Skip the confirmation prompt}';

    protected $description = 'Phase 10 v10.10 — idempotent repair of a single admin user\'s reports access.';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?? 'admin@marketplace.test');

        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User not found: {$email}");
            $this->line('Tip: run `php artisan reports:diagnose-access --all-admins` to list admin-like users.');
            return self::FAILURE;
        }

        $this->info("Repairing admin reports access for: #{$user->id} {$user->email}");
        $this->line('');
        $this->line('  Current state:');
        $this->line('    status:              ' . var_export($user->status, true));
        $this->line('    roles:               [' . $user->getRoleNames()->implode(', ') . ']');
        $this->line('    canManageAdminReports() before: ' . var_export($user->canManageAdminReports(), true));
        $this->line('');

        $planned = [];
        if ($user->status !== 'active') {
            $planned[] = "set status = 'active' (was: " . var_export($user->status, true) . ')';
        }
        if (! $user->hasRole('super_admin')) {
            $planned[] = "assign super_admin role";
        }
        $planned[] = 'reset Spatie permission cache';

        $this->line('  Planned changes:');
        foreach ($planned as $p) {
            $this->line("    - {$p}");
        }
        $this->line('');

        if (! $this->option('no-confirm') && ! $this->confirm('Apply these changes?', true)) {
            $this->warn('Aborted. No changes made.');
            return self::FAILURE;
        }

        if ($user->status !== 'active') {
            $user->status = 'active';
            $user->save();
            $this->info("  ✓ status set to 'active'");
        }

        $role = Role::firstOrCreate([
            'name'       => 'super_admin',
            'guard_name' => 'web',
        ]);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
            $this->info("  ✓ super_admin role assigned");
        }

        try {
            Artisan::call('permission:cache-reset');
            $this->info("  ✓ Spatie permission cache reset");
        } catch (\Throwable $e) {
            $this->warn("  ⚠ permission:cache-reset failed: " . $e->getMessage());
        }

        $user = $user->fresh();

        $this->line('');
        $this->info("Repair complete. New state:");
        $this->line('  status:                         ' . var_export($user->status, true));
        $this->line('  roles:                          [' . $user->getRoleNames()->implode(', ') . ']');
        $this->line('  canManageAdminReports() after:  ' . var_export($user->canManageAdminReports(), true));

        if (! $user->canManageAdminReports()) {
            $this->error('');
            $this->error('  ✗ User STILL cannot manage admin reports after repair.');
            $this->error('    Run: php artisan reports:diagnose-access ' . $user->email);
            return self::FAILURE;
        }

        $this->line('');
        $this->info('  ✓ User can now access /admin/reports.');
        $this->line('  Next steps:');
        $this->line('    - Log out from the application');
        $this->line('    - Log back in as ' . $user->email);
        $this->line('    - Visit /admin/reports — expected HTTP 200');

        return self::SUCCESS;
    }
}
