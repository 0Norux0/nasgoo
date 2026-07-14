<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CurrenciesSeeder::class,
            SettingsSeeder::class,
            NotificationTemplatesSeeder::class,
            VendorPackagesSeeder::class,
            CategoriesSeeder::class,
            AttributesSeeder::class,
            PaymentMethodsSeeder::class,
        ]);

        // ── Super admin (always present) ─────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplace.test'],
            [
                'name'              => 'Marketplace Admin',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'locale'            => 'en',
                'default_currency'  => 'KWD',
                'status'            => 'active',
            ],
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        // ── Demo users for the other roles (local + testing envs) ─
        if (app()->environment(['local', 'testing'])) {
            $staff = User::firstOrCreate(
                ['email' => 'staff@marketplace.test'],
                [
                    'name'              => 'Admin Staff',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ],
            );
            if (! $staff->hasRole('admin_staff')) {
                $staff->assignRole('admin_staff');
            }

            $vendor = User::firstOrCreate(
                ['email' => 'vendor@marketplace.test'],
                [
                    'name'              => 'Demo Vendor',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ],
            );
            if (! $vendor->hasRole('vendor')) {
                $vendor->assignRole('vendor');
            }

            $customer = User::firstOrCreate(
                ['email' => 'customer@marketplace.test'],
                [
                    'name'              => 'Demo Customer',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                ],
            );
            if (! $customer->hasRole('customer')) {
                $customer->assignRole('customer');
            }
        }

        // Phase 10 v10.10 — idempotent admin-reports access repair. Runs
        // after the admin user exists so the seeder has something to repair.
        // Safe no-op if everything is already correct. Also runnable
        // standalone: `php artisan db:seed --class=EnsureAdminReportsAccessSeeder`.
        $this->call(EnsureAdminReportsAccessSeeder::class);

        $this->command?->newLine();
        $this->command?->info('Phase 1 seeding complete.');
        $this->command?->info('  Super admin → admin@marketplace.test / password');
        if (app()->environment(['local', 'testing'])) {
            $this->command?->info('  Admin staff → staff@marketplace.test / password');
            $this->command?->info('  Vendor      → vendor@marketplace.test / password');
            $this->command?->info('  Customer    → customer@marketplace.test / password');
        }

        // Phase 4 v5.3 — flesh out demo accounts with vendor profile + products
        // + customer address, so `migrate:fresh --seed` produces a fully
        // testable environment. Self-guarded against `testing` env.
        $this->call(DemoSeeder::class);

        // Phase 11B.1 v11B.1.1 §5 — idempotent Arabic content backfill for
        // known demo products. Runs AFTER DemoSeeder so the product slugs
        // exist. Safe to re-run; never overwrites pre-existing Arabic values.
        $this->call(ArabicProductContentSeeder::class);

        // Phase 11B.1 v11B.1.2 §3+§11 — migrate JSON-column translations
        // (v11A.5 / v11B.1 / v11B.1.1) into the normalized
        // product_translations table with status='approved'. Idempotent.
        $this->call(BackfillProductTranslationsSeeder::class);
    }
}
