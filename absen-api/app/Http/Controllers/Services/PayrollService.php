<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatGaji;
use App\Models\DetailGaji;
use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
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

            switch ($karyawan->kategori_gaji) {
                case 'Bulanan':
                    $pendapatan['Gaji Pokok'] = $gajiPokokBulanan;
                    // Hitung lembur
                    $totalLemburMenit = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                        ->whereYear('tanggal_absensi', $periodeBulan->year)
                        ->whereMonth('tanggal_absensi', $periodeBulan->month)
                        ->sum('durasi_lembur_menit');
                    
                    if ($totalLemburMenit > 0) {
                        $totalLemburJam = $totalLemburMenit / 60;
                        $pendapatan['Tunjangan Lembur'] = round($totalLemburJam * $rateLemburPerJam, 2);
                    }
                    break;
                
                case 'Harian':
                    $totalHariMasuk = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                        ->whereYear('tanggal_absensi', $periodeBulan->year)
                        ->whereMonth('tanggal_absensi', $periodeBulan->month)
                        ->whereIn('status', ['Hadir', 'Terlambat'])
                        ->count();
                    $pendapatan['Gaji Harian'] = $totalHariMasuk * $gajiHarian;

                     // Hitung lembur (sama seperti bulanan)
                    $totalLemburMenit = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                        ->whereYear('tanggal_absensi', $periodeBulan->year)
                        ->whereMonth('tanggal_absensi', $periodeBulan->month)
                        ->sum('durasi_lembur_menit');

                    if ($totalLemburMenit > 0) {
                        $totalLemburJam = $totalLemburMenit / 60;
                        $pendapatan['Tunjangan Lembur'] = round($totalLemburJam * $rateLemburPerJam, 2);
                    }
                    break;

                case 'Borongan':
                    // Logika untuk gaji borongan, mungkin perlu input manual
                    $pendapatan['Gaji Borongan'] = 0; // Contoh
                    break;
            }

            // Contoh Potongan
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
}
