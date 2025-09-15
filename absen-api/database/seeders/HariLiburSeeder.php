<?php
// database/seeders/HariLiburSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HariLibur;

class HariLiburSeeder extends Seeder
{
    public function run()
    {
        $hariLibur = [
            ['tanggal' => '2025-01-01', 'nama_libur' => 'Tahun Baru Masehi', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-02-12', 'nama_libur' => 'Imlek', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-03-29', 'nama_libur' => 'Hari Raya Nyepi', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-03-31', 'nama_libur' => 'Wafat Isa Almasih', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-04-09', 'nama_libur' => 'Isra Miraj', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-05-01', 'nama_libur' => 'Hari Buruh', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-05-09', 'nama_libur' => 'Kenaikan Isa Almasih', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-05-29', 'nama_libur' => 'Hari Raya Waisak', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-06-01', 'nama_libur' => 'Hari Lahir Pancasila', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-08-17', 'nama_libur' => 'Hari Kemerdekaan RI', 'jenis' => 'tanggal_merah'],
            ['tanggal' => '2025-12-25', 'nama_libur' => 'Hari Raya Natal', 'jenis' => 'tanggal_merah'],
        ];

        foreach ($hariLibur as $libur) {
            HariLibur::create($libur);
        }
    }
}
