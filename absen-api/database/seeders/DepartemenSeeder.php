<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Departemen;

class DepartemenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Matikan pengecekan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Departemen::truncate();

        Departemen::create([
            'nama_departemen' => 'Manajemen',
            'menggunakan_shift' => false,
        ]);

        Departemen::create([
            'nama_departemen' => 'Keamanan',
            'menggunakan_shift' => true,
        ]);

        Departemen::create([
            'nama_departemen' => 'Teknologi Informasi',
            'menggunakan_shift' => false,
        ]);

        // Nyalakan kembali pengecekan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}

