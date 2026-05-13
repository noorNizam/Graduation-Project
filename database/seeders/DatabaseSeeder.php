<?php

namespace Database\Seeders;

use App\Infrastructure\Models\PaymentUnit;
use App\Infrastructure\Models\ServingType;
use App\Infrastructure\Models\User;
use App\Infrastructure\Models\WalletModel;
use App\Models\ServingCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Payment Units (skip if exists)
        $paymentUnits = ['USD', 'Hour', 'SYP'];
        foreach ($paymentUnits as $unitName) {
            PaymentUnit::firstOrCreate(['name' => $unitName]);
        }
        $hourUnit = PaymentUnit::where('name', 'Hour')->first();

        // 2. Create Serving Types (skip if exists)
        $servingTypes = ['paid', 'unpaid'];
        foreach ($servingTypes as $typeName) {
            ServingType::firstOrCreate(['name' => $typeName]);
        }

        // 3. Create Serving Categories (5 entities, no children, skip if exists)
        $categories = [
            ['name' => 'Home Services', 'parent_id' => null],
            ['name' => 'Education & Tutoring', 'parent_id' => null],
            ['name' => 'Tech Support', 'parent_id' => null],
            ['name' => 'Health & Wellness', 'parent_id' => null],
            ['name' => 'Transportation', 'parent_id' => null],
        ];
        foreach ($categories as $category) {
            ServingCategory::firstOrCreate(['name' => $category['name']], $category);
        }

        // 4. Create Admin User (skip if email exists)
        $admin = User::firstOrCreate(
            ['email' => 'admin@system.com'],
            [
                'full_name' => 'Admin',
                'role' => 'admin',
                'is_active' => true,
                'phone_number' => null,
                'password' => bcrypt('P@ssw0rd'),
            ]
        );

        // Create or retrieve Wallet for Admin
        WalletModel::firstOrCreate(
            ['user_id' => $admin->id],
            ['title' => 'Admin Wallet', 'balance' => 0.00, 'unit_id' => $hourUnit->id]
        );

        // 5. Create Regular Users (skip if email exists)
        $regularUsers = [
            [
                'full_name' => 'systemUser',
                'email' => 'user@system.com',
                'role' => 'user',
                'is_active' => true,
                'phone_number' => null,
                'password' => bcrypt('P@ssw0rd'),
            ],
            [
                'full_name' => 'systemUser2',
                'email' => 'user2@system.com',
                'role' => 'user',
                'is_active' => true,
                'phone_number' => null,
                'password' => bcrypt('P@ssw0rd'),
            ],
        ];

        foreach ($regularUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Create or retrieve Wallet for each user
            WalletModel::firstOrCreate(
                ['user_id' => $user->id],
                ['title' => $userData['full_name']."'s Wallet", 'balance' => 0.00, 'unit_id' => $hourUnit->id]
            );
        }
    }
}
