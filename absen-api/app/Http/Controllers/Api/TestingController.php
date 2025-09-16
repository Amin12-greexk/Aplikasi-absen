<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Karyawan;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\SettingGaji;
use App\Services\FingerprintImportService;
use App\Services\GajiTambahanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    protected $fingerprintService;
    protected $gajiTambahanService;

    public function __construct()
    {
        $this->fingerprintService = new FingerprintImportService();
        $this->gajiTambahanService = new GajiTambahanService();
    }

    /**
     * Generate dummy attendance data untuk testing
     */
    public function createSampleAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawan,karyawan_id',
            'date' => 'required|date_format:Y-m-d',
            'check_in_time' => 'required|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i'
        ]);

        try {
            DB::beginTransaction();

            $karyawan = Karyawan::find($request->karyawan_id);
            
            // Generate PIN if doesn't exist
            if (!$karyawan->pin_fingerprint) {
                $pin = str_pad($karyawan->karyawan_id, 5, '0', STR_PAD_LEFT);
                $karyawan->update(['pin_fingerprint' => $pin]);
            }

            $date = Carbon::parse($request->date);
            $checkInTime = $date->copy()->setTimeFromTimeString($request->check_in_time);
            
            // Create check-in log
            $checkInLog = AttendanceLog::create([
                'device_sn' => 'TESTING_DEVICE',
                'pin' => $karyawan->pin_fingerprint,
                'scan_time' => $checkInTime,
                'verify_mode' => 1,
                'inout_mode' => 1,
                'device_ip' => '127.0.0.1',
                'is_processed' => false
            ]);

            $logs = [$checkInLog];

            // Create check-out log if provided
            if ($request->check_out_time) {
                $checkOutTime = $date->copy()->setTimeFromTimeString($request->check_out_time);
                
                $checkOutLog = AttendanceLog::create([
                    'device_sn' => 'TESTING_DEVICE',
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_time' => $checkOutTime,
                    'verify_mode' => 1,
                    'inout_mode' => 2,
                    'device_ip' => '127.0.0.1',
                    'is_processed' => false
                ]);
                
                $logs[] = $checkOutLog;
            }

            // Process logs immediately
            $processResult = $this->fingerprintService->processUnprocessedLogs();

            // Get the created absensi
            $absensi = Absensi::where('karyawan_id', $karyawan->karyawan_id)
                ->where('tanggal_absensi', $date->format('Y-m-d'))
                ->first();

            DB::commit();

            return response()->json([
                'message' => 'Sample attendance created and processed successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint', 'role_karyawan']),
                'logs' => $logs,
                'absensi' => $absensi,
                'process_result' => $processResult
            ]);

       } catch (\Throwable $e) {
        \Log::error('TestingController Error', [
            'msg' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Failed to create sample attendance',
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ], 500);
    }
    }

    /**
     * Generate data absensi untuk satu bulan penuh
     */
    public function generateMonthData($karyawan_id, $periode): JsonResponse
    {
        try {
            DB::beginTransaction();

            $karyawan = Karyawan::findOrFail($karyawan_id);
            
            // Generate PIN if doesn't exist
            if (!$karyawan->pin_fingerprint) {
                $pin = str_pad($karyawan->karyawan_id, 5, '0', STR_PAD_LEFT);
                $karyawan->update(['pin_fingerprint' => $pin]);
            }

            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            $generated = 0;
            $current = $startDate->copy();

            while ($current <= $endDate) {
                // Get jenis hari
                $jenisHari = HariLibur::getJenisHari($current);
                
                // Skip weekend for staff (50% chance)
                if ($karyawan->role_karyawan === 'staff' && $jenisHari === 'weekend' && rand(1, 10) > 5) {
                    $current->addDay();
                    continue;
                }

                // Random chance untuk tidak masuk (10% untuk simulasi alpha)
                if (rand(1, 10) === 1) {
                    $current->addDay();
                    continue;
                }

                // Generate jam masuk (08:00 ± 30 menit)
                $checkInHour = 8;
                $checkInMinute = rand(-30, 30);
                $checkIn = $current->copy()->setTime($checkInHour, 0)->addMinutes($checkInMinute);

                // Generate jam pulang berdasarkan role
                if ($karyawan->role_karyawan === 'produksi') {
                    // Produksi: lebih sering lembur
                    $checkOutHour = rand(17, 20); // 17:00 - 20:00
                } else {
                    // Staff: lembur lebih jarang
                    $checkOutHour = rand(1, 10) > 7 ? rand(18, 19) : 17;
                }
                $checkOutMinute = rand(0, 59);
                $checkOut = $current->copy()->setTime($checkOutHour, $checkOutMinute);

                // Create logs
                AttendanceLog::create([
                    'device_sn' => 'TESTING_DEVICE',
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_time' => $checkIn,
                    'verify_mode' => 1,
                    'inout_mode' => 1,
                    'device_ip' => '127.0.0.1',
                    'is_processed' => false
                ]);

                AttendanceLog::create([
                    'device_sn' => 'TESTING_DEVICE',
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_time' => $checkOut,
                    'verify_mode' => 1,
                    'inout_mode' => 2,
                    'device_ip' => '127.0.0.1',
                    'is_processed' => false
                ]);

                $generated++;
                $current->addDay();
            }

            // Process all logs
            $processResult = $this->fingerprintService->processUnprocessedLogs();

            DB::commit();

            return response()->json([
                'message' => 'Month data generated and processed successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint', 'role_karyawan']),
                'periode' => $periode,
                'days_generated' => $generated,
                'total_logs' => $generated * 2,
                'process_result' => $processResult
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to generate month data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process all unprocessed attendance logs
     */
    public function processTestingData(): JsonResponse
    {
        try {
            $result = $this->fingerprintService->processUnprocessedLogs();

            return response()->json([
                'message' => 'Testing data processed successfully',
                'processed' => $result['processed'],
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process testing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all testing data
     */
    public function clearTestData(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete testing attendance logs
            $deletedLogs = AttendanceLog::where('device_sn', 'TESTING_DEVICE')->delete();
            
            // Delete related absensi records
            $testPins = Karyawan::whereNotNull('pin_fingerprint')
                ->pluck('pin_fingerprint')
                ->toArray();
            
            $deletedAbsensi = 0;
            if (!empty($testPins)) {
                // Get karyawan IDs from test PINs
                $testKaryawanIds = Karyawan::whereIn('pin_fingerprint', $testPins)
                    ->pluck('karyawan_id')
                    ->toArray();
                
                if (!empty($testKaryawanIds)) {
                    $deletedAbsensi = Absensi::whereIn('karyawan_id', $testKaryawanIds)->delete();
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Test data cleared successfully',
                'deleted_logs' => $deletedLogs,
                'deleted_absensi' => $deletedAbsensi
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to clear test data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary for testing
     */
    public function getAttendanceSummary($periode): JsonResponse
    {
        $year = substr($periode, 0, 4);
        $month = substr($periode, 5, 2);

        $summary = Absensi::selectRaw('
                karyawan.nama_lengkap,
                karyawan.role_karyawan,
                COUNT(*) as total_days,
                SUM(CASE WHEN absensi.status = "Hadir" THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN absensi.status = "Terlambat" THEN 1 ELSE 0 END) as terlambat,
                SUM(CASE WHEN absensi.status = "Alpha" THEN 1 ELSE 0 END) as alpha,
                SUM(jam_lembur) as total_jam_lembur,
                SUM(upah_lembur) as total_upah_lembur,
                SUM(premi) as total_premi,
                SUM(uang_makan) as total_uang_makan,
                SUM(total_gaji_tambahan) as total_gaji_tambahan
            ')
            ->join('karyawan', 'absensi.karyawan_id', '=', 'karyawan.karyawan_id')
            ->whereYear('tanggal_absensi', $year)
            ->whereMonth('tanggal_absensi', $month)
            ->groupBy('karyawan.karyawan_id', 'karyawan.nama_lengkap', 'karyawan.role_karyawan')
            ->get();

        // Get active setting
        $setting = SettingGaji::getActiveSetting();

        return response()->json([
            'periode' => $periode,
            'summary' => $summary,
            'active_setting' => $setting ? [
                'premi_produksi' => $setting->premi_produksi,
                'premi_staff' => $setting->premi_staff,
                'tarif_lembur_produksi' => $setting->tarif_lembur_produksi_per_jam,
                'tarif_lembur_staff' => $setting->tarif_lembur_staff_per_jam
            ] : null
        ]);
    }

    /**
     * Test calculation for specific scenario
     */
    public function testCalculation(Request $request): JsonResponse
    {
        $request->validate([
            'role_karyawan' => 'required|in:produksi,staff',
            'jenis_hari' => 'required|in:weekday,weekend,tanggal_merah',
            'jam_lembur' => 'required|numeric|min:0',
            'jam_selesai_kerja' => 'required|date_format:H:i',
            'hadir_6_hari' => 'required|boolean'
        ]);

        try {
            $result = $this->gajiTambahanService->hitungGajiTambahan(
                $request->role_karyawan,
                $request->jenis_hari,
                $request->jam_lembur,
                Carbon::createFromFormat('H:i', $request->jam_selesai_kerja),
                $request->hadir_6_hari
            );

            // Get active setting for reference
            $setting = SettingGaji::getActiveSetting();

            return response()->json([
                'message' => 'Calculation test successful',
                'input' => $request->all(),
                'result' => $result,
                'setting_used' => $setting ? [
                    'setting_id' => $setting->setting_id,
                    'tarif_lembur' => $request->role_karyawan === 'produksi' 
                        ? $setting->tarif_lembur_produksi_per_jam 
                        : $setting->tarif_lembur_staff_per_jam,
                    'premi' => $request->role_karyawan === 'produksi'
                        ? $setting->premi_produksi
                        : $setting->premi_staff
                ] : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Calculation test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate sample data for all karyawan
     */
    public function generateSampleDataForAll(Request $request): JsonResponse
    {
        $request->validate([
            'periode' => 'required|date_format:Y-m'
        ]);

        try {
            DB::beginTransaction();

            $karyawanList = Karyawan::where('status', 'Aktif')->get();
            $results = [];

            foreach ($karyawanList as $karyawan) {
                // Generate PIN if doesn't exist
                if (!$karyawan->pin_fingerprint) {
                    $pin = str_pad($karyawan->karyawan_id, 5, '0', STR_PAD_LEFT);
                    $karyawan->update(['pin_fingerprint' => $pin]);
                }

                // Generate attendance for the period
                $year = substr($request->periode, 0, 4);
                $month = substr($request->periode, 5, 2);
                $startDate = Carbon::create($year, $month, 1);
                $endDate = $startDate->copy()->endOfMonth();

                $generated = 0;
                $current = $startDate->copy();

                while ($current <= $endDate) {
                    $jenisHari = HariLibur::getJenisHari($current);
                    
                    // Different patterns for different roles
                    if ($karyawan->role_karyawan === 'staff') {
                        // Staff: no weekend work usually
                        if ($jenisHari === 'weekend' && rand(1, 10) > 2) {
                            $current->addDay();
                            continue;
                        }
                    }

                    // Random absence (5% chance)
                    if (rand(1, 100) <= 5) {
                        $current->addDay();
                        continue;
                    }

                    // Generate check in/out times
                    $checkIn = $current->copy()->setTime(8, 0)->addMinutes(rand(-15, 30));
                    $checkOut = $current->copy()->setTime(17, 0)->addMinutes(rand(0, 180)); // 0-3 hours overtime

                    // Create logs
                    AttendanceLog::create([
                        'device_sn' => 'TESTING_DEVICE',
                        'pin' => $karyawan->pin_fingerprint,
                        'scan_time' => $checkIn,
                        'verify_mode' => 1,
                        'inout_mode' => 1,
                        'device_ip' => '127.0.0.1',
                        'is_processed' => false
                    ]);

                    AttendanceLog::create([
                        'device_sn' => 'TESTING_DEVICE',
                        'pin' => $karyawan->pin_fingerprint,
                        'scan_time' => $checkOut,
                        'verify_mode' => 1,
                        'inout_mode' => 2,
                        'device_ip' => '127.0.0.1',
                        'is_processed' => false
                    ]);

                    $generated++;
                    $current->addDay();
                }

                $results[] = [
                    'karyawan' => $karyawan->nama_lengkap,
                    'days_generated' => $generated
                ];
            }

            // Process all logs
            $processResult = $this->fingerprintService->processUnprocessedLogs();

            DB::commit();

            return response()->json([
                'message' => 'Sample data generated for all karyawan',
                'periode' => $request->periode,
                'results' => $results,
                'process_result' => $processResult
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to generate sample data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}