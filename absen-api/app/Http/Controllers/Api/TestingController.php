<?php
// app/Http/Controllers/Api/TestingController.php (NEW)

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Karyawan;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Services\FingerprintImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
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
            $karyawan = Karyawan::find($request->karyawan_id);
            
            if (!$karyawan->pin_fingerprint) {
                // Generate PIN jika belum ada
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

            return response()->json([
                'message' => 'Sample attendance created successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint']),
                'logs' => $logs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create sample attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate data absensi untuk satu bulan penuh
     */
    public function generateMonthData($karyawan_id, $periode): JsonResponse
    {
        try {
            $karyawan = Karyawan::findOrFail($karyawan_id);
            
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
                // Skip weekend kecuali untuk role tertentu
                if ($current->isWeekend() && rand(1, 10) > 3) {
                    $current->addDay();
                    continue;
                }

                // Random chance untuk tidak masuk (untuk testing alpha)
                if (rand(1, 10) > 8) {
                    $current->addDay();
                    continue;
                }

                // Generate jam masuk (08:00 ± 30 menit)
                $checkInHour = 8;
                $checkInMinute = rand(-30, 30);
                $checkIn = $current->copy()->setTime($checkInHour, max(0, min(59, $checkInMinute)));

                // Generate jam pulang (17:00 ± 2 jam untuk lembur)
                $checkOutHour = 17 + rand(0, 3); // 17:00 - 20:00
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

            return response()->json([
                'message' => 'Month data generated successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint']),
                'periode' => $periode,
                'days_generated' => $generated,
                'total_logs' => $generated * 2
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate month data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process all testing data
     */
    public function processTestingData(): JsonResponse
    {
        try {
            $importService = new FingerprintImportService();
            $result = $importService->processUnprocessedLogs();

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
            
            // Optionally clear absensi that was created from testing
            $deletedAbsensi = Absensi::whereHas('karyawan', function($query) {
                $query->where('pin_fingerprint', 'LIKE', '0000%');
            })->delete();

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

        return response()->json([
            'periode' => $periode,
            'summary' => $summary
        ]);
    }
}