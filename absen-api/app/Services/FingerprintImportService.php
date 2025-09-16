<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Karyawan;
use App\Models\Absensi;
use App\Models\KehadiranPeriode;
use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FingerprintImportService
{
    protected $gajiTambahanService;

    public function __construct()
    {
        $this->gajiTambahanService = new GajiTambahanService();
    }

    /**
     * Process unprocessed attendance logs into absensi records
     */
    public function processUnprocessedLogs()
    {
        $processedCount = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            // Get all unprocessed logs grouped by PIN and date
            $unprocessedLogs = AttendanceLog::where('is_processed', false)
                ->orderBy('scan_time', 'asc')
                ->get()
                ->groupBy(function ($log) {
                    return $log->pin . '_' . Carbon::parse($log->scan_time)->format('Y-m-d');
                });

            foreach ($unprocessedLogs as $key => $logsPerDay) {
                try {
                    list($pin, $date) = explode('_', $key);
                    
                    // Find karyawan by PIN
                    $karyawan = Karyawan::where('pin_fingerprint', $pin)->first();
                    
                    if (!$karyawan) {
                        Log::warning("Karyawan with PIN {$pin} not found, skipping logs");
                        
                        // Mark logs as processed anyway to avoid reprocessing
                        foreach ($logsPerDay as $log) {
                            $log->markAsProcessed();
                        }
                        continue;
                    }

                    // Process logs for this karyawan on this date
                    $this->processKaryawanDailyLogs($karyawan, $logsPerDay, $date);
                    $processedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error processing logs for {$key}: " . $e->getMessage();
                    Log::error("Error processing attendance log", [
                        'key' => $key,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Failed to process attendance logs", ['error' => $e->getMessage()]);
            $errors[] = "General error: " . $e->getMessage();
        }

        return [
            'processed' => $processedCount,
            'errors' => $errors
        ];
    }

    /**
     * Process daily logs for a specific karyawan
     */
    private function processKaryawanDailyLogs(Karyawan $karyawan, $logs, $date)
    {
        // Sort logs by time
        $sortedLogs = $logs->sortBy('scan_time');
        
        // Get first scan (check in) and last scan (check out)
        $firstScan = $sortedLogs->first();
        $lastScan = $sortedLogs->count() > 1 ? $sortedLogs->last() : null;

        $jamMasuk = Carbon::parse($firstScan->scan_time);
        $jamPulang = $lastScan ? Carbon::parse($lastScan->scan_time) : null;

        // Determine status
        $status = $this->determineStatus($karyawan, $jamMasuk, $date);

        // Get jenis hari
        $jenisHari = HariLibur::getJenisHari($date);

        // Calculate jam lembur if check out exists
        $jamLembur = 0;
        if ($jamPulang) {
            $jamLembur = $this->calculateJamLembur($jamMasuk, $jamPulang);
        }

        // Check if karyawan meets premi requirement for the period
        $periode = Carbon::parse($date)->format('Y-m');
        $hadirMinimal6Hari = $this->checkHadirMinimal6Hari($karyawan->karyawan_id, $periode);

        // Calculate gaji tambahan if both check in and check out exist
        $gajiTambahan = [
            'upah_lembur' => 0,
            'premi' => 0,
            'uang_makan' => 0,
            'total_tambahan' => 0
        ];

        if ($jamPulang) {
            $gajiTambahan = $this->gajiTambahanService->hitungGajiTambahan(
                $karyawan->role_karyawan,
                $jenisHari,
                $jamLembur,
                $jamPulang,
                $hadirMinimal6Hari
            );
        }

        // Create or update absensi record
        $absensi = Absensi::updateOrCreate(
            [
                'karyawan_id' => $karyawan->karyawan_id,
                'tanggal_absensi' => $date
            ],
            [
                'jam_scan_masuk' => $jamMasuk,
                'jam_scan_pulang' => $jamPulang,
                'status' => $status,
                'jenis_hari' => $jenisHari,
                'jam_lembur' => $jamLembur,
                'hadir_6_hari_periode' => $hadirMinimal6Hari,
                'upah_lembur' => $gajiTambahan['upah_lembur'] ?? $gajiTambahan['lembur_pay'] ?? 0,
                'premi' => $gajiTambahan['premi'] ?? 0,
                'uang_makan' => $gajiTambahan['uang_makan'] ?? 0,
                'total_gaji_tambahan' => $gajiTambahan['total_tambahan'] ?? 0
            ]
        );

        // Update kehadiran periode
        $this->updateKehadiranPeriode($karyawan->karyawan_id, $periode);

        // Mark all logs as processed
        foreach ($logs as $log) {
            $log->markAsProcessed();
        }

        Log::info("Processed attendance for karyawan {$karyawan->nama_lengkap} on {$date}", [
            'absensi_id' => $absensi->absensi_id,
            'status' => $status,
            'jam_lembur' => $jamLembur,
            'total_gaji_tambahan' => $gajiTambahan['total_tambahan'] ?? 0
        ]);
    }

    /**
     * Determine attendance status
     */
    private function determineStatus(Karyawan $karyawan, Carbon $jamMasuk, $date)
    {
        // Check if it's a holiday
        $jenisHari = HariLibur::getJenisHari($date);
        if ($jenisHari === 'tanggal_merah') {
            return 'Libur';
        }

        // Get scheduled work time
        $jamKerjaMasuk = $karyawan->jam_kerja_masuk ?? '08:00:00';
        $scheduledTime = Carbon::parse($date . ' ' . $jamKerjaMasuk);

        // Add 30 minutes tolerance for late
        $toleranceTime = $scheduledTime->copy()->addMinutes(30);

        if ($jamMasuk->lte($toleranceTime)) {
            return 'Hadir';
        } else {
            return 'Terlambat';
        }
    }

    /**
     * Calculate overtime hours
     */
    private function calculateJamLembur(Carbon $jamMasuk, Carbon $jamPulang)
    {
        // Total hours worked
        $totalJamKerja = $jamMasuk->diffInHours($jamPulang);

        // Normal working hours = 8 hours + 1 hour break = 9 hours
        $jamKerjaNormal = 9;

        // Overtime = total hours - normal hours
        $jamLembur = max(0, $totalJamKerja - $jamKerjaNormal);

        return $jamLembur;
    }

    /**
     * Check if karyawan has attended at least 6 days in the period
     */
    private function checkHadirMinimal6Hari($karyawanId, $periode)
    {
        $year = substr($periode, 0, 4);
        $month = substr($periode, 5, 2);

        $totalHadir = Absensi::where('karyawan_id', $karyawanId)
            ->whereYear('tanggal_absensi', $year)
            ->whereMonth('tanggal_absensi', $month)
            ->whereIn('status', ['Hadir', 'Terlambat'])
            ->count();

        return $totalHadir >= 6;
    }

    /**
     * Update kehadiran periode record
     */
    private function updateKehadiranPeriode($karyawanId, $periode)
    {
        $year = substr($periode, 0, 4);
        $month = substr($periode, 5, 2);

        $totalHadir = Absensi::where('karyawan_id', $karyawanId)
            ->whereYear('tanggal_absensi', $year)
            ->whereMonth('tanggal_absensi', $month)
            ->whereIn('status', ['Hadir', 'Terlambat'])
            ->count();

        $memenuiSyaratPremi = $totalHadir >= 6;

        KehadiranPeriode::updateOrCreate(
            [
                'karyawan_id' => $karyawanId,
                'periode' => $periode
            ],
            [
                'total_hari_hadir' => $totalHadir,
                'memenuhi_syarat_premi' => $memenuiSyaratPremi
            ]
        );
    }

    /**
     * Import from SQL file (for testing/migration)
     */
    public function importFromSQLFile($sqlContent)
    {
        $imported = 0;
        $errors = [];

        try {
            // Parse SQL content and extract INSERT statements for attendance_log
            $lines = explode("\n", $sqlContent);
            $insertStatements = [];
            
            foreach ($lines as $line) {
                if (stripos($line, 'INSERT INTO `attendance_log`') !== false) {
                    $insertStatements[] = $line;
                }
            }

            foreach ($insertStatements as $statement) {
                try {
                    DB::statement($statement);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to execute: " . substr($statement, 0, 100) . "... Error: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            $errors[] = "General import error: " . $e->getMessage();
        }

        return [
            'success' => count($errors) === 0,
            'imported' => $imported,
            'errors' => $errors
        ];
    }
}