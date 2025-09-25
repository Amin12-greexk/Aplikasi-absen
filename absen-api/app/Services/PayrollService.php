<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatGaji;
use App\Models\DetailGaji;
use App\Models\Absensi;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class PayrollService
{
    protected $gajiTambahanService;

    public function __construct(GajiTambahanService $gajiTambahanService = null)
    {
        $this->gajiTambahanService = $gajiTambahanService ?: new GajiTambahanService();
    }

    /**
     * Generate payroll berdasar payroll_period (flexible period).
     */
    public function generateForPeriod(int $karyawan_id, int $period_id): RiwayatGaji
    {
        $karyawan      = Karyawan::findOrFail($karyawan_id);
        $payrollPeriod = PayrollPeriod::findOrFail($period_id);

        if ($payrollPeriod->is_closed) {
            throw new \Exception('Periode payroll sudah ditutup');
        }

        return $this->generatePayrollForDateRange(
            $karyawan,
            Carbon::parse($payrollPeriod->tanggal_mulai),
            Carbon::parse($payrollPeriod->tanggal_selesai),
            $payrollPeriod->tipe_periode,
            $period_id
        );
    }

    /**
     * Generate payroll untuk rentang tanggal (dipakai oleh route yang menerima start_date/end_date).
     */
    public function generateForDateRange(
        int $karyawan_id,
        string $tanggal_mulai,
        string $tanggal_selesai,
        string $tipe_periode = 'mingguan'
    ): RiwayatGaji {
        $karyawan = Karyawan::findOrFail($karyawan_id);
        $start    = Carbon::parse($tanggal_mulai)->startOfDay();
        $end      = Carbon::parse($tanggal_selesai)->endOfDay();

        return $this->generatePayrollForDateRange($karyawan, $start, $end, $tipe_periode);
    }

    /**
     * Inti pembuatan riwayat gaji untuk satu karyawan dan satu rentang tanggal.
     */
    private function generatePayrollForDateRange(
        Karyawan $karyawan,
        Carbon $tanggal_mulai,
        Carbon $tanggal_selesai,
        string $tipe_periode,
        int $period_id = null
    ): RiwayatGaji {
        return DB::transaction(function () use ($karyawan, $tanggal_mulai, $tanggal_selesai, $tipe_periode, $period_id) {
            // Idempotency: hapus slip existing pada rentang yang sama
            RiwayatGaji::where('karyawan_id', $karyawan->karyawan_id)
                ->where('periode_mulai', $tanggal_mulai->toDateString())
                ->where('periode_selesai', $tanggal_selesai->toDateString())
                ->delete();

            // Buat header riwayat
            $riwayatGaji = RiwayatGaji::create([
                'karyawan_id'     => $karyawan->karyawan_id,
                'periode'         => $this->generatePeriodeString($tanggal_mulai, $tanggal_selesai, $tipe_periode),
                'tipe_periode'    => $tipe_periode,
                'periode_mulai'   => $tanggal_mulai->toDateString(),
                'periode_selesai' => $tanggal_selesai->toDateString(),
                'period_id'       => $period_id,
            ]);

            // ===== Hitung komponen =====

            // 1) Gaji Pokok
            $gajiPokok = $this->calculateGajiPokok($karyawan, $tanggal_mulai, $tanggal_selesai, $tipe_periode);

            // 2) Tambahan (agregat langsung di SQL; lembur negatif di-clamp jadi 0 menit)
            $tambahan = $this->calculateGajiTambahanForPeriod($karyawan, $tanggal_mulai, $tanggal_selesai);

            // 3) Potongan (BPJS prorata by day count)
            $potongan = $this->calculatePotongan($karyawan, $tanggal_mulai, $tanggal_selesai, $tipe_periode);

            // ===== Persist detail =====
            $pendapatan = array_filter(array_merge(
                ['Gaji Pokok' => $gajiPokok],
                $tambahan
            ), fn ($v) => $v > 0);

            foreach ($pendapatan as $desk => $nom) {
                DetailGaji::create([
                    'gaji_id'        => $riwayatGaji->gaji_id,
                    'jenis_komponen' => 'Pendapatan',
                    'deskripsi'      => $desk,
                    'jumlah'         => round($nom, 2),
                ]);
            }

            foreach ($potongan as $desk => $nom) {
                if ($nom > 0) {
                    DetailGaji::create([
                        'gaji_id'        => $riwayatGaji->gaji_id,
                        'jenis_komponen' => 'Potongan',
                        'deskripsi'      => $desk,
                        'jumlah'         => round($nom, 2),
                    ]);
                }
            }

            // Total
            $totalPendapatan = array_sum($pendapatan);
            $totalPotongan   = array_sum($potongan);
            $gajiFinal       = round($totalPendapatan - $totalPotongan, 2);

            $riwayatGaji->update([
                'gaji_final'         => $gajiFinal,
                // tanggal_pembayaran: tetap isi null sampai benar2 dibayar (biar alur status jelas)
                // kalau mau langsung dianggap "dibayar", silakan ganti ke now()
                'tanggal_pembayaran' => null,
            ]);

            return $riwayatGaji->load('detailGaji');
        });
    }

    /**
     * Gaji Pokok:
     * - Bulanan: full jika tipe_periode bulanan, selain itu dihitung proporsional by hari range.
     * - Harian: tarif_harian * jumlah hari hadir/terlambat.
     * - Borongan: 0 (placeholder).
     */
    private function calculateGajiPokok(Karyawan $karyawan, Carbon $mulai, Carbon $selesai, string $tipe_periode): float
    {
        switch ($karyawan->kategori_gaji) {
            case 'Bulanan':
                // TODO: ambil dari field gaji_bulanan di tabel karyawan jika tersedia
                $gajiBulanan = $karyawan->gaji_bulanan ?? 5000000; // fallback sementara
                if ($tipe_periode === 'bulanan'
                    && $mulai->isSameDay($mulai->copy()->startOfMonth())
                    && $selesai->isSameDay($mulai->copy()->endOfMonth())) {
                    return $gajiBulanan;
                }
                $daysInMonth = $mulai->daysInMonth;
                $daysRange   = $mulai->diffInDays($selesai) + 1;
                return ($gajiBulanan / $daysInMonth) * $daysRange;

            case 'Harian':
                $hariMasuk = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                    ->whereBetween('tanggal_absensi', [$mulai->toDateString(), $selesai->toDateString()])
                    ->whereIn('status', ['Hadir', 'Terlambat'])
                    ->count();
                $tarif = $karyawan->tarif_harian ?? 150000;
                return $hariMasuk * $tarif;

            case 'Borongan':
                return 0.0;

            default:
                return 0.0;
        }
    }

    /**
     * Tambahan (Upah Lembur, Premi Kehadiran, Uang Makan) diakumulasi di SQL.
     * Lembur negatif di data diklamping 0 via GREATEST(...,0) saat perhitungan (lihat kolom terkait di tabel absensi). 
     */
    private function calculateGajiTambahanForPeriod(Karyawan $karyawan, Carbon $mulai, Carbon $selesai): array
    {
        // Gunakan agregasi langsung; numeric fields sudah ada di tabel absensi.
        // upah_lembur, premi, uang_makan → sum; durasi_lembur_menit boleh negatif di data
        // tapi nilai upah_lembur sudah tersimpan di kolom sendiri. Tetap aman.
        $agg = Absensi::selectRaw("
                SUM(upah_lembur)    as total_lembur,
                SUM(premi)          as total_premi,
                SUM(uang_makan)     as total_uang_makan,
                SUM(GREATEST(durasi_lembur_menit,0)) as total_menit_lembur_positif
            ")
            ->where('karyawan_id', $karyawan->karyawan_id)
            ->whereBetween('tanggal_absensi', [$mulai->toDateString(), $selesai->toDateString()])
            ->first();

        $result = [];
        if (($agg->total_lembur ?? 0) > 0)     $result['Upah Lembur']       = (float) $agg->total_lembur;
        if (($agg->total_premi ?? 0) > 0)      $result['Premi Kehadiran']   = (float) $agg->total_premi;
        if (($agg->total_uang_makan ?? 0) > 0) $result['Uang Makan']        = (float) $agg->total_uang_makan;

        return $result;
    }

    /**
     * Potongan (BPJS) prorata berdasarkan jumlah hari di range terhadap hari dalam bulan start.
     * Jika tipe_periode 'harian' → prorata per 30 (fallback umum).
     */
    private function calculatePotongan(Karyawan $karyawan, Carbon $mulai, Carbon $selesai, string $tipe_periode): array
    {
        // TODO: Ambil dari setting/konfigurasi jika sudah ada
        $bpjsBulanan = 50000.0;

        $daysRange   = $mulai->diffInDays($selesai) + 1;
        $daysInMonth = $mulai->daysInMonth;

        if ($tipe_periode === 'bulanan'
            && $mulai->isSameDay($mulai->copy()->startOfMonth())
            && $selesai->isSameDay($mulai->copy()->endOfMonth())) {
            $bpjs = $bpjsBulanan;
        } elseif ($tipe_periode === 'harian') {
            // fallback umum harian
            $bpjs = $bpjsBulanan * ($daysRange / 30);
        } else {
            // mingguan / custom: prorata terhadap bulan berjalan
            $bpjs = $bpjsBulanan * ($daysRange / $daysInMonth);
        }

        return [
            'BPJS' => round(max($bpjs, 0), 2),
        ];
    }

    private function generatePeriodeString(Carbon $mulai, Carbon $selesai, string $tipe_periode): string
    {
        switch ($tipe_periode) {
            case 'harian':
                return $mulai->format('d M Y');
            case 'mingguan':
                return $mulai->format('d M') . ' - ' . $selesai->format('d M Y');
            case 'bulanan':
                return $mulai->format('F Y');
            default:
                return $mulai->toDateString() . ' s/d ' . $selesai->toDateString();
        }
    }

    /**
     * Legacy: generate berdasar 'periode' "YYYY-MM".
     */
    public function generate(int $karyawan_id, string $periode): RiwayatGaji
    {
        $periodeBulan = Carbon::createFromFormat('Y-m', $periode);
        return $this->generateForDateRange(
            $karyawan_id,
            $periodeBulan->startOfMonth()->toDateString(),
            $periodeBulan->endOfMonth()->toDateString(),
            'bulanan'
        );
    }
}
