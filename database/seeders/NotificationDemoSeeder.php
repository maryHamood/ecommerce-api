<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NotificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only allowed in local or testing environments.');
        }

        foreach ([
            ['name' => 'Demo Admin', 'phone' => '+963900000101', 'role' => 'admin'],
            ['name' => 'Demo Customer One', 'phone' => '+963900000102', 'role' => 'customer'],
            ['name' => 'Demo Customer Two', 'phone' => '+963900000103', 'role' => 'customer'],
        ] as $account) {
            $user = User::firstOrCreate(['phone' => $account['phone']], [
                'name' => $account['name'],
                'role' => $account['role'],
                'password' => Hash::make('HandoffDemo!2026'),
            ]);

            if ($user->name !== $account['name'] || $user->role !== $account['role']
                || ! Hash::check('HandoffDemo!2026', $user->password)) {
                throw new \RuntimeException('A demo phone already belongs to another account. No account was overwritten.');
            }
        }

        $category = Category::firstOrCreate(['name' => 'Notification Demo']);
        Product::firstOrCreate(['name' => 'Notification Demo Product'], [
            'price' => 25,
            'amount' => 1000,
            'category_id' => $category->id,
        ]);
    }
}
