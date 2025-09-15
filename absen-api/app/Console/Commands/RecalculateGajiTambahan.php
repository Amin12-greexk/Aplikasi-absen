<?php
// app/Console/Commands/RecalculateGajiTambahan.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GajiTambahanService;
use App\Models\Absensi;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecalculateGajiTambahan extends Command
{
    protected $signature = 'gaji:recalculate {periode} {--karyawan=} {--force}';
    protected $description = 'Recalculate gaji tambahan for specific period';

    protected $gajiTambahanService;

    public function __construct(GajiTambahanService $gajiTambahanService)
    {
        parent::__construct();
        $this->gajiTambahanService = $gajiTambahanService;
    }

    public function handle()
    {
        $periode = $this->argument('periode');
        $karyawanId = $this->option('karyawan');
        $force = $this->option('force');

        // Validate periode format
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $this->error('❌ Invalid periode format. Use YYYY-MM');
            return 1;
        }

        $this->info("🔄 Recalculating gaji tambahan for periode: {$periode}");

        if ($karyawanId) {
            $karyawan = Karyawan::find($karyawanId);
            if (!$karyawan) {
                $this->error("❌ Karyawan with ID {$karyawanId} not found");
                return 1;
            }
            $this->info("👤 Processing for: {$karyawan->nama_lengkap}");
        }

        if (!$force && !$this->confirm('⚠️  This will recalculate all gaji tambahan data. Continue?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            DB::beginTransaction();

            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);

            $query = Absensi::with('karyawan')
                ->whereYear('tanggal_absensi', $year)
                ->whereMonth('tanggal_absensi', $month);

            if ($karyawanId) {
                $query->where('karyawan_id', $karyawanId);
            }

            $absensiList = $query->get();
            $progressBar = $this->output->createProgressBar($absensiList->count());
            $progressBar->start();

            $updated = 0;
            $errors = [];

            foreach ($absensiList as $absensi) {
                try {
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

                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error updating absensi ID {$absensi->absensi_id}: " . $e->getMessage();
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            DB::commit();

            $this->info("✅ Recalculation completed!");
            $this->info("📊 Updated {$updated} attendance records");

            if (!empty($errors)) {
                $this->warn("⚠️  Encountered " . count($errors) . " errors:");
                foreach ($errors as $error) {
                    $this->line("   - {$error}");
                }
            }

        } catch (\Exception $e) {
            DB::rollback();
            $this->error("❌ Recalculation failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}