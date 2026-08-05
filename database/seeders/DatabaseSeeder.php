<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed default administrator for Filament Admin Panel login
        Admin::updateOrCreate(
            ['email' => 'admin@tgmanager.uz'],
            [
                'name' => 'AI Manager SuperAdmin',
                'password' => Hash::make('admin1234'),
                'role' => 'super_admin',
            ]
        );
    }
}
