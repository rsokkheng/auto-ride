<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission groups covering the admin panel's functional areas. Each
     * group name doubles as the permission used to gate that section.
     */
    public const PERMISSIONS = [
        'manage-users',       // Users CRUD (resources/views/admin/users.blade.php)
        'manage-drivers',     // Driver approvals, penalties, vehicles
        'manage-rides',       // Rides
        'manage-deliveries',  // Deliveries, Car Rentals, Marketplace orders
        'manage-companies',   // Companies, Business Accounts, Partner Contracts
        'manage-finance',     // Transactions, Top-ups, Withdrawals, Settlements
        'manage-pricing',     // Fare/Pricing/Surge/Airport zones/Charging stations
        'manage-marketing',   // Promo Events, Promo Coupons, Banners, Subscription Plans
        'manage-support',     // Support Tickets, Safety, Chat
        'manage-reports',     // Operations report, exports
        'manage-roles',       // The Roles & Permissions admin page itself
    ];

    /**
     * Non-admin staff roles and the permissions each one gets. Only assigned
     * to users explicitly given the role afterwards — never auto-applied to
     * existing admins (unlike Super Admin, which everyone keeps).
     */
    public const ROLES = [
        'Dispatcher' => ['manage-rides', 'manage-drivers'],
        'Support'    => ['manage-support'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        // Every existing admin keeps full access — nobody loses anything today.
        // Restrict specific staff later by assigning a narrower role instead.
        User::where('role', 'admin')->each(function (User $user) use ($superAdmin) {
            if (! $user->hasRole($superAdmin)) {
                $user->assignRole($superAdmin);
            }
        });

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
