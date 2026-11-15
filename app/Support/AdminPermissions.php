<?php

namespace App\Support;

use Database\Seeders\RolePermissionSeeder;

/**
 * How the admin permissions (RolePermissionSeeder::PERMISSIONS) are presented on
 * the Roles & Permissions page: a readable name, an icon, what it covers, and its area.
 */
class AdminPermissions
{
    public const SUPER_ADMIN = 'Super Admin';

    /** area => [label, icon] */
    public const GROUPS = [
        'operations' => ['Operations', 'fa-route'],
        'business'   => ['Business', 'fa-briefcase'],
        'finance'    => ['Finance & Pricing', 'fa-coins'],
        'growth'     => ['Marketing & Reports', 'fa-bullhorn'],
        'support'    => ['Support', 'fa-headset'],
        'admin'      => ['Administration', 'fa-user-shield'],
    ];

    /** permission => [label, icon, area, what it covers] */
    public const INFO = [
        'manage-rides'      => ['Rides', 'fa-route', 'operations', 'Ride list, ride details, edit and cancel rides.'],
        'manage-deliveries' => ['Deliveries & Rentals', 'fa-box', 'operations', 'Deliveries, partner packages, car rentals and marketplace orders.'],
        'manage-drivers'    => ['Drivers & Vehicles', 'fa-id-card', 'operations', 'Driver approvals, penalties, documents and vehicles.'],
        'manage-companies'  => ['Companies & Partners', 'fa-building', 'business', 'Companies, business accounts, partners and partner contracts.'],
        'manage-finance'    => ['Finance', 'fa-wallet', 'finance', 'Transactions, top-ups, driver payouts, settlements and daily closing.'],
        'manage-pricing'    => ['Pricing', 'fa-tags', 'finance', 'Fares, surge and airport zones, subscription plans, charging stations.'],
        'manage-marketing'  => ['Marketing', 'fa-bullhorn', 'growth', 'Banners, promo events and coupons.'],
        'manage-reports'    => ['Reports', 'fa-chart-line', 'growth', 'Business, financial and people reports and exports.'],
        'manage-support'    => ['Support & Safety', 'fa-headset', 'support', 'Support tickets, safety alerts and chat.'],
        'manage-users'      => ['Users', 'fa-users', 'admin', 'Create, edit and delete user accounts.'],
        'manage-roles'      => ['Roles & Permissions', 'fa-user-shield', 'admin', 'This page: create roles and decide who can do what.'],
    ];

    public static function label(string $permission): string
    {
        return self::INFO[$permission][0] ?? ucwords(str_replace(['manage-', '-'], ['', ' '], $permission));
    }

    public static function icon(string $permission): string
    {
        return self::INFO[$permission][1] ?? 'fa-key';
    }

    public static function description(string $permission): string
    {
        return self::INFO[$permission][3] ?? '';
    }

    /**
     * Permission names grouped by area, in display order; unknown permissions go last.
     * @param iterable<string> $names
     * @return array<string, string[]> area => names
     */
    public static function grouped(iterable $names): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), []);
        foreach ($names as $name) {
            $out[self::INFO[$name][2] ?? 'other'][] = $name;
        }
        foreach ($out as $area => $list) {
            usort($list, fn ($a, $b) => array_search($a, array_keys(self::INFO)) <=> array_search($b, array_keys(self::INFO)));
            $out[$area] = $list;
        }

        return array_filter($out);
    }

    /** Every permission the system defines (the Super Admin role always has all of them). */
    public static function all(): array
    {
        return RolePermissionSeeder::PERMISSIONS;
    }
}
