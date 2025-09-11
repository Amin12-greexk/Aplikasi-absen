<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::truncate();

        User::create([
            'name' => 'HR Admin',
            'email' => 'admin@esta.test',
            'password' => 'password', // Otomatis di-hash oleh model User
        ]);
    }
}
