<?php
// database/seeders/SettingGajiSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SettingGaji;

class SettingGajiSeeder extends Seeder
{
    public function run()
    {
        SettingGaji::create([
            'premi_produksi' => 100000,
            'premi_staff' => 75000,
            'uang_makan_produksi_weekday' => 15000,
            'uang_makan_produksi_weekend_5_10' => 20000,
            'uang_makan_produksi_weekend_10_20' => 25000,
            'uang_makan_staff_weekday' => 12000,
            'uang_makan_staff_weekend_5_10' => 17000,
            'uang_makan_staff_weekend_10_20' => 22000,
            'tarif_lembur_produksi_per_jam' => 50000,
            'tarif_lembur_staff_per_jam' => 40000,
            'is_active' => true
        ]);
    }
}