<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffUserSeeder extends Seeder
{
    /**
     * One demo admin account per new staff role, so each role has someone to
     * log in and test with. Change the password after first login.
     */
    public const STAFF = [
        [
            'name'     => 'Dispatcher User',
            'email'    => 'dispatcher@roteh.app',
            'password' => 'dispatcher123',
            'role_name' => 'Dispatcher',
        ],
        [
            'name'     => 'Support User',
            'email'    => 'support@roteh.app',
            'password' => 'support123',
            'role_name' => 'Support',
        ],
    ];

    public function run(): void
    {
        foreach (self::STAFF as $staff) {
            $user = User::firstOrCreate(
                ['email' => $staff['email']],
                [
                    'name'      => $staff['name'],
                    'password'  => Hash::make($staff['password']),
                    'role'      => 'admin',
                    'api_token' => bin2hex(random_bytes(40)),
                ]
            );

            if (! $user->hasRole($staff['role_name'])) {
                $user->assignRole($staff['role_name']);
            }
        }
    }
}
