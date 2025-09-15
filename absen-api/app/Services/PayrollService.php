<?php
// app/Services/PayrollService.php (UPDATED)

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatGaji;
use App\Models\DetailGaji;
use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    protected $gajiTambahanService;

    public function __construct(GajiTambahanService $gajiTambahanService = null)
    {
        $this->gajiTambahanService = $gajiTambahanService ?: new GajiTambahanService();
    }

    public function generate(int $karyawan_id, string $periode): RiwayatGaji
    {
        $karyawan = Karyawan::findOrFail($karyawan_id);
        $periodeBulan = Carbon::createFromFormat('Y-m', $periode);

        // Placeholder untuk aturan gaji, idealnya ini disimpan di database
        $gajiPokokBulanan = 5000000;
        $gajiHarian = 150000;
        $rateLemburPerJam = 25000;

        return DB::transaction(function () use ($karyawan, $periodeBulan, $gajiPokokBulanan, $gajiHarian, $rateLemburPerJam) {
            
            $pendapatan = [];
            $potongan = [];
            
            // Hapus riwayat gaji lama jika ada untuk periode ini
            RiwayatGaji::where('karyawan_id', $karyawan->karyawan_id)
                ->where('periode', $periodeBulan->format('Y-m'))
                ->delete();

            // Buat record riwayat gaji baru
            $riwayatGaji = RiwayatGaji::create([
                'karyawan_id' => $karyawan->karyawan_id,
                'periode' => $periodeBulan->format('Y-m'),
            ]);

            // ===========================================
            // GAJI POKOK (EXISTING LOGIC)
            // ===========================================
            switch ($karyawan->kategori_gaji) {
                case 'Bulanan':
                    $pendapatan['Gaji Pokok'] = $gajiPokokBulanan;
                    break;
                
                case 'Harian':
                    $totalHariMasuk = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                        ->whereYear('tanggal_absensi', $periodeBulan->year)
                        ->whereMonth('tanggal_absensi', $periodeBulan->month)
                        ->whereIn('status', ['Hadir', 'Terlambat'])
                        ->count();
                    $pendapatan['Gaji Harian'] = $totalHariMasuk * $gajiHarian;
                    break;

                case 'Borongan':
                    $pendapatan['Gaji Borongan'] = 0; // Contoh
                    break;
            }

            // ===========================================
            // GAJI TAMBAHAN (NEW INTEGRATION)
            // ===========================================
            try {
                $gajiTambahanData = $this->gajiTambahanService->hitungGajiTambahanPeriode(
                    $karyawan->karyawan_id, 
                    $periodeBulan->format('Y-m')
                );

                // Tambahkan komponen gaji tambahan ke pendapatan
                if ($gajiTambahanData['total_upah_lembur'] > 0) {
                    $pendapatan['Upah Lembur'] = $gajiTambahanData['total_upah_lembur'];
                }

                if ($gajiTambahanData['total_premi'] > 0) {
                    $pendapatan['Premi Kehadiran'] = $gajiTambahanData['total_premi'];
                }

                if ($gajiTambahanData['total_uang_makan'] > 0) {
                    $pendapatan['Uang Makan'] = $gajiTambahanData['total_uang_makan'];
                }

            } catch (\Exception $e) {
                // Log error tapi tetap lanjutkan proses payroll
                \Log::error("Error calculating gaji tambahan for karyawan {$karyawan_id}", [
                    'error' => $e->getMessage(),
                    'periode' => $periode
                ]);
            }

            // ===========================================
            // POTONGAN (EXISTING LOGIC)
            // ===========================================
            $potongan['BPJS'] = -50000;

            // Simpan semua detail pendapatan
            foreach($pendapatan as $deskripsi => $jumlah) {
                DetailGaji::create([
                    'gaji_id' => $riwayatGaji->gaji_id,
                    'jenis_komponen' => 'Pendapatan',
                    'deskripsi' => $deskripsi,
                    'jumlah' => $jumlah,
                ]);
            }
            
            // Simpan semua detail potongan
            foreach($potongan as $deskripsi => $jumlah) {
                DetailGaji::create([
                    'gaji_id' => $riwayatGaji->gaji_id,
                    'jenis_komponen' => 'Potongan',
                    'deskripsi' => $deskripsi,
                    'jumlah' => abs($jumlah), // Simpan sebagai angka positif
                ]);
            }

            // Hitung dan simpan gaji final
            $gajiFinal = array_sum($pendapatan) + array_sum($potongan);
            $riwayatGaji->update([
                'gaji_final' => $gajiFinal,
                'tanggal_pembayaran' => now()
            ]);

            return $riwayatGaji->load('detailGaji');
        });
    }

    /**
     * Generate payroll dengan recalculate gaji tambahan
     */
    public function generateWithRecalculate(int $karyawan_id, string $periode): RiwayatGaji
    {
        // Recalculate gaji tambahan dulu untuk memastikan data terbaru
        try {
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);

            $absensiList = Absensi::with('karyawan')
                ->where('karyawan_id', $karyawan_id)
                ->whereYear('tanggal_absensi', $year)
                ->whereMonth('tanggal_absensi', $month)
                ->get();

            foreach ($absensiList as $absensi) {
                if ($absensi->jam_scan_masuk && $absensi->jam_scan_pulang) {
                    $jamLembur = $absensi->calculateJamLembur();
                    $hadirMinimal6Hari = $absensi->karyawan->isMemenuiSyaratPremi($periode);

                    $gajiTambahan = $this->gajiTambahanService->hitungGajiTambahan(
                        $absensi->karyawan->role_karyawan,
                        $absensi->jenis_hari,
                        $jamLembur,
                        $absensi->jam_scan_pulang,
                        $hadirMinimal6Hari
                    );

                    $absensi->update([
                        'jam_lembur' => $jamLembur,
                        'hadir_6_hari_periode' => $hadirMinimal6Hari,
                        'upah_lembur' => $gajiTambahan['lembur_pay'],
                        'premi' => $gajiTambahan['premi'],
                        'uang_makan' => $gajiTambahan['uang_makan'],
                        'total_gaji_tambahan' => $gajiTambahan['total_tambahan']
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error recalculating gaji tambahan", [
                'karyawan_id' => $karyawan_id,
                'periode' => $periode,
                'error' => $e->getMessage()
            ]);
        }

        // Generate payroll normal
        return $this->generate($karyawan_id, $periode);
    }
}