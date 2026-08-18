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
        // 1. Ensure payment units exist
        $hourUnit = PaymentUnit::firstOrCreate(['name' => 'Hour']);
        PaymentUnit::firstOrCreate(['name' => 'SYP']);
        PaymentUnit::firstOrCreate(['name' => 'USD']);

        // 2. Create Serving Types (skip if exists), ensure unpaid is removed
        $servingTypes = ['paid', 'voluntary'];
        foreach ($servingTypes as $typeName) {
            ServingType::firstOrCreate(['name' => $typeName]);
        }
        ServingType::where('name', 'unpaid')->delete();

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

        // 4. Create Admin Users (skip if email exists)
        for ($i = 1; $i <= 3; $i++) {
            $suffix = $i === 1 ? '' : $i;
            $adminData = [
                'full_name' => "systemAdmin{$suffix}",
                'email' => "admin{$suffix}@system.com",
                'role' => 'admin',
                'is_active' => true,
                'phone_number' => null,
                'password' => bcrypt('P@ssw0rd'),
            ];

            $admin = User::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );

            WalletModel::firstOrCreate(
                ['user_id' => $admin->id],
                ['title' => $adminData['full_name']."'s Wallet", 'balance' => 0.00, 'unit_id' => $hourUnit->id]
            );
        }

        // 5. Create Regular Users (skip if email exists)
        for ($i = 1; $i <= 30; $i++) {
            $suffix = $i === 1 ? '' : $i;
            $userData = [
                'full_name' => "systemUser{$suffix}",
                'email' => "user{$suffix}@system.com",
                'role' => 'user',
                'is_active' => true,
                'phone_number' => null,
                'password' => bcrypt('P@ssw0rd'),
            ];

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            WalletModel::firstOrCreate(
                ['user_id' => $user->id],
                ['title' => $userData['full_name']."'s Wallet", 'balance' => 0.00, 'unit_id' => $hourUnit->id]
            );
        }
    }
}
