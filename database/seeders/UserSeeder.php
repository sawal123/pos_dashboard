<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed admin user untuk login.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // ADMIN-01: Akun dummy Platform Admin hanya dibuat pada environment
        // non-production (local/testing). Production tidak boleh memiliki akun
        // Platform Admin otomatis dengan credential default.
        if (app()->environment(['local', 'testing'])) {
            User::updateOrCreate(
                ['email' => 'platform@admin.com'],
                [
                    'name' => 'Platform Admin',
                    'email' => 'platform@admin.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'is_platform_admin' => true,
                ]
            );
        }
    }
}
