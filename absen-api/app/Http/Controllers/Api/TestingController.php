<?php
// app/Http/Controllers/Api/TestingController.php (UPDATED for att_log structure)

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
            
            // Create check-in log dengan struktur att_log
            $checkInLog = AttendanceLog::create([
                'sn' => 'TESTING_DEVICE_001', // Updated field name
                'pin' => $karyawan->pin_fingerprint,
                'scan_date' => $checkInTime, // Updated field name
                'verifymode' => 1, // Updated field name
                'inoutmode' => 1, // Updated field name
                'device_ip' => '127.0.0.1',
                'is_processed' => false
            ]);

            $logs = [$checkInLog];

            // Create check-out log if provided
            if ($request->check_out_time) {
                $checkOutTime = $date->copy()->setTimeFromTimeString($request->check_out_time);
                
                $checkOutLog = AttendanceLog::create([
                    'sn' => 'TESTING_DEVICE_001',
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_date' => $checkOutTime,
                    'verifymode' => 1,
                    'inoutmode' => 2,
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
            DB::rollback();
            \Log::error('TestingController Error', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create sample attendance',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

                // Create logs dengan struktur att_log
                AttendanceLog::create([
                    'sn' => 'TESTING_DEVICE_' . str_pad($karyawan->karyawan_id, 3, '0', STR_PAD_LEFT),
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_date' => $checkIn,
                    'verifymode' => 1,
                    'inoutmode' => 1,
                    'device_ip' => '127.0.0.1',
                    'is_processed' => false
                ]);

                AttendanceLog::create([
                    'sn' => 'TESTING_DEVICE_' . str_pad($karyawan->karyawan_id, 3, '0', STR_PAD_LEFT),
                    'pin' => $karyawan->pin_fingerprint,
                    'scan_date' => $checkOut,
                    'verifymode' => 1,
                    'inoutmode' => 2,
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

            // Delete testing attendance logs (dengan prefix TESTING_DEVICE)
            $deletedLogs = AttendanceLog::where('sn', 'LIKE', 'TESTING_DEVICE%')->delete();
            
            // Delete related absensi records untuk testing PINs
            $testPins = Karyawan::whereNotNull('pin_fingerprint')
                ->pluck('pin_fingerprint')
                ->toArray();
            
            $deletedAbsensi = 0;
            if (!empty($testPins)) {
                // Get karyawan IDs dari test PINs
                $testKaryawanIds = Karyawan::whereIn('pin_fingerprint', $testPins)
                    ->pluck('karyawan_id')
                    ->toArray();
                
                if (!empty($testKaryawanIds)) {
                    // Only delete absensi yang mungkin dari testing
                    $deletedAbsensi = Absensi::whereIn('karyawan_id', $testKaryawanIds)
                        ->whereDate('created_at', '>=', Carbon::today()->subDays(7)) // Only recent data
                        ->delete();
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
     * Get attendance summary untuk testing
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

        // Get att_log statistics
        $attLogStats = [
            'total_logs' => AttendanceLog::count(),
            'processed_logs' => AttendanceLog::where('is_processed', true)->count(),
            'unprocessed_logs' => AttendanceLog::where('is_processed', false)->count(),
            'testing_logs' => AttendanceLog::where('sn', 'LIKE', 'TESTING_DEVICE%')->count(),
            'devices' => AttendanceLog::distinct('sn')->pluck('sn')
        ];

        return response()->json([
            'periode' => $periode,
            'summary' => $summary,
            'att_log_stats' => $attLogStats,
            'active_setting' => $setting ? [
                'premi_produksi' => $setting->premi_produksi,
                'premi_staff' => $setting->premi_staff,
                'tarif_lembur_produksi' => $setting->tarif_lembur_produksi_per_jam,
                'tarif_lembur_staff' => $setting->tarif_lembur_staff_per_jam
            ] : null
        ]);
    }

    /**
     * Test calculation untuk specific scenario
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

            // Get active setting untuk reference
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
     * Generate sample data untuk semua karyawan
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
                // Generate PIN jika belum ada
                if (!$karyawan->pin_fingerprint) {
                    $pin = str_pad($karyawan->karyawan_id, 5, '0', STR_PAD_LEFT);
                    $karyawan->update(['pin_fingerprint' => $pin]);
                }

                // Generate attendance untuk periode tersebut
                $year = substr($request->periode, 0, 4);
                $month = substr($request->periode, 5, 2);
                $startDate = Carbon::create($year, $month, 1);
                $endDate = $startDate->copy()->endOfMonth();

                $generated = 0;
                $current = $startDate->copy();

                while ($current <= $endDate) {
                    $jenisHari = HariLibur::getJenisHari($current);
                    
                    // Different patterns untuk different roles
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

                    // Create logs dengan struktur att_log
                    AttendanceLog::create([
                        'sn' => 'BULK_TEST_DEVICE',
                        'pin' => $karyawan->pin_fingerprint,
                        'scan_date' => $checkIn,
                        'verifymode' => 1,
                        'inoutmode' => 1,
                        'device_ip' => '127.0.0.1',
                        'is_processed' => false
                    ]);

                    AttendanceLog::create([
                        'sn' => 'BULK_TEST_DEVICE',
                        'pin' => $karyawan->pin_fingerprint,
                        'scan_date' => $checkOut,
                        'verifymode' => 1,
                        'inoutmode' => 2,
                        'device_ip' => '127.0.0.1',
                        'is_processed' => false
                    ]);

                    $generated++;
                    $current->addDay();
                }

                $results[] = [
                    'karyawan' => $karyawan->nama_lengkap,
                    'pin' => $karyawan->pin_fingerprint,
                    'days_generated' => $generated
                ];
            }

            // Process all logs
            $processResult = $this->fingerprintService->processUnprocessedLogs();

            DB::commit();

            return response()->json([
                'message' => 'Sample data generated untuk semua karyawan',
                'periode' => $request->periode,
                'karyawan_count' => $karyawanList->count(),
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

    /**
     * Simulate multiple devices
     */
    public function simulateMultipleDevices(Request $request): JsonResponse
    {
        $request->validate([
            'devices' => 'required|array|min:1|max:5',
            'devices.*.sn' => 'required|string',
            'devices.*.ip' => 'required|ip',
            'start_date' => 'required|date_format:Y-m-d',
            'days' => 'required|integer|min:1|max:31'
        ]);

        try {
            DB::beginTransaction();

            $totalGenerated = 0;
            $deviceResults = [];

            foreach ($request->devices as $deviceConfig) {
                $deviceGenerated = 0;
                $startDate = Carbon::parse($request->start_date);

                // Get random karyawan untuk device ini
                $karyawanList = Karyawan::where('status', 'Aktif')
                    ->whereNotNull('pin_fingerprint')
                    ->inRandomOrder()
                    ->take(rand(3, 8)) // 3-8 karyawan per device
                    ->get();

                foreach ($karyawanList as $karyawan) {
                    for ($i = 0; $i < $request->days; $i++) {
                        $date = $startDate->copy()->addDays($i);
                        
                        // Skip weekend randomly
                        if ($date->isWeekend() && rand(1, 10) > 4) continue;
                        
                        // Random absence
                        if (rand(1, 20) === 1) continue;

                        // Check in
                        $checkIn = $date->copy()->setTime(8, rand(0, 45));
                        AttendanceLog::create([
                            'sn' => $deviceConfig['sn'],
                            'pin' => $karyawan->pin_fingerprint,
                            'scan_date' => $checkIn,
                            'verifymode' => rand(1, 2), // Mix fingerprint and password
                            'inoutmode' => 1,
                            'device_ip' => $deviceConfig['ip'],
                            'is_processed' => false
                        ]);

                        // Check out
                        $checkOut = $date->copy()->setTime(17, rand(0, 240)); // Up to 4 hours overtime
                        AttendanceLog::create([
                            'sn' => $deviceConfig['sn'],
                            'pin' => $karyawan->pin_fingerprint,
                            'scan_date' => $checkOut,
                            'verifymode' => rand(1, 2),
                            'inoutmode' => 2,
                            'device_ip' => $deviceConfig['ip'],
                            'is_processed' => false
                        ]);

                        $deviceGenerated++;
                    }
                }

                $deviceResults[] = [
                    'sn' => $deviceConfig['sn'],
                    'ip' => $deviceConfig['ip'],
                    'karyawan_count' => $karyawanList->count(),
                    'days_generated' => $deviceGenerated,
                    'total_logs' => $deviceGenerated * 2
                ];

                $totalGenerated += $deviceGenerated * 2;
            }

            DB::commit();

            return response()->json([
                'message' => 'Multiple device simulation completed',
                'devices_simulated' => count($request->devices),
                'total_logs_generated' => $totalGenerated,
                'device_results' => $deviceResults,
                'note' => 'Use /fingerprint/process-logs to process these logs into attendance records'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to simulate multiple devices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed att_log analysis
     */
    public function getAttLogAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'sn' => 'nullable|string'
        ]);

        try {
            $query = AttendanceLog::query();

            if ($request->start_date) {
                $query->whereDate('scan_date', '>=', $request->start_date);
            }

            if ($request->end_date) {
                $query->whereDate('scan_date', '<=', $request->end_date);
            }

            if ($request->sn) {
                $query->where('sn', $request->sn);
            }

            // Overall statistics
            $totalLogs = $query->count();
            $processedLogs = (clone $query)->where('is_processed', true)->count();
            $unprocessedLogs = (clone $query)->where('is_processed', false)->count();

            // Device statistics
            $deviceStats = (clone $query)
                ->selectRaw('
                    sn,
                    device_ip,
                    COUNT(*) as total_logs,
                    COUNT(CASE WHEN is_processed = 1 THEN 1 END) as processed,
                    COUNT(CASE WHEN is_processed = 0 THEN 1 END) as unprocessed,
                    COUNT(CASE WHEN inoutmode = 1 THEN 1 END) as check_ins,
                    COUNT(CASE WHEN inoutmode = 2 THEN 1 END) as check_outs,
                    MIN(scan_date) as first_scan,
                    MAX(scan_date) as last_scan
                ')
                ->groupBy('sn', 'device_ip')
                ->get();

            // Employee statistics
            $employeeStats = (clone $query)
                ->selectRaw('
                    pin,
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN inoutmode = 1 THEN 1 END) as check_ins,
                    COUNT(CASE WHEN inoutmode = 2 THEN 1 END) as check_outs,
                    COUNT(CASE WHEN is_processed = 1 THEN 1 END) as processed_scans
                ')
                ->groupBy('pin')
                ->with(['karyawan:karyawan_id,pin_fingerprint,nama_lengkap,role_karyawan'])
                ->orderBy('total_scans', 'desc')
                ->limit(20)
                ->get();

            // Verification mode statistics
            $verifyModeStats = (clone $query)
                ->selectRaw('
                    verifymode,
                    COUNT(*) as count,
                    CASE 
                        WHEN verifymode = 1 THEN "Fingerprint"
                        WHEN verifymode = 2 THEN "Password/PIN"
                        WHEN verifymode = 3 THEN "Card"
                        WHEN verifymode = 4 THEN "Face"
                        WHEN verifymode = 5 THEN "Palm Vein"
                        ELSE "Unknown"
                    END as method_name
                ')
                ->groupBy('verifymode')
                ->get();

            // Daily activity pattern
            $dailyPattern = (clone $query)
                ->selectRaw('
                    DATE(scan_date) as date,
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN inoutmode = 1 THEN 1 END) as check_ins,
                    COUNT(CASE WHEN inoutmode = 2 THEN 1 END) as check_outs
                ')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();

            // Hourly pattern
            $hourlyPattern = (clone $query)
                ->selectRaw('
                    HOUR(scan_date) as hour,
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN inoutmode = 1 THEN 1 END) as check_ins,
                    COUNT(CASE WHEN inoutmode = 2 THEN 1 END) as check_outs
                ')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            return response()->json([
                'period' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'device_filter' => $request->sn
                ],
                'overview' => [
                    'total_logs' => $totalLogs,
                    'processed_logs' => $processedLogs,
                    'unprocessed_logs' => $unprocessedLogs,
                    'processing_rate' => $totalLogs > 0 ? round(($processedLogs / $totalLogs) * 100, 2) : 0
                ],
                'device_statistics' => $deviceStats,
                'employee_statistics' => $employeeStats,
                'verification_methods' => $verifyModeStats,
                'daily_activity' => $dailyPattern,
                'hourly_pattern' => $hourlyPattern
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate att_log analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate data integrity
     */
    public function validateDataIntegrity(): JsonResponse
    {
        try {
            $issues = [];

            // Check for orphaned attendance logs (PIN tidak ada di karyawan)
            $orphanedLogs = AttendanceLog::whereNotIn('pin', function($query) {
                $query->select('pin_fingerprint')
                      ->from('karyawan')
                      ->whereNotNull('pin_fingerprint');
            })->count();

            if ($orphanedLogs > 0) {
                $issues[] = [
                    'type' => 'orphaned_logs',
                    'count' => $orphanedLogs,
                    'description' => 'Attendance logs with PINs not found in karyawan table'
                ];
            }

            // Check for karyawan without PIN
            $karyawanWithoutPin = Karyawan::where('status', 'Aktif')
                ->whereNull('pin_fingerprint')
                ->count();

            if ($karyawanWithoutPin > 0) {
                $issues[] = [
                    'type' => 'missing_pins',
                    'count' => $karyawanWithoutPin,
                    'description' => 'Active karyawan without PIN fingerprint'
                ];
            }

            // Check for unmatched check-ins/check-outs
            $unmatchedCheckIns = AttendanceLog::where('inoutmode', 1)
                ->where('is_processed', false)
                ->whereNotExists(function($query) {
                    $query->select('*')
                          ->from('att_log as checkout')
                          ->whereColumn('checkout.pin', 'att_log.pin')
                          ->whereColumn('checkout.sn', 'att_log.sn')
                          ->whereRaw('DATE(checkout.scan_date) = DATE(att_log.scan_date)')
                          ->where('checkout.inoutmode', 2);
                })
                ->count();

            if ($unmatchedCheckIns > 0) {
                $issues[] = [
                    'type' => 'unmatched_checkins',
                    'count' => $unmatchedCheckIns,
                    'description' => 'Check-in logs without corresponding check-out'
                ];
            }

            // Check for duplicate logs
            $duplicateLogs = AttendanceLog::selectRaw('sn, pin, scan_date, COUNT(*) as count')
                ->groupBy('sn', 'pin', 'scan_date')
                ->having('count', '>', 1)
                ->count();

            if ($duplicateLogs > 0) {
                $issues[] = [
                    'type' => 'duplicate_logs',
                    'count' => $duplicateLogs,
                    'description' => 'Duplicate attendance logs (same sn, pin, scan_date)'
                ];
            }

            // Check for absensi without corresponding att_log
            $absensiWithoutLogs = Absensi::whereNotExists(function($query) {
                $query->select('*')
                      ->from('att_log')
                      ->join('karyawan', 'att_log.pin', '=', 'karyawan.pin_fingerprint')
                      ->whereColumn('karyawan.karyawan_id', 'absensi.karyawan_id')
                      ->whereRaw('DATE(att_log.scan_date) = absensi.tanggal_absensi');
            })->count();

            if ($absensiWithoutLogs > 0) {
                $issues[] = [
                    'type' => 'absensi_without_logs',
                    'count' => $absensiWithoutLogs,
                    'description' => 'Absensi records without corresponding att_log entries'
                ];
            }

            return response()->json([
                'message' => 'Data integrity validation completed',
                'total_issues' => count($issues),
                'issues' => $issues,
                'recommendations' => [
                    'Run cleanup commands to fix orphaned data',
                    'Assign PIN fingerprint to active karyawan',
                    'Process unprocessed logs to create absensi records',
                    'Remove duplicate logs if necessary'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to validate data integrity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}