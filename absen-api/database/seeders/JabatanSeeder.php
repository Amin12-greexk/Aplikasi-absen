<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Jabatan;
use Illuminate\Support\Facades\DB;

class JabatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Jabatan::truncate();
        
        Jabatan::create(['nama_jabatan' => 'Direktur Utama']);
        Jabatan::create(['nama_jabatan' => 'Staff Produksi']);
        Jabatan::create(['nama_jabatan' => 'Satpam']);
        Jabatan::create(['nama_jabatan' => 'Staff IT']);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
