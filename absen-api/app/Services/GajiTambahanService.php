<?php

namespace App\Services;

use App\Models\SettingGaji;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\HariLibur;
use App\Models\KehadiranPeriode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GajiTambahanService
{
    private $setting;

    public function __construct()
    {
        $this->setting = SettingGaji::getActiveSetting();
        
        // Fallback jika belum ada setting aktif
        if (!$this->setting) {
            Log::warning('No active SettingGaji found, creating default');
            $this->setting = SettingGaji::create([
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

    /**
     * Hitung gaji tambahan sesuai pseudocode
     * 
     * @param string $roleKaryawan "produksi" atau "staff"
     * @param string $jenisHari "weekday", "weekend", "tanggal_merah"
     * @param float $jamLembur jumlah jam lembur
     * @param Carbon|string $jamSelesaiKerja jam pulang kerja
     * @param bool $hadir6Hari apakah hadir minimal 6 hari dalam periode
     * @return array
     */
    public function hitungGajiTambahan($roleKaryawan, $jenisHari, $jamLembur, $jamSelesaiKerja, $hadir6Hari)
    {
        // Ensure jamSelesaiKerja is Carbon instance
        if (!($jamSelesaiKerja instanceof Carbon)) {
            $jamSelesaiKerja = Carbon::parse($jamSelesaiKerja);
        }

        // 1. HITUNG UPAH LEMBUR
        $lemburPay = $this->hitungUpahLembur($roleKaryawan, $jenisHari, $jamLembur);

        // 2. HITUNG PREMI
        $premi = $this->hitungPremi($roleKaryawan, $hadir6Hari);

        // 3. HITUNG UANG MAKAN
        $uangMakan = $this->hitungUangMakan($roleKaryawan, $jenisHari, $jamLembur, $jamSelesaiKerja);

        // 4. TOTAL GAJI TAMBAHAN
        $totalTambahan = $lemburPay + $premi + $uangMakan;

        return [
            'lembur_pay' => $lemburPay,
            'premi' => $premi,
            'uang_makan' => $uangMakan,
            'total_tambahan' => $totalTambahan,
            'details' => [
                'role_karyawan' => $roleKaryawan,
                'jenis_hari' => $jenisHari,
                'jam_lembur' => $jamLembur,
                'jam_selesai' => $jamSelesaiKerja->format('H:i'),
                'hadir_6_hari' => $hadir6Hari
            ]
        ];
    }

    /**
     * Hitung upah lembur sesuai pseudocode
     */
    private function hitungUpahLembur($roleKaryawan, $jenisHari, $jamLembur)
    {
        if ($jamLembur <= 0) {
            return 0;
        }

        // Get tarif lembur per jam berdasarkan role
        $tarifPerJam = $roleKaryawan === 'produksi' 
            ? $this->setting->tarif_lembur_produksi_per_jam 
            : $this->setting->tarif_lembur_staff_per_jam;

        // Kalkulasi berdasarkan jenis hari
        if ($jenisHari === 'weekday') {
            // Weekday: tarif normal
            $lemburPay = $jamLembur * $tarifPerJam;
        } else {
            // Weekend atau tanggal merah: tarif x2
            $lemburPay = $jamLembur * $tarifPerJam * 2;
        }

        return $lemburPay;
    }

    /**
     * Hitung premi sesuai pseudocode
     */
    private function hitungPremi($roleKaryawan, $hadir6Hari)
    {
        $premi = 0;

        // Premi hanya diberikan jika hadir minimal 6 hari
        if ($hadir6Hari === true) {
            if ($roleKaryawan === 'produksi') {
                $premi = $this->setting->premi_produksi;
            } elseif ($roleKaryawan === 'staff') {
                $premi = $this->setting->premi_staff;
            }
        }

        return $premi;
    }

    /**
     * Hitung uang makan sesuai pseudocode
     */
    private function hitungUangMakan($roleKaryawan, $jenisHari, $jamLembur, Carbon $jamSelesaiKerja)
    {
        $uangMakan = 0;

        if ($roleKaryawan === 'produksi') {
            if ($jenisHari === 'weekday') {
                // Weekday: cek jam pulang >= 19:00
                if ($jamSelesaiKerja->hour >= 19) {
                    $uangMakan = $this->setting->uang_makan_produksi_weekday;
                }
            } elseif ($jenisHari === 'weekend' || $jenisHari === 'tanggal_merah') {
                // Weekend/tanggal merah: berdasarkan jam lembur
                if ($jamLembur >= 5 && $jamLembur <= 10) {
                    $uangMakan = $this->setting->uang_makan_produksi_weekend_5_10;
                } elseif ($jamLembur > 10 && $jamLembur <= 20) {
                    $uangMakan = $this->setting->uang_makan_produksi_weekend_10_20;
                }
            }
        } elseif ($roleKaryawan === 'staff') {
            if ($jenisHari === 'weekday') {
                // Weekday: cek jam pulang >= 19:00
                if ($jamSelesaiKerja->hour >= 19) {
                    $uangMakan = $this->setting->uang_makan_staff_weekday;
                }
            } elseif ($jenisHari === 'weekend' || $jenisHari === 'tanggal_merah') {
                // Weekend/tanggal merah: berdasarkan jam lembur
                if ($jamLembur >= 5 && $jamLembur <= 10) {
                    $uangMakan = $this->setting->uang_makan_staff_weekend_5_10;
                } elseif ($jamLembur > 10 && $jamLembur <= 20) {
                    $uangMakan = $this->setting->uang_makan_staff_weekend_10_20;
                }
            }
        }

        return $uangMakan;
    }

    /**
     * Hitung gaji tambahan untuk satu periode (bulanan)
     */
    public function hitungGajiTambahanPeriode($karyawanId, $periode)
    {
        $karyawan = Karyawan::findOrFail($karyawanId);
        $year = substr($periode, 0, 4);
        $month = substr($periode, 5, 2);

        // Get all absensi dalam periode
        $absensiList = Absensi::where('karyawan_id', $karyawanId)
            ->whereYear('tanggal_absensi', $year)
            ->whereMonth('tanggal_absensi', $month)
            ->get();

        // Check kehadiran untuk premi
        $totalHariHadir = $absensiList->whereIn('status', ['Hadir', 'Terlambat'])->count();
        $memenuiSyaratPremi = $totalHariHadir >= 6;

        // Update atau create kehadiran periode
        KehadiranPeriode::updateOrCreate(
            [
                'karyawan_id' => $karyawanId,
                'periode' => $periode
            ],
            [
                'total_hari_hadir' => $totalHariHadir,
                'memenuhi_syarat_premi' => $memenuiSyaratPremi
            ]
        );

        $totalLembur = 0;
        $totalPremi = 0;
        $totalUangMakan = 0;
        $details = [];

        foreach ($absensiList as $absensi) {
            // Skip jika tidak ada jam pulang
            if (!$absensi->jam_scan_pulang) {
                continue;
            }

            // Determine jenis hari
            $jenisHari = HariLibur::getJenisHari($absensi->tanggal_absensi);

            // Calculate jam lembur
            $jamLembur = $this->calculateJamLembur($absensi);

            // Hitung gaji tambahan per hari
            $gajiHarian = $this->hitungGajiTambahan(
                $karyawan->role_karyawan,
                $jenisHari,
                $jamLembur,
                $absensi->jam_scan_pulang,
                $memenuiSyaratPremi
            );

            // Update absensi dengan hasil perhitungan
            $absensi->update([
                'jenis_hari' => $jenisHari,
                'jam_lembur' => $jamLembur,
                'hadir_6_hari_periode' => $memenuiSyaratPremi,
                'upah_lembur' => $gajiHarian['lembur_pay'],
                'premi' => $gajiHarian['premi'],
                'uang_makan' => $gajiHarian['uang_makan'],
                'total_gaji_tambahan' => $gajiHarian['total_tambahan']
            ]);

            $totalLembur += $gajiHarian['lembur_pay'];
            $totalPremi += $gajiHarian['premi'];
            $totalUangMakan += $gajiHarian['uang_makan'];

            $details[] = [
                'tanggal' => $absensi->tanggal_absensi->format('Y-m-d'),
                'jenis_hari' => $jenisHari,
                'jam_lembur' => $jamLembur,
                'upah_lembur' => $gajiHarian['lembur_pay'],
                'premi' => $gajiHarian['premi'],
                'uang_makan' => $gajiHarian['uang_makan'],
                'total' => $gajiHarian['total_tambahan']
            ];
        }

        return [
            'periode' => $periode,
            'total_hari_hadir' => $totalHariHadir,
            'memenuhi_syarat_premi' => $memenuiSyaratPremi,
            'total_upah_lembur' => $totalLembur,
            'total_premi' => $totalPremi,
            'total_uang_makan' => $totalUangMakan,
            'total_gaji_tambahan' => $totalLembur + $totalPremi + $totalUangMakan,
            'details' => $details
        ];
    }

    /**
     * Calculate jam lembur dari absensi
     */
    private function calculateJamLembur(Absensi $absensi)
    {
        if (!$absensi->jam_scan_masuk || !$absensi->jam_scan_pulang) {
            return 0;
        }

        $jamMasuk = Carbon::parse($absensi->jam_scan_masuk);
        $jamPulang = Carbon::parse($absensi->jam_scan_pulang);

        // Total jam kerja
        $totalJamKerja = $jamMasuk->diffInHours($jamPulang);

        // Jam kerja normal = 8 jam + 1 jam istirahat = 9 jam
        $jamKerjaNormal = 9;

        // Jam lembur = total jam kerja - jam normal
        $jamLembur = max(0, $totalJamKerja - $jamKerjaNormal);

        return $jamLembur;
    }
}