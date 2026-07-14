<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 10 v10.10 — diagnostic command for the admin reports 403.
 *
 * Per dev §13: a development-only Artisan command that reports safely
 * on a given user's authorization state, so the developer can identify
 * exactly why their admin user is being denied /admin/reports.
 *
 * Usage:
 *   php artisan reports:diagnose-access
 *   php artisan reports:diagnose-access admin@example.com
 *   php artisan reports:diagnose-access --all-admins
 *
 * Safe-for-production: prints only the user id, email, name, status,
 * email verification state, role names, permission names, role check
 * results, and Gate result. NEVER prints password hash, remember token,
 * two-factor secret, or session data. No mutation.
 */
final class DiagnoseReportsAccessCommand extends Command
{
    protected $signature = 'reports:diagnose-access
                            {email? : Email of the user to inspect (default: admin@marketplace.test)}
                            {--all-admins : Print state for every user with an admin-like role}';

    protected $description = 'Phase 10 v10.10 — diagnose why a user is denied /admin/reports.';

    public function handle(): int
    {
        if ($this->option('all-admins')) {
            return $this->inspectAllAdmins();
        }

        $email = (string) ($this->argument('email') ?? 'admin@marketplace.test');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("User not found: {$email}");
            $this->newLine();
            $this->line('Admin-like users currently in the database:');
            $admins = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['super_admin', 'admin_staff', 'admin', 'administrator']);
            })->get(['id', 'email', 'name', 'status']);
            foreach ($admins as $a) {
                $this->line("  - #{$a->id}  {$a->email}  ({$a->name})  status={$a->status}");
            }
            return self::FAILURE;
        }

        $this->reportFor($user);
        return self::SUCCESS;
    }

    private function inspectAllAdmins(): int
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['super_admin', 'admin_staff', 'admin', 'administrator']);
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('No users with admin-like roles found.');
            return self::SUCCESS;
        }

        foreach ($admins as $a) {
            $this->reportFor($a);
            $this->newLine();
        }
        return self::SUCCESS;
    }

    private function reportFor(User $user): void
    {
        $this->info("════ Diagnostic for user #{$user->id} ════");
        $this->line("  email:               {$user->email}");
        $this->line("  name:                {$user->name}");
        $this->line(sprintf('  status:              %s  (type: %s)', var_export($user->status, true), gettype($user->status)));
        $this->line('  status === "active": ' . var_export($user->status === 'active', true));
        $this->line('  email_verified_at:   ' . ($user->email_verified_at?->toDateTimeString() ?? 'NULL'));
        $this->line('  deleted_at:          ' . ($user->deleted_at?->toDateTimeString() ?? 'NULL'));

        $roleNames = $user->getRoleNames()->all();
        $this->line('  roles:               [' . implode(', ', $roleNames) . ']');

        foreach (['super_admin', 'admin_staff', 'admin', 'administrator'] as $role) {
            $this->line(sprintf('  hasRole(%-15s): %s', "'{$role}'", var_export($user->hasRole($role), true)));
        }

        $this->line('  hasAnyRole(super_admin|admin_staff|admin|administrator): '
            . var_export($user->hasAnyRole(['super_admin', 'admin_staff', 'admin', 'administrator']), true));

        $this->line('');
        $this->line('  canManageAdminReports(): ' . var_export($user->canManageAdminReports(), true)
            . '  ← the v10.10 canonical helper');

        try {
            $gateResult = Gate::forUser($user)->allows('viewReports');
            $this->line('  Gate::allows("viewReports"): ' . var_export($gateResult, true)
                . '  ← legacy gate (kept for any third-party caller)');
        } catch (\Throwable $e) {
            $this->error('  Gate::allows("viewReports") threw: ' . $e->getMessage());
        }

        try {
            $permCount = $user->getAllPermissions()->count();
            $this->line("  total permissions:   {$permCount}");
        } catch (\Throwable $e) {
            $this->error('  getAllPermissions() threw: ' . $e->getMessage());
        }

        $this->line('');

        if ($user->canManageAdminReports()) {
            $this->info('  ✓ This user can access /admin/reports under v10.10.');
        } else {
            $this->error('  ✗ This user will be DENIED /admin/reports.');
            $this->line('    Run: php artisan reports:repair-access ' . $user->email);
        }
    }
}
