<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission catalogue, grouped by module.
     *
     * v6.3 — CRITICAL BUG FIX. This array previously had DUPLICATE PHP keys
     * for 'products' (lines 36 & 48) and 'orders' (lines 41 & 55). In PHP,
     * when an associative array has duplicate keys, the later value silently
     * overwrites the earlier value. The result was that the registered
     * 'orders' permissions became just ['orders.view', 'orders.manage'] and
     * 'orders.confirm', 'orders.ship', 'orders.deliver', 'orders.cancel',
     * 'orders.refund', 'payments.capture', 'payments.refund', etc. were
     * NEVER registered as permissions — even though the role assignments
     * below and the Filament action visibility checks both referenced them.
     *
     * Result: php artisan migrate:fresh --seed blew up at the vendor
     * syncPermissions() call with "There is no permission named
     * `orders.confirm` for guard `web`". And even on systems where seeding
     * had succeeded historically (because the duplicate-key arrays happened
     * to align), the Filament action visibility checks always returned false
     * because the permissions didn't exist.
     *
     * This rewrite merges the duplicates so every permission referenced by
     * roles AND by Filament action ->can() checks is registered.
     *
     * @return array<string, array<int, string>>
     */
    public static function permissionCatalogue(): array
    {
        return [
            'users' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
            ],
            'roles' => [
                'roles.view', 'roles.manage',
            ],
            'settings' => [
                'settings.view', 'settings.manage',
            ],
            'vendors' => [
                'vendors.view', 'vendors.approve', 'vendors.suspend',
                'vendor_packages.manage', 'vendor_subscriptions.manage',
            ],
            // Merged from the two old 'products' keys
            'products' => [
                'products.view', 'products.create', 'products.update',
                'products.delete', 'products.approve', 'products.publish',
                'products.feature',
                'categories.manage', 'attributes.manage',
            ],
            'services' => [
                'services.view', 'services.create', 'services.approve',
            ],
            // Merged from the two old 'orders' keys + add what the Filament
            // OrderResource + ViewOrder + EditOrder ->can() checks reference.
            // Every permission used by ->visible(... ->can(...)) MUST be
            // listed here or super_admin->syncPermissions($allPermissions)
            // won't include it and admins won't see the buttons.
            'orders' => [
                'orders.view', 'orders.view.any', 'orders.manage',
                'orders.confirm', 'orders.ship', 'orders.deliver',
                'orders.cancel', 'orders.refund',
                'orders.export',
            ],
            // Payments — needed for COD capture, manual transfer confirm,
            // and refund actions in OrderResource + EditOrder + ViewOrder.
            'payments' => [
                'payments.view', 'payments.capture', 'payments.refund',
                'payment_methods.manage',
                'payouts.approve', 'commissions.manage',
            ],
            'bookings' => [
                'bookings.view', 'bookings.manage',
            ],
            'reviews' => [
                'reviews.moderate',
            ],
            'promotions' => [
                'promotions.manage',
            ],
            'support' => [
                'support.manage',
            ],
            'reports' => [
                'reports.view',
            ],
            'audit' => [
                'audit_logs.view',
            ],
            // Phase 6 — supplier / dropshipping. New top-level module keys
            // (no overlap with existing modules — safe against the v6.3
            // duplicate-array-key class of bug).
            'supplier_platforms' => [
                'supplier_platforms.view', 'supplier_platforms.manage',
            ],
            'supplier_integrations' => [
                'supplier_integrations.view', 'supplier_integrations.create',
                'supplier_integrations.update', 'supplier_integrations.delete',
            ],
            'supplier_products' => [
                'supplier_products.view', 'supplier_products.create',
                'supplier_products.import', 'supplier_products.update',
                'supplier_products.delete',
                'supplier_products.map', 'supplier_products.approve',
                'supplier_products.reject',
            ],
            'supplier_orders' => [
                'supplier_orders.view', 'supplier_orders.update',
            ],
            // Phase 7 — customizable products + proof workflow.
            // New top-level module keys to keep distinct from existing
            // products/orders modules (avoids v6.3 duplicate-array-key class).
            'customization_fields' => [
                'customization_fields.view', 'customization_fields.manage',
            ],
            'customization_proofs' => [
                'customization_proofs.view', 'customization_proofs.upload',
                'customization_proofs.respond',
            ],
        ];
    }

    public function run(): void
    {
        // Reset cached roles/permissions
        Artisan::call('permission:cache-reset');

        // ── Permissions ──────────────────────────────────────────
        $allPermissions = [];
        foreach (self::permissionCatalogue() as $module => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
                $allPermissions[] = $permission;
            }
        }

        // ── Roles ────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminStaff = Role::firstOrCreate(['name' => 'admin_staff', 'guard_name' => 'web']);
        $vendor     = Role::firstOrCreate(['name' => 'vendor',      'guard_name' => 'web']);
        $customer   = Role::firstOrCreate(['name' => 'customer',    'guard_name' => 'web']);

        // ── Role → Permission assignments ────────────────────────

        // super_admin: every permission, including future ones via Gate::before in policies
        $superAdmin->syncPermissions($allPermissions);

        // admin_staff: everything EXCEPT roles.manage, settings.manage (privileged), commissions.manage
        $adminStaffPermissions = array_diff($allPermissions, [
            'roles.manage',
            'settings.manage',
            'commissions.manage',
            'payouts.approve',
            'users.delete',
            'vendor_subscriptions.manage',
        ]);
        $adminStaff->syncPermissions($adminStaffPermissions);

        // vendor: their own resource permissions (Phase 2 will refine via policies)
        $vendor->syncPermissions([
            'products.view', 'products.create', 'products.update',
            'services.view', 'services.create',
            'orders.view', 'orders.confirm', 'orders.ship', 'orders.deliver',
            'payments.view',
            'bookings.view',
            'reviews.moderate',
            'reports.view',
            // Phase 6 — vendors manage their own suppliers + dropshipping
            'supplier_platforms.view',
            'supplier_integrations.view', 'supplier_integrations.create',
            'supplier_integrations.update', 'supplier_integrations.delete',
            'supplier_products.view', 'supplier_products.create',
            'supplier_products.import', 'supplier_products.update',
            'supplier_products.delete', 'supplier_products.map',
            'supplier_orders.view', 'supplier_orders.update',
            // Phase 7 — vendor manages their own customization fields + uploads proofs
            'customization_fields.view', 'customization_fields.manage',
            'customization_proofs.view', 'customization_proofs.upload',
        ]);

        // customer: read-only on shoppable things, plus their own data
        $customer->syncPermissions([
            'products.view',
            'services.view',
            'orders.view',
            'bookings.view',
        ]);

        Artisan::call('permission:cache-reset');

        $this->command?->info(sprintf(
            'Seeded %d permissions across 4 roles (super_admin, admin_staff, vendor, customer).',
            count($allPermissions)
        ));
    }
}
