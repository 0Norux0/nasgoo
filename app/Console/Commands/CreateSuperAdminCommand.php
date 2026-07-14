<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 12 §6 — safe production super-admin creation.
 *
 * Purpose: give the operator a scriptable, auditable way to create the
 * first super-admin without seeding a well-known email/password pair
 * (which is what DatabaseSeeder does — safe for local, unsafe for
 * production).
 *
 * Rules enforced:
 *   • Password entered via secretive prompt (never in shell history)
 *   • Password strength: ≥ 12 chars + upper + lower + digit + symbol
 *   • Refuses if a super_admin already exists, unless --force is passed
 *     (documented use case: replacing a compromised super_admin)
 *   • Requires --confirm to run in production (mirrors migrate --force)
 *   • Records `password_changed_at` = null so PostLoginController can
 *     force a change on first login (see note below on implementation)
 *   • Never writes the password to logs; audit_logs stores only the
 *     event ("super_admin_created") + actor + timestamp
 *
 * Note on force-change: if the User model has no `password_changed_at`
 * column, this command still works — it just skips that step and prints
 * a warning to change the password after first login manually.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'marketplace:create-super-admin
                            {--email= : Admin email address (prompted if omitted)}
                            {--name= : Admin full name (prompted if omitted)}
                            {--force : Allow creation when a super_admin already exists}
                            {--confirm : Required in production env}';

    protected $description = 'Create a production super-admin user with a strong password (interactive).';

    public function handle(): int
    {
        // ─── Production guard (§13 warning) ─────────────────────────
        if (app()->environment('production') && ! $this->option('confirm')) {
            $this->error('Refusing to run in production without --confirm flag.');
            $this->line('Re-run: php artisan marketplace:create-super-admin --confirm');
            return self::FAILURE;
        }

        // ─── Sanity: roles exist ───────────────────────────────────
        if (! class_exists(\Spatie\Permission\Models\Role::class)) {
            $this->error('spatie/laravel-permission not installed. Cannot assign super_admin role.');
            return self::FAILURE;
        }
        $role = \Spatie\Permission\Models\Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if (! $role) {
            $this->error('super_admin role missing. Run RolesAndPermissionsSeeder first: php artisan db:seed --class=RolesAndPermissionsSeeder --force');
            return self::FAILURE;
        }

        // ─── Existing super_admin check ─────────────────────────────
        $existing = User::role('super_admin')->count();
        if ($existing > 0 && ! $this->option('force')) {
            $this->warn("A super_admin already exists ({$existing} account(s)). Refusing to create another without --force.");
            $this->line('If replacing a compromised admin, re-run with --force (and consider disabling the old account afterwards).');
            return self::FAILURE;
        }

        // ─── Collect email + name ───────────────────────────────────
        $email = $this->option('email') ?: $this->ask('Super-admin email');
        $name  = $this->option('name')  ?: $this->ask('Full name', 'Marketplace Admin');

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => 'required|email|max:191',
            'name'  => 'required|string|min:2|max:191',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) $this->error($msg);
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists. Choose a different email or promote the existing user manually.");
            return self::FAILURE;
        }

        // ─── Password: never on CLI, always interactive + hidden ────
        $this->line('');
        $this->line('Password requirements: ≥ 12 chars, at least one upper, one lower, one digit, one symbol.');
        $password  = $this->secret('Password');
        $password2 = $this->secret('Confirm password');
        if ($password !== $password2) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }
        if (! $this->isStrongPassword($password)) {
            $this->error('Password does not meet strength requirements.');
            return self::FAILURE;
        }

        // ─── Create + assign role in a transaction ──────────────────
        try {
            $user = DB::transaction(function () use ($email, $name, $password, $role) {
                $data = [
                    'name'              => $name,
                    'email'             => $email,
                    'password'          => Hash::make($password),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ];
                // Optional column that Post-Login middleware can inspect to
                // force password change on first login.
                if (Schema::hasColumn('users', 'password_changed_at')) {
                    $data['password_changed_at'] = null;
                }
                if (Schema::hasColumn('users', 'locale')) {
                    $data['locale'] = 'en';
                }
                if (Schema::hasColumn('users', 'default_currency')) {
                    $data['default_currency'] = 'KWD';
                }
                $user = User::create($data);
                $user->assignRole($role);
                return $user;
            });
        } catch (\Throwable $e) {
            $this->error("Failed to create super-admin: {$e->getMessage()}");
            return self::FAILURE;
        }

        // ─── Audit log (no password material stored) ────────────────
        if (Schema::hasTable('audit_logs')) {
            try {
                // v11B.4.3 audit fix: use REAL audit_logs schema columns
                // (model_type/model_id/notes/created_at only — no
                // auditable_type/context/updated_at; the table is an
                // immutable log per its own migration comment).
                DB::table('audit_logs')->insert([
                    'user_id'    => null,   // seed-time; no acting user
                    'action'     => 'super_admin.created',
                    'model_type' => User::class,
                    'model_id'   => $user->id,
                    'after'      => json_encode([
                        'email' => $email,
                        'created_via' => 'marketplace:create-super-admin',
                    ], JSON_UNESCAPED_UNICODE),
                    'ip_address' => null,
                    'user_agent' => 'artisan',
                    'notes'      => 'super_admin created via console command',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $this->warn("Audit log write failed (non-fatal): {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info("Super-admin created: {$email}");
        if (! Schema::hasColumn('users', 'password_changed_at')) {
            $this->warn('Note: users.password_changed_at column not present. Force-change-on-first-login cannot be enforced automatically. Change the password manually after first sign-in.');
        }
        return self::SUCCESS;
    }

    private function isStrongPassword(string $pw): bool
    {
        return strlen($pw) >= 12
            && preg_match('/[A-Z]/', $pw)
            && preg_match('/[a-z]/', $pw)
            && preg_match('/[0-9]/', $pw)
            && preg_match('/[^A-Za-z0-9]/', $pw);
    }
}
