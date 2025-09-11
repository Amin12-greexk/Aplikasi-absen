<?php

namespace App\Observers;

use App\Models\Absensi;
use App\Models\JadwalShift;
use Carbon\Carbon;

class AbsensiObserver
{
    /**
     * Handle the Absensi "saving" event.
     *
     * Ini akan berjalan setiap kali data Absensi akan dibuat (created) atau diubah (updated).
     */
    public function saving(Absensi $absensi): void
    {
        // Hanya hitung lembur jika jam pulang diisi dan belum pernah dihitung
        if ($absensi->isDirty('jam_scan_pulang') && !is_null($absensi->jam_scan_pulang)) {
            $karyawan = $absensi->karyawan;
            $tanggalAbsensi = Carbon::parse($absensi->tanggal_absensi);
            $jamScanPulang = Carbon::parse($absensi->jam_scan_pulang);
            $jamKerjaPulang = null;

            // Jika karyawan kategori borongan, tidak ada lembur
            if ($karyawan->kategori_gaji === 'Borongan') {
                $absensi->durasi_lembur_menit = 0;
                return;
            }

            // 1. Cek apakah departemen menggunakan shift
            if ($karyawan->departemenSaatIni->menggunakan_shift) {
                // Cari jadwal shift karyawan pada tanggal tersebut
                $jadwal = JadwalShift::where('karyawan_id', $karyawan->karyawan_id)
                    ->where('tanggal_jadwal', $tanggalAbsensi->format('Y-m-d'))
                    ->with('shift')
                    ->first();

                if ($jadwal && $jadwal->shift) {
                    $jamKerjaPulang = Carbon::parse($jadwal->shift->jam_pulang);
                    // Jika shift malam (hari berikutnya)
                    if ($jadwal->shift->hari_berikutnya) {
                         $jamKerjaPulang->setDateFrom($tanggalAbsensi->addDay());
                    } else {
                         $jamKerjaPulang->setDateFrom($tanggalAbsensi);
                    }
                }
            } else {
                // 2. Jika tidak, gunakan jam kerja standar dari tabel karyawan
                if ($karyawan->jam_kerja_pulang) {
                    $jamKerjaPulang = Carbon::parse($karyawan->jam_kerja_pulang);
                    $jamKerjaPulang->setDateFrom($tanggalAbsensi);
                }
            }

            // 3. Hitung durasi lembur jika jam kerja pulang ditemukan
            if ($jamKerjaPulang && $jamScanPulang->isAfter($jamKerjaPulang)) {
                $durasiLembur = $jamScanPulang->diffInMinutes($jamKerjaPulang);
                $absensi->durasi_lembur_menit = $durasiLembur;
            } else {
                 $absensi->durasi_lembur_menit = 0;
            }
        }
    }
}
