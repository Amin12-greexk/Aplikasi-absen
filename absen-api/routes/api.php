<?php
// routes/api.php (CORRECTED - Using existing controllers)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KaryawanController;
use App\Http\Controllers\Api\DepartemenController;
use App\Http\Controllers\Api\JabatanController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\JadwalShiftController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\SettingGajiController;
use App\Http\Controllers\Api\GajiTambahanController;
use App\Http\Controllers\Api\FingerspotIntegrationController;
use App\Http\Controllers\Api\TestingController;
use App\Models\Karyawan;

// Endpoint publik
Route::post('/login', [AuthController::class, 'login']);

// Webhook untuk menerima data dari mesin fingerprint
Route::post('/webhook/fingerspot', function(Request $request) {
    Log::info('Webhook Fingerspot Diterima:', $request->all());
    
    try {
        $attendanceData = $request->all();
        
        // Process webhook data if needed
        // Create AttendanceLog records directly from webhook
        
        return response()->json([
            'message' => 'Webhook received and processed',
            'status' => 'success'
        ]);
    } catch (\Exception $e) {
        Log::error('Webhook processing error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Webhook received but processing failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Grup Endpoint yang memerlukan Autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Informasi user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['departemenSaatIni', 'jabatanSaatIni']);
    });

    // ========================================
    // EXISTING ROUTES (KEEP AS IS)
    // ========================================
    
    // Karyawan (dengan permission)
    Route::get('/karyawan', [KaryawanController::class, 'index'])->middleware('can:view-any-karyawan');
    Route::post('/karyawan', [KaryawanController::class, 'store'])->middleware('can:create-karyawan');
    Route::get('/karyawan/{karyawan}', [KaryawanController::class, 'show'])->middleware('can:view-karyawan,karyawan');
    Route::put('/karyawan/{karyawan}', [KaryawanController::class, 'update'])->middleware('can:update-karyawan,karyawan');
    Route::delete('/karyawan/{karyawan}', [KaryawanController::class, 'destroy'])->middleware('can:delete-karyawan,karyawan');
    
    // Master Data (Departemen, Jabatan, Shift)
    Route::apiResource('departemen', DepartemenController::class)->middleware('can:manage-master-data');
    Route::apiResource('jabatan', JabatanController::class)->middleware('can:manage-master-data');
    Route::apiResource('shift', ShiftController::class)->middleware('can:manage-master-data');
    
    // Jadwal Shift
    Route::get('jadwal-shift/{departemen_id}/{tahun}/{bulan}', [JadwalShiftController::class, 'getJadwalByDepartemen']);
    Route::post('jadwal-shift', [JadwalShiftController::class, 'updateJadwal']);

    // Penggajian
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->middleware('can:process-payroll');
    Route::get('payroll/history/{karyawan_id}', [PayrollController::class, 'getHistory'])->middleware('can:view-any-slip');
    Route::get('payroll/slip/{gaji_id}', [PayrollController::class, 'getSlipGaji']);
    Route::post('payroll/bulk-generate', [PayrollController::class, 'bulkGenerate'])->middleware('can:process-payroll');
    Route::get('payroll/all', [PayrollController::class, 'getAllPayrolls'])->middleware('can:view-any-slip');

    // ========================================
    // FINGERPRINT/ATTENDANCE ROUTES (USING EXISTING CONTROLLERS)
    // ========================================
    
    Route::prefix('fingerprint')->group(function () {
        // Using FingerspotIntegrationController methods
        Route::post('import-logs', [FingerspotIntegrationController::class, 'importAttendance']);
        Route::get('device-status', [FingerspotIntegrationController::class, 'getDeviceStatus']);
        Route::get('userinfo', [FingerspotIntegrationController::class, 'getUserInfo']);
        
        // Using TestingController for processing and development
        Route::post('process-logs', [TestingController::class, 'processTestingData']);
        Route::post('generate-dummy-data', [TestingController::class, 'createSampleAttendance']);
        Route::get('summary', [TestingController::class, 'getAttendanceSummary']);
        
        // Direct implementations for specific functionality
        Route::post('set-pin', function(Request $request) {
            $request->validate([
                'karyawan_id' => 'required|exists:karyawan,karyawan_id',
                'pin_fingerprint' => 'required|string|max:32|unique:karyawan,pin_fingerprint'
            ]);
            
            $karyawan = \App\Models\Karyawan::find($request->karyawan_id);
            $karyawan->update(['pin_fingerprint' => $request->pin_fingerprint]);
            
            return response()->json([
                'message' => 'PIN fingerprint set successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint'])
            ]);
        });
        
        Route::get('logs', function(Request $request) {
            $query = \App\Models\AttendanceLog::with('karyawan');
            
            if ($request->start_date) {
                $query->whereDate('scan_date', '>=', $request->start_date);
            }
            
            if ($request->end_date) {
                $query->whereDate('scan_date', '<=', $request->end_date);
            }
            
            if ($request->pin) {
                $query->where('pin', $request->pin);
            }
            
            if ($request->has('is_processed')) {
                $query->where('is_processed', $request->is_processed);
            }
            
            return response()->json($query->orderBy('scan_date', 'desc')->paginate(20));
        });
        
        // Cleanup operations
        Route::delete('cleanup-old-logs', function(Request $request) {
            $days = $request->get('days', 30);
            $importService = new \App\Services\FingerprintImportService();
            $deleted = $importService->cleanupOldLogs($days);
            
            return response()->json([
                'message' => 'Old logs cleaned up',
                'deleted_count' => $deleted
            ]);
        });
    });

    // Device integration (for actual hardware communication)
    Route::prefix('fingerspot')->group(function () {
        // All using FingerspotIntegrationController
        Route::get('status', [FingerspotIntegrationController::class, 'getDeviceStatus']);
        Route::get('userinfo', [FingerspotIntegrationController::class, 'getUserInfo']);
        Route::post('register-user', [FingerspotIntegrationController::class, 'registerUser']);
        Route::delete('remove-user', [FingerspotIntegrationController::class, 'removeUser']);
        Route::post('sync-all-users', [FingerspotIntegrationController::class, 'syncAllUsers']);
        Route::post('import-attendance', [FingerspotIntegrationController::class, 'importAttendance']);
        Route::post('set-time', [FingerspotIntegrationController::class, 'setDeviceTime']);
        Route::post('restart', [FingerspotIntegrationController::class, 'restartDevice']);
        
        // Mock mode configuration (inline implementation)
        Route::post('enable-mock-mode', function() {
            config(['services.fingerspot.mock_mode' => true]);
            return response()->json(['message' => 'Mock mode enabled']);
        });
        
        Route::post('disable-mock-mode', function() {
            config(['services.fingerspot.mock_mode' => false]);
            return response()->json(['message' => 'Mock mode disabled']);
        });
    });

    // Setting gaji management
    Route::prefix('setting-gaji')->group(function () {
        Route::get('/', [SettingGajiController::class, 'index']);
        Route::post('/', [SettingGajiController::class, 'store']);
        Route::get('/active', [SettingGajiController::class, 'getActiveSetting']);
        Route::get('/{id}', [SettingGajiController::class, 'show']);
        Route::put('/{id}', [SettingGajiController::class, 'update']);
        Route::post('/{id}/activate', [SettingGajiController::class, 'activate']);
    });

    // Gaji tambahan calculation
    Route::prefix('gaji-tambahan')->group(function () {
        Route::post('calculate', [GajiTambahanController::class, 'calculate']);
        Route::get('periode/{karyawan_id}/{periode}', [GajiTambahanController::class, 'getPeriode']);
        Route::post('recalculate-all', [GajiTambahanController::class, 'recalculateAll']);
        Route::get('summary', [GajiTambahanController::class, 'getSummaryByDepartemen']);
    });

    // ========================================
    // ABSENSI/ATTENDANCE MANAGEMENT
    // ========================================
    
    Route::prefix('absensi')->group(function () {
        // View attendance records
        Route::get('/', function(Request $request) {
            $query = \App\Models\Absensi::with(['karyawan.departemenSaatIni']);
            
            if ($request->karyawan_id) {
                $query->where('karyawan_id', $request->karyawan_id);
            }
            
            if ($request->start_date) {
                $query->whereDate('tanggal_absensi', '>=', $request->start_date);
            }
            
            if ($request->end_date) {
                $query->whereDate('tanggal_absensi', '<=', $request->end_date);
            }
            
            return response()->json($query->orderBy('tanggal_absensi', 'desc')->paginate(20));
        });
        
        // Get specific attendance record
        Route::get('/{absensi_id}', function($absensi_id) {
            $absensi = \App\Models\Absensi::with(['karyawan.departemenSaatIni', 'karyawan.jabatanSaatIni'])
                ->findOrFail($absensi_id);
            return response()->json($absensi);
        });
        
        // Update attendance record
        Route::put('/{absensi_id}', function(Request $request, $absensi_id) {
            $absensi = \App\Models\Absensi::findOrFail($absensi_id);
            
            $validatedData = $request->validate([
                'status' => 'sometimes|in:Hadir,Terlambat,Alpha,Izin,Sakit,Libur',
                'jam_scan_masuk' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
                'jam_scan_pulang' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
                'jam_lembur' => 'sometimes|nullable|numeric|min:0',
                'keterangan' => 'sometimes|nullable|string'
            ]);
            
            $absensi->update($validatedData);
            
            return response()->json([
                'message' => 'Attendance record updated',
                'absensi' => $absensi->fresh()
            ]);
        });
        
        // Monthly attendance summary
        Route::get('/summary/{periode}', function($periode) {
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);
            
            $summary = \App\Models\Absensi::selectRaw('
                    karyawan.nama_lengkap,
                    karyawan.role_karyawan,
                    departemen.nama_departemen,
                    COUNT(*) as total_hari,
                    SUM(CASE WHEN status = "Hadir" THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = "Terlambat" THEN 1 ELSE 0 END) as terlambat,
                    SUM(CASE WHEN status = "Alpha" THEN 1 ELSE 0 END) as alpha,
                    SUM(CASE WHEN status = "Izin" THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN status = "Sakit" THEN 1 ELSE 0 END) as sakit,
                    SUM(jam_lembur) as total_jam_lembur,
                    SUM(total_gaji_tambahan) as total_gaji_tambahan
                ')
                ->join('karyawan', 'absensi.karyawan_id', '=', 'karyawan.karyawan_id')
                ->join('departemen', 'karyawan.departemen_id_saat_ini', '=', 'departemen.departemen_id')
                ->whereYear('tanggal_absensi', $year)
                ->whereMonth('tanggal_absensi', $month)
                ->groupBy('karyawan.karyawan_id', 'karyawan.nama_lengkap', 'karyawan.role_karyawan', 'departemen.nama_departemen')
                ->get();
                
            return response()->json([
                'periode' => $periode,
                'summary' => $summary
            ]);
        });
    });

    // ========================================
    // TESTING ROUTES (USING TestingController)
    // ========================================
    
    Route::prefix('testing')->group(function () {
        // All using TestingController
        Route::post('create-sample-attendance', [TestingController::class, 'createSampleAttendance']);
        Route::post('generate-month-data/{karyawan_id}/{periode}', [TestingController::class, 'generateMonthData']);
        Route::post('generate-sample-data-for-all', [TestingController::class, 'generateSampleDataForAll']);
        Route::post('test-calculation', [TestingController::class, 'testCalculation']);
        Route::get('attendance-summary/{periode}', [TestingController::class, 'getAttendanceSummary']);
        Route::delete('clear-test-data', [TestingController::class, 'clearTestData']);
        
        // Additional testing endpoints for att_log structure
        Route::post('simulate-device-data', function(Request $request) {
            $request->validate([
                'device_sn' => 'required|string',
                'start_date' => 'required|date_format:Y-m-d',
                'days' => 'required|integer|min:1|max:31'
            ]);
            
            $karyawanList = \App\Models\Karyawan::where('status', 'Aktif')
                ->whereNotNull('pin_fingerprint')
                ->take(5)
                ->get();
                
            $generated = 0;
            $startDate = \Carbon\Carbon::parse($request->start_date);
            
            foreach ($karyawanList as $karyawan) {
                for ($i = 0; $i < $request->days; $i++) {
                    $date = $startDate->copy()->addDays($i);
                    
                    if ($date->isWeekend() && rand(1, 10) > 3) continue;
                    
                    // Check in
                    $checkIn = $date->copy()->setTime(8, rand(0, 30));
                    \App\Models\AttendanceLog::create([
                        'sn' => $request->device_sn,
                        'scan_date' => $checkIn,
                        'pin' => $karyawan->pin_fingerprint,
                        'verifymode' => 1,
                        'inoutmode' => 1,
                        'device_ip' => '192.168.1.100',
                        'is_processed' => false
                    ]);
                    
                    // Check out
                    $checkOut = $date->copy()->setTime(17, rand(0, 180));
                    \App\Models\AttendanceLog::create([
                        'sn' => $request->device_sn,
                        'scan_date' => $checkOut,
                        'pin' => $karyawan->pin_fingerprint,
                        'verifymode' => 1,
                        'inoutmode' => 2,
                        'device_ip' => '192.168.1.100',
                        'is_processed' => false
                    ]);
                    
                    $generated++;
                }
            }
            
            return response()->json([
                'message' => 'Device simulation data created',
                'device_sn' => $request->device_sn,
                'karyawan_count' => $karyawanList->count(),
                'days_generated' => $generated,
                'total_logs' => $generated * 2
            ]);
        });
        
        Route::get('att-log-stats', function() {
            $stats = [
                'total_logs' => \App\Models\AttendanceLog::count(),
                'processed_logs' => \App\Models\AttendanceLog::where('is_processed', true)->count(),
                'unprocessed_logs' => \App\Models\AttendanceLog::where('is_processed', false)->count(),
                'devices' => \App\Models\AttendanceLog::distinct('sn')->pluck('sn'),
                'date_range' => [
                    'earliest' => \App\Models\AttendanceLog::min('scan_date'),
                    'latest' => \App\Models\AttendanceLog::max('scan_date')
                ]
            ];
            
            return response()->json($stats);
        });
        
        Route::post('process-test-logs', function() {
            $importService = new \App\Services\FingerprintImportService();
            $result = $importService->processUnprocessedLogs();
            
            return response()->json([
                'message' => 'Test logs processed',
                'result' => $result
            ]);
        });
    });

    // ========================================
    // SYSTEM MONITORING & HEALTH CHECK
    // ========================================
    
    Route::prefix('system')->group(function () {
        Route::get('health', function() {
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now(),
                'database' => [
                    'att_log_count' => \App\Models\AttendanceLog::count(),
                    'absensi_count' => \App\Models\Absensi::count(),
                    'karyawan_count' => \App\Models\Karyawan::where('status', 'Aktif')->count()
                ],
                'services' => [
                    'fingerspot_mock_mode' => config('services.fingerspot.mock_mode', true),
                    'queue_connection' => config('queue.default'),
                    'cache_driver' => config('cache.default')
                ]
            ]);
        });
        
        Route::get('logs/recent', function(Request $request) {
            $lines = $request->get('lines', 50);
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return response()->json(['error' => 'Log file not found'], 404);
            }
            
            $logs = [];
            $file = new \SplFileObject($logFile);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();
            
            $startLine = max(0, $totalLines - $lines);
            $file->seek($startLine);
            
            while (!$file->eof()) {
                $line = trim($file->current());
                if (!empty($line)) {
                    $logs[] = $line;
                }
                $file->next();
            }
            
            return response()->json([
                'logs' => $logs,
                'total_lines' => $totalLines,
                'showing_lines' => count($logs)
            ]);
        });
    });

    // ========================================
    // REPORTS & ANALYTICS
    // ========================================
    
    Route::prefix('reports')->group(function () {
        // Daily attendance report
        Route::get('daily/{date}', function($date) {
            $absensi = \App\Models\Absensi::with(['karyawan.departemenSaatIni'])
                ->whereDate('tanggal_absensi', $date)
                ->orderBy('jam_scan_masuk')
                ->get();
                
            $summary = [
                'total_karyawan' => \App\Models\Karyawan::where('status', 'Aktif')->count(),
                'hadir' => $absensi->where('status', 'Hadir')->count(),
                'terlambat' => $absensi->where('status', 'Terlambat')->count(),
                'alpha' => $absensi->where('status', 'Alpha')->count(),
                'total_jam_lembur' => $absensi->sum('jam_lembur'),
                'attendance_percentage' => round(($absensi->whereIn('status', ['Hadir', 'Terlambat'])->count() / max(1, \App\Models\Karyawan::where('status', 'Aktif')->count())) * 100, 2)
            ];
            
            return response()->json([
                'date' => $date,
                'summary' => $summary,
                'details' => $absensi
            ]);
        });
        
        // Monthly attendance trends
        Route::get('monthly-trends/{year}', function($year) {
            $trends = \App\Models\Absensi::selectRaw('
                    MONTH(tanggal_absensi) as month,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = "Hadir" THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = "Terlambat" THEN 1 ELSE 0 END) as terlambat,
                    SUM(CASE WHEN status = "Alpha" THEN 1 ELSE 0 END) as alpha,
                    AVG(jam_lembur) as avg_overtime,
                    SUM(total_gaji_tambahan) as total_additional_salary
                ')
                ->whereYear('tanggal_absensi', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get();
                
            return response()->json([
                'year' => $year,
                'trends' => $trends
            ]);
        });
        
        // Department performance
        Route::get('department-performance/{periode}', function($periode) {
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);
            
            $performance = \App\Models\Absensi::selectRaw('
                    departemen.nama_departemen,
                    COUNT(*) as total_attendance,
                    SUM(CASE WHEN status IN ("Hadir", "Terlambat") THEN 1 ELSE 0 END) as present_count,
                    ROUND((SUM(CASE WHEN status IN ("Hadir", "Terlambat") THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate,
                    SUM(jam_lembur) as total_overtime,
                    AVG(jam_lembur) as avg_overtime_per_employee,
                    SUM(total_gaji_tambahan) as total_additional_compensation
                ')
                ->join('karyawan', 'absensi.karyawan_id', '=', 'karyawan.karyawan_id')
                ->join('departemen', 'karyawan.departemen_id_saat_ini', '=', 'departemen.departemen_id')
                ->whereYear('tanggal_absensi', $year)
                ->whereMonth('tanggal_absensi', $month)
                ->groupBy('departemen.departemen_id', 'departemen.nama_departemen')
                ->orderBy('attendance_rate', 'desc')
                ->get();
                
            return response()->json([
                'periode' => $periode,
                'department_performance' => $performance
            ]);
        });
        
    });
    Route::post('payroll/generate-weekly', function(Request $request) {
    $karyawanHarian = Karyawan::where('kategori_gaji', 'Harian')
        ->where('periode_gaji', 'mingguan')
        ->get();
    
    $weekStart = Carbon::now()->startOfWeek();
    $weekEnd = Carbon::now()->endOfWeek();
    
    foreach ($karyawanHarian as $k) {
        $payrollService->generateForDateRange(
            $k->karyawan_id, 
            $weekStart, 
            $weekEnd, 
            'mingguan'
        );
    }
});

// routes/api.php
Route::post('payroll/generate-batch', function(Request $request) {
    $payrollService = app(\App\Services\PayrollService::class);
    $request->validate([
        'tipe_periode' => 'required|in:harian,mingguan',
        'tanggal' => 'required|date'
    ]);
    
    $results = [];
    $errors = [];
    $payrollService = app(PayrollService::class);
    
    if ($request->tipe_periode === 'harian') {
        // Generate untuk hari tertentu
        $tanggal = Carbon::parse($request->tanggal);
        
        $karyawanHarian = Karyawan::where('kategori_gaji', 'Harian')
            ->where('periode_gaji', 'harian')
            ->where('status', 'Aktif')
            ->get();
        
        foreach ($karyawanHarian as $k) {
            try {
                $gaji = $payrollService->generateForDateRange(
                    $k->karyawan_id,
                    $tanggal->format('Y-m-d'),
                    $tanggal->format('Y-m-d'),
                    'harian'
                );
                $results[] = $gaji;
            } catch (\Exception $e) {
                $errors[] = ['karyawan' => $k->nama_lengkap, 'error' => $e->getMessage()];
            }
        }
        
    } elseif ($request->tipe_periode === 'mingguan') {
        // Generate untuk minggu tertentu
        $tanggal = Carbon::parse($request->tanggal);
        $weekStart = $tanggal->startOfWeek();
        $weekEnd = $tanggal->endOfWeek();
        
        $karyawanMingguan = Karyawan::where('kategori_gaji', 'Harian')
            ->where('periode_gaji', 'mingguan')
            ->where('status', 'Aktif')
            ->get();
        
        foreach ($karyawanMingguan as $k) {
            try {
                $gaji = $payrollService->generateForDateRange(
                    $k->karyawan_id,
                    $weekStart->format('Y-m-d'),
                    $weekEnd->format('Y-m-d'),
                    'mingguan'
                );
                $results[] = $gaji;
            } catch (\Exception $e) {
                $errors[] = ['karyawan' => $k->nama_lengkap, 'error' => $e->getMessage()];
            }
        }
    }
    
    return response()->json([
        'success_count' => count($results),
        'error_count' => count($errors),
        'results' => $results,
        'errors' => $errors
    ]);
});
});
