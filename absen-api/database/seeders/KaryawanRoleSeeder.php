<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Karyawan;
use App\Models\Departemen;
use App\Models\Jabatan;
use Illuminate\Support\Facades\Hash;

class KaryawanRoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Departemen dan Jabatan
        $depIT = Departemen::create(['nama_departemen' => 'IT / Developer', 'menggunakan_shift' => false]);
        $depHR = Departemen::create(['nama_departemen' => 'Human Resources', 'menggunakan_shift' => false]);
        $depOps = Departemen::create(['nama_departemen' => 'Operasional', 'menggunakan_shift' => false]);

        $jabIT = Jabatan::create(['nama_jabatan' => 'IT Staff']);
        $jabHR = Jabatan::create(['nama_jabatan' => 'HR Manager']);
        $jabDir = Jabatan::create(['nama_jabatan' => 'Direktur']);
        $jabKar = Jabatan::create(['nama_jabatan' => 'Staff']);

        // 2. Data default
        $defaultAlamat = 'Jl. Contoh No. 123, Jakarta';
        $defaultTelepon = '081234567890';
        $defaultMasuk = now();
        $jamMasuk = '08:00:00';
        $jamPulang = '17:00:00';

        // 3. Buat Karyawan Developer
        $nik_it = '1111';
        Karyawan::create([
            'nik' => $nik_it,
            'nama_lengkap' => 'Developer',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1995-01-01',
            'jenis_kelamin' => 'Laki-laki',
            'alamat' => $defaultAlamat,
            'status_perkawinan' => 'Menikah',
            'nomor_telepon' => $defaultTelepon,
            'email' => 'dev@tunasesta.com',
            'password' => Hash::make('tunasesta' . $nik_it),
            'role' => 'it_dev',
            'tanggal_masuk' => $defaultMasuk,
            'kategori_gaji' => 'Bulanan',
            'jam_kerja_masuk' => $jamMasuk,
            'jam_kerja_pulang' => $jamPulang,
            'status' => 'Aktif',
            'departemen_id_saat_ini' => $depIT->departemen_id,
            'jabatan_id_saat_ini' => $jabIT->jabatan_id,
        ]);

        // HR Manager
        $nik_hr = '2222';
        Karyawan::create([
            'nik' => $nik_hr,
            'nama_lengkap' => 'HR Manager',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1988-05-20',
            'jenis_kelamin' => 'Perempuan',
            'alamat' => $defaultAlamat,
            'status_perkawinan' => 'Menikah',
            'nomor_telepon' => $defaultTelepon,
            'email' => 'hr@tunasesta.com',
            'password' => Hash::make('tunasesta' . $nik_hr),
            'role' => 'hr',
            'tanggal_masuk' => $defaultMasuk,
            'kategori_gaji' => 'Bulanan',
            'jam_kerja_masuk' => $jamMasuk,
            'jam_kerja_pulang' => $jamPulang,
            'status' => 'Aktif',
            'departemen_id_saat_ini' => $depHR->departemen_id,
            'jabatan_id_saat_ini' => $jabHR->jabatan_id,
        ]);

        // Direktur
        $nik_dir = '3333';
        Karyawan::create([
            'nik' => $nik_dir,
            'nama_lengkap' => 'Direktur Operasional',
            'tempat_lahir' => 'Surabaya',
            'tanggal_lahir' => '1970-11-15',
            'jenis_kelamin' => 'Laki-laki',
            'alamat' => $defaultAlamat,
            'status_perkawinan' => 'Menikah',
            'nomor_telepon' => $defaultTelepon,
            'email' => 'direktur@tunasesta.com',
            'password' => Hash::make('tunasesta' . $nik_dir),
            'role' => 'direktur',
            'tanggal_masuk' => $defaultMasuk,
            'kategori_gaji' => 'Bulanan',
            'jam_kerja_masuk' => $jamMasuk,
            'jam_kerja_pulang' => $jamPulang,
            'status' => 'Aktif',
            'departemen_id_saat_ini' => $depOps->departemen_id,
            'jabatan_id_saat_ini' => $jabDir->jabatan_id,
        ]);

        // Karyawan Biasa
        $nik_kar = '4444';
        Karyawan::create([
            'nik' => $nik_kar,
            'nama_lengkap' => 'Karyawan Biasa',
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2000-06-10',
            'jenis_kelamin' => 'Laki-laki',
            'alamat' => $defaultAlamat,
            'status_perkawinan' => 'Belum Menikah',
            'nomor_telepon' => $defaultTelepon,
            'email' => 'karyawan@tunasesta.com',
            'password' => Hash::make('tunasesta' . $nik_kar),
            'role' => 'karyawan',
            'tanggal_masuk' => $defaultMasuk,
            'kategori_gaji' => 'Harian',
            'jam_kerja_masuk' => $jamMasuk,
            'jam_kerja_pulang' => $jamPulang,
            'status' => 'Aktif',
            'departemen_id_saat_ini' => $depOps->departemen_id,
            'jabatan_id_saat_ini' => $jabKar->jabatan_id,
        ]);
    }
}
