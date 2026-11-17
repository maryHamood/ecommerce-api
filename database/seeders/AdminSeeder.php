<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['phone' => '+963993424184'],
            [
                'name' => 'Admin',
                'email' => 'admin.test@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('Admin@12345'),
                'role' => 'admin',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->command->info('Admin account created successfully!');
    }
}
