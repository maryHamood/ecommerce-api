<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LargeDatasetSeeder extends Seeder
{
    public function run(): void
    {

        $this->command->info('Creating Admin and Customer accounts...');
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'phone' => '+963993424184',
                'password' => Hash::make('12345679'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Customer User',
                'phone' => '+963932230827',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);


        $this->command->info('Generating 10 categories...');
        $categoryIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $categoryIds[] = DB::table('categories')->insertGetId([
                'name' => 'Category ' . Str::random(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        $this->command->info('Generating 1000 products...');
        $products = [];
        for ($i = 1; $i <= 1000; $i++) {
            $products[] = [
                'name' => 'Product ' . $i . ' - ' . Str::random(5),
                'price' => rand(10, 5000),
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'photo' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('products')->insert($products);


        $userIds = DB::table('users')->pluck('id')->toArray();


        $this->command->info('Generating 100,000 orders in chunks...');
        $totalOrders = 100000;
        $chunkSize = 1000;
        $totalChunks = $totalOrders / $chunkSize;

        for ($chunk = 1; $chunk <= $totalChunks; $chunk++) {
            $orders = [];
            for ($i = 1; $i <= $chunkSize; $i++) {
                $orders[] = [
                    'user_id' => $userIds[array_rand($userIds)],
                    'total' => rand(50, 10000),
                    'status' => ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'][array_rand(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])],
                    'created_at' => now()->subDays(rand(1, 365)),
                    'updated_at' => now(),
                ];
            }
            DB::table('orders')->insert($orders);
            $this->command->info("Inserted chunk {$chunk} of {$totalChunks}");
        }

        $this->command->info('Large Dataset Generated Successfully!');
    }
}
