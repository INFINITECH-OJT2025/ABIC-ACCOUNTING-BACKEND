<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'infinitech.accountant@gmail.com'],
            [
                'name' => 'Accountant',
                'password' => Hash::make('Hi!Imaccountant1'),
                'role' => 'accountant',
            ]
        );
    }
}
