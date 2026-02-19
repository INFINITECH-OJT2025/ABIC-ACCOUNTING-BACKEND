<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the super_admin user.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin1@abic.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('SuperAdmin@1234'),
                'role' => 'super_admin',
                'account_status' => 'active',
                'email_verified_at' => now(),
                'password_expires_at' => null,
                'is_password_expired' => false,
                'last_password_change' => now(),
            ]
        );
    }
}
