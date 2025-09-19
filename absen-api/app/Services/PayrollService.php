<?php
// app/Services/PayrollService.php (UPDATED dengan periode flexible)

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatGaji;
use App\Models\DetailGaji;
use App\Models\Absensi;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    protected $gajiTambahanService;

    public function __construct(GajiTambahanService $gajiTambahanService = null)
    {
        $this->gajiTambahanService = $gajiTambahanService ?: new GajiTambahanService();
    }

    /**
     * Generate payroll untuk periode flexible
     */
    public function generateForPeriod(int $karyawan_id, int $period_id): RiwayatGaji
    {
        $karyawan = Karyawan::findOrFail($karyawan_id);
        $payrollPeriod = PayrollPeriod::findOrFail($period_id);

        if ($payrollPeriod->is_closed) {
            throw new \Exception('Periode payroll sudah ditutup');
        }

        return $this->generatePayrollForDateRange(
            $karyawan,
            $payrollPeriod->tanggal_mulai,
            $payrollPeriod->tanggal_selesai,
            $payrollPeriod->tipe_periode,
            $period_id
        );
    }

    /**
     * Generate payroll untuk date range custom
     */
    public function generateForDateRange(int $karyawan_id, string $tanggal_mulai, string $tanggal_selesai, string $tipe_periode = 'mingguan'): RiwayatGaji
    {
        $karyawan = Karyawan::findOrFail($karyawan_id);
        $start = Carbon::parse($tanggal_mulai);
        $end = Carbon::parse($tanggal_selesai);

        return $this->generatePayrollForDateRange($karyawan, $start, $end, $tipe_periode);
    }

    private function generatePayrollForDateRange(Karyawan $karyawan, Carbon $tanggal_mulai, Carbon $tanggal_selesai, string $tipe_periode, int $period_id = null): RiwayatGaji
    {
        return DB::transaction(function () use ($karyawan, $tanggal_mulai, $tanggal_selesai, $tipe_periode, $period_id) {
            
            // Hapus riwayat gaji lama untuk periode yang sama
            RiwayatGaji::where('karyawan_id', $karyawan->karyawan_id)
                ->where('periode_mulai', $tanggal_mulai->format('Y-m-d'))
                ->where('periode_selesai', $tanggal_selesai->format('Y-m-d'))
                ->delete();

            // Buat record riwayat gaji baru
            $riwayatGaji = RiwayatGaji::create([
                'karyawan_id' => $karyawan->karyawan_id,
                'periode' => $this->generatePeriodeString($tanggal_mulai, $tanggal_selesai, $tipe_periode),
                'tipe_periode' => $tipe_periode,
                'periode_mulai' => $tanggal_mulai->format('Y-m-d'),
                'periode_selesai' => $tanggal_selesai->format('Y-m-d'),
                'period_id' => $period_id
            ]);

            $pendapatan = [];
            $potongan = [];

            // Calculate gaji pokok berdasarkan tipe periode dan kategori
            $gajiPokok = $this->calculateGajiPokok($karyawan, $tanggal_mulai, $tanggal_selesai, $tipe_periode);
            if ($gajiPokok > 0) {
                $pendapatan['Gaji Pokok'] = $gajiPokok;
            }

            // Calculate gaji tambahan (lembur, premi, uang makan)
            $gajiTambahan = $this->calculateGajiTambahanForPeriod($karyawan, $tanggal_mulai, $tanggal_selesai);
            $pendapatan = array_merge($pendapatan, $gajiTambahan);

            // Calculate potongan
            $potongan = $this->calculatePotongan($karyawan, $tipe_periode);

            // Simpan detail pendapatan
            foreach($pendapatan as $deskripsi => $jumlah) {
                if ($jumlah > 0) {
                    DetailGaji::create([
                        'gaji_id' => $riwayatGaji->gaji_id,
                        'jenis_komponen' => 'Pendapatan',
                        'deskripsi' => $deskripsi,
                        'jumlah' => $jumlah,
                    ]);
                }
            }
            
            // Simpan detail potongan
            foreach($potongan as $deskripsi => $jumlah) {
                if ($jumlah > 0) {
                    DetailGaji::create([
                        'gaji_id' => $riwayatGaji->gaji_id,
                        'jenis_komponen' => 'Potongan',
                        'deskripsi' => $deskripsi,
                        'jumlah' => $jumlah,
                    ]);
                }
            }

            // Hitung dan simpan gaji final
            $gajiFinal = array_sum($pendapatan) - array_sum($potongan);
            $riwayatGaji->update([
                'gaji_final' => $gajiFinal,
                'tanggal_pembayaran' => now()
            ]);

            return $riwayatGaji->load('detailGaji');
        });
    }

    private function calculateGajiPokok(Karyawan $karyawan, Carbon $tanggal_mulai, Carbon $tanggal_selesai, string $tipe_periode): float
{
    switch ($karyawan->kategori_gaji) {
        case 'Bulanan':
            if ($tipe_periode === 'bulanan') {
                return 5000000; // Harusnya dari field karyawan
            } else {
                $totalDaysInMonth = $tanggal_mulai->daysInMonth;
                $workingDays = $tanggal_mulai->diffInDays($tanggal_selesai) + 1;
                return (5000000 / $totalDaysInMonth) * $workingDays;
            }
            
        case 'Harian':
            $totalHariMasuk = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                ->whereBetween('tanggal_absensi', [$tanggal_mulai, $tanggal_selesai])
                ->whereIn('status', ['Hadir', 'Terlambat'])
                ->count();
            
            // Use tarif_harian from karyawan table
            return $totalHariMasuk * ($karyawan->tarif_harian ?? 150000);
            
        case 'Borongan':
            return 0; // Need specific implementation
            
        default:
            return 0;
    }
}

    private function calculateGajiTambahanForPeriod(Karyawan $karyawan, Carbon $tanggal_mulai, Carbon $tanggal_selesai): array
    {
        $absensiList = Absensi::where('karyawan_id', $karyawan->karyawan_id)
            ->whereBetween('tanggal_absensi', [$tanggal_mulai, $tanggal_selesai])
            ->get();

        $totalLembur = 0;
        $totalPremi = 0;
        $totalUangMakan = 0;

        foreach ($absensiList as $absensi) {
            $totalLembur += $absensi->upah_lembur ?? 0;
            $totalPremi += $absensi->premi ?? 0;
            $totalUangMakan += $absensi->uang_makan ?? 0;
        }

        $result = [];
        if ($totalLembur > 0) $result['Upah Lembur'] = $totalLembur;
        if ($totalPremi > 0) $result['Premi Kehadiran'] = $totalPremi;
        if ($totalUangMakan > 0) $result['Uang Makan'] = $totalUangMakan;

        return $result;
    }

    private function calculatePotongan(Karyawan $karyawan, string $tipe_periode): array
    {
        $potongan = [];
        
        // BPJS - pro-rate untuk periode mingguan/harian
        $bpjsAmount = 50000;
        if ($tipe_periode === 'mingguan') {
            $bpjsAmount = $bpjsAmount / 4; // 1/4 dari bulanan
        } elseif ($tipe_periode === 'harian') {
            $bpjsAmount = $bpjsAmount / 30; // 1/30 dari bulanan
        }
        
        $potongan['BPJS'] = $bpjsAmount;

        return $potongan;
    }

    private function generatePeriodeString(Carbon $tanggal_mulai, Carbon $tanggal_selesai, string $tipe_periode): string
    {
        switch ($tipe_periode) {
            case 'harian':
                return $tanggal_mulai->format('d M Y');
            case 'mingguan':
                return $tanggal_mulai->format('d M') . ' - ' . $tanggal_selesai->format('d M Y');
            case 'bulanan':
                return $tanggal_mulai->format('F Y');
            default:
                return $tanggal_mulai->format('Y-m-d') . ' to ' . $tanggal_selesai->format('Y-m-d');
        }
    }

    // Legacy method untuk backward compatibility
    public function generate(int $karyawan_id, string $periode): RiwayatGaji
    {
        $periodeBulan = Carbon::createFromFormat('Y-m', $periode);
        return $this->generateForDateRange(
            $karyawan_id,
            $periodeBulan->startOfMonth()->format('Y-m-d'),
            $periodeBulan->endOfMonth()->format('Y-m-d'),
            'bulanan'
        );
    }
}