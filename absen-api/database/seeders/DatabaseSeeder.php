<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Kosongkan seeder lama dan panggil seeder baru
        $this->call([
            KaryawanRoleSeeder::class,
        ]);
    }
}