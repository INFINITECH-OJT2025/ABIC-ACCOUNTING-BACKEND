<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@test.com',
                'role' => 'super_admin',
                'password' => 'Super@123',
            ],
            [
                'name' => 'Admin Head',
                'email' => 'adminhead@test.com',
                'role' => 'admin_head',
                'password' => 'Admin@123',
            ],
            [
                'name' => 'Accountant Head',
                'email' => 'accountanthead@test.com',
                'role' => 'accountant_head',
                'password' => 'Account@123',
            ],
            [
                'name' => 'Default User',
                'email' => 'user@test.com',
                'role' => 'user',
                'password' => 'User@123',
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'password' => Hash::make($u['password']),
                    'account_status' => 'active',
                    'email_verified_at' => now(),
                    'password_expires_at' => null,
                    'is_password_expired' => false,
                    'last_password_change' => now(),
                ]
            );

            $this->command->info("Created: {$u['role']} ({$u['email']}) password: {$u['password']}");
        }
    }
}
