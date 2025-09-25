<?php
// routes/api.php (COMPLETE & FIXED VERSION)

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

// Import Controllers
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

// Import Models
use App\Models\Karyawan;
use App\Models\Absensi;
use App\Models\AttendanceLog;

// Import Services
use App\Services\PayrollService;
use App\Services\FingerprintImportService;

// ========================================
// PUBLIC ENDPOINTS
// ========================================

Route::post('/login', [AuthController::class, 'login']);

Route::post('/webhook/fingerspot', function(Request $request) {
    Log::info('Webhook Fingerspot Diterima:', $request->all());
    
    try {
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

// ========================================
// AUTHENTICATED ENDPOINTS
// ========================================

Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['departemenSaatIni', 'jabatanSaatIni']);
    });

    // ========================================
    // EMPLOYEE MANAGEMENT
    // ========================================
    
    Route::prefix('karyawan')->group(function () {
        Route::get('/', [KaryawanController::class, 'index'])->middleware('can:view-any-karyawan');
        Route::post('/', [KaryawanController::class, 'store'])->middleware('can:create-karyawan');
        Route::get('/{karyawan}', [KaryawanController::class, 'show'])->middleware('can:view-karyawan,karyawan');
        Route::put('/{karyawan}', [KaryawanController::class, 'update'])->middleware('can:update-karyawan,karyawan');
        Route::delete('/{karyawan}', [KaryawanController::class, 'destroy'])->middleware('can:delete-karyawan,karyawan');
    });
    
    // ========================================
    // MASTER DATA
    // ========================================
    
    Route::apiResource('departemen', DepartemenController::class)->middleware('can:manage-master-data');
    Route::apiResource('jabatan', JabatanController::class)->middleware('can:manage-master-data');
    Route::apiResource('shift', ShiftController::class)->middleware('can:manage-master-data');
    
    Route::prefix('jadwal-shift')->group(function () {
        Route::get('/{departemen_id}/{tahun}/{bulan}', [JadwalShiftController::class, 'getJadwalByDepartemen']);
        Route::post('/', [JadwalShiftController::class, 'updateJadwal']);
    });

    // ========================================
    // PAYROLL MANAGEMENT
    // ========================================
    
    Route::prefix('payroll')->group(function () {
        Route::post('/generate', [PayrollController::class, 'generate'])->middleware('can:process-payroll');
        Route::get('/history/{karyawan_id}', [PayrollController::class, 'getHistory'])->middleware('can:view-any-slip');
        Route::get('/slip/{gaji_id}', [PayrollController::class, 'getSlipGaji']);
        Route::post('/bulk-generate', [PayrollController::class, 'bulkGenerate'])->middleware('can:process-payroll');
        Route::get('/all', [PayrollController::class, 'getAllPayrolls'])->middleware('can:view-any-slip');
        
        // Generate batch untuk harian/mingguan
        Route::post('/generate-batch', function(Request $request) {
            $payrollService = app(PayrollService::class);
            
            $request->validate([
                'tipe_periode' => 'required|in:harian,mingguan',
                'tanggal' => 'required|date'
            ]);
            
            $results = [];
            $errors = [];
            
            if ($request->tipe_periode === 'harian') {
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
                        $errors[] = [
                            'karyawan' => $k->nama_lengkap, 
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
            } elseif ($request->tipe_periode === 'mingguan') {
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
                        $errors[] = [
                            'karyawan' => $k->nama_lengkap, 
                            'error' => $e->getMessage()
                        ];
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

    // ========================================
    // FINGERPRINT & ATTENDANCE
    // ========================================
    
        // Route::prefix('fingerprint')->group(function () {
        // Route::post('/import-logs', [FingerspotIntegrationController::class, 'importAttendance']);
        // Route::get('/device-status', [FingerspotIntegrationController::class, 'getDeviceStatus']);
        // Route::get('/userinfo', [FingerspotIntegrationController::class, 'getUserInfo']);
        // Route::post('/process-logs', [TestingController::class, 'processTestingData']);
        // Route::post('/generate-dummy-data', [TestingController::class, 'createSampleAttendance']);
        // Route::get('/summary/{periode}', [TestingController::class, 'getAttendanceSummary']);
        
        // Route::post('/set-pin', function(Request $request) {
        //     $request->validate([
        //         'karyawan_id' => 'required|exists:karyawan,karyawan_id',
        //         'pin_fingerprint' => 'required|string|max:32|unique:karyawan,pin_fingerprint'
        //     ]);
            
        //     $karyawan = Karyawan::find($request->karyawan_id);
        //     $karyawan->update(['pin_fingerprint' => $request->pin_fingerprint]);
            
        //     return response()->json([
        //         'message' => 'PIN fingerprint set successfully',
        //         'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint'])
        //     ]);
        // });
        
        Route::get('/logs', function(Request $request) {
            $query = AttendanceLog::with('karyawan');
            
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
        
        });

    // ========================================
    // FINGERSPOT DEVICE INTEGRATION
    // ========================================
    
    Route::prefix('fingerprint')->group(function () {
        Route::get('/status', [FingerspotIntegrationController::class, 'getDeviceStatus']);
        Route::get('/userinfo', [FingerspotIntegrationController::class, 'getUserInfo']);
        Route::post('/register-user', [FingerspotIntegrationController::class, 'registerUser']);
        Route::delete('/remove-user', [FingerspotIntegrationController::class, 'removeUser']);
        Route::post('/sync-all-users', [FingerspotIntegrationController::class, 'syncAllUsers']);
        Route::post('/import-attendance', [FingerspotIntegrationController::class, 'importAttendance']);
        Route::post('/set-time', [FingerspotIntegrationController::class, 'setDeviceTime']);
        Route::post('/restart', [FingerspotIntegrationController::class, 'restartDevice']);

         Route::get('/logs', function (Request $request) {
        $query = AttendanceLog::with('karyawan');

        if ($request->start_date) {
            $query->whereDate('scan_time', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('scan_time', '<=', $request->end_date);
        }

        if ($request->pin) {
            $query->where('pin', $request->pin);
        }

        if ($request->sn) {
            $query->where('device_sn', $request->sn);
        }

        if ($request->has('is_processed')) {
            $query->where('is_processed', (int) $request->is_processed);
        }

        return response()->json(
            $query->orderBy('scan_time', 'desc')->paginate(20)
        );
    });
    });

    // ========================================
    // SALARY SETTINGS
    // ========================================
    
    Route::prefix('setting-gaji')->group(function () {
        Route::get('/', [SettingGajiController::class, 'index']);
        Route::post('/', [SettingGajiController::class, 'store']);
        Route::get('/active', [SettingGajiController::class, 'getActiveSetting']);
        Route::get('/{id}', [SettingGajiController::class, 'show']);
        Route::put('/{id}', [SettingGajiController::class, 'update']);
        Route::post('/{id}/activate', [SettingGajiController::class, 'activate']);
    });

    Route::prefix('gaji-tambahan')->group(function () {
        Route::post('/calculate', [GajiTambahanController::class, 'calculate']);
        Route::get('/periode/{karyawan_id}/{periode}', [GajiTambahanController::class, 'getPeriode']);
        Route::post('/recalculate-all', [GajiTambahanController::class, 'recalculateAll']);
        Route::get('/summary', [GajiTambahanController::class, 'getSummaryByDepartemen']);
    });

    // ========================================
    // ATTENDANCE MANAGEMENT
    // ========================================
    
    Route::prefix('absensi')->group(function () {
    Route::get('/', function(Request $request) {
        // Relasi karyawan + departemen
        $query = Absensi::with(['karyawan.departemenSaatIni']);

        // Filter rentang tanggal (tetap dukung start_date & end_date dari sebelumnya)
        if ($request->start_date) {
            $query->whereDate('tanggal_absensi', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('tanggal_absensi', '<=', $request->end_date);
        }

        // Filter single tanggal (front‑end mengirim 'tanggal_absensi')
        if ($request->tanggal_absensi) {
            $query->whereDate('tanggal_absensi', $request->tanggal_absensi);
        }

        // Filter berdasarkan departemen (nama atau ID)
        if ($request->departemen) {
            // Jika front‑end mengirim nama departemen:
            $query->whereHas('karyawan.departemenSaatIni', function($q) use ($request) {
                $q->where('nama_departemen', $request->departemen);
            });
            // Atau jika ingin dukung ID: $q->where('departemen_id', $request->departemen_id);
        }

        // Filter berdasarkan status absensi
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Pencarian nama atau NIK
        if ($request->q) {
            $q = strtolower($request->q);
            $query->whereHas('karyawan', function($k) use ($q) {
                $k->whereRaw('LOWER(nama_lengkap) LIKE ?', ["%{$q}%"])
                  ->orWhereRaw('LOWER(nik) LIKE ?', ["%{$q}%"]);
            });
        }

        // Kembalikan tanpa paginate untuk kemudahan front‑end
        return response()->json(
            $query->orderBy('tanggal_absensi', 'desc')->get()
        );
    });
        
        Route::get('/{absensi_id}', function($absensi_id) {
            $absensi = Absensi::with(['karyawan.departemenSaatIni', 'karyawan.jabatanSaatIni'])
                ->findOrFail($absensi_id);
            return response()->json($absensi);
        });
        
        Route::put('/{absensi_id}', function(Request $request, $absensi_id) {
            $absensi = Absensi::findOrFail($absensi_id);
            
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
        
        Route::get('/summary/{periode}', function($periode) {
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);
            
            $summary = Absensi::selectRaw('
                    karyawan.nama_lengkap,
                    karyawan.role_karyawan,
                    departemen.nama_departemen,
                    COUNT(*) as total_hari,
                    SUM(CASE WHEN status = "Hadir" THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = "Terlambat" THEN 1 ELSE 0 END) as terlambat,
                    SUM(CASE WHEN status = "Alpha" THEN 1 ELSE 0 END) as alpha,
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
    // TESTING ENDPOINTS
    // ========================================
    
    Route::prefix('testing')->group(function () {
        Route::post('/create-sample-attendance', [TestingController::class, 'createSampleAttendance']);
        Route::post('/generate-month-data/{karyawan_id}/{periode}', [TestingController::class, 'generateMonthData']);
        Route::post('/generate-sample-data-for-all', [TestingController::class, 'generateSampleDataForAll']);
        Route::post('/test-calculation', [TestingController::class, 'testCalculation']);
        Route::get('/attendance-summary/{periode}', [TestingController::class, 'getAttendanceSummary']);
        Route::delete('/clear-test-data', [TestingController::class, 'clearTestData']);
    });

    // ========================================
    // REPORTS & ANALYTICS
    // ========================================
    
    Route::prefix('reports')->group(function () {
        Route::get('/daily/{date}', function($date) {
            $absensi = Absensi::with(['karyawan.departemenSaatIni'])
                ->whereDate('tanggal_absensi', $date)
                ->orderBy('jam_scan_masuk')
                ->get();
                
            $summary = [
                'total_karyawan' => Karyawan::where('status', 'Aktif')->count(),
                'hadir' => $absensi->where('status', 'Hadir')->count(),
                'terlambat' => $absensi->where('status', 'Terlambat')->count(),
                'alpha' => $absensi->where('status', 'Alpha')->count(),
                'total_jam_lembur' => $absensi->sum('jam_lembur'),
                'attendance_percentage' => round(
                    ($absensi->whereIn('status', ['Hadir', 'Terlambat'])->count() / 
                    max(1, Karyawan::where('status', 'Aktif')->count())) * 100, 2
                )
            ];
            
            return response()->json([
                'date' => $date,
                'summary' => $summary,
                'details' => $absensi
            ]);
        });
    });

    // ========================================
    // SYSTEM MONITORING
    // ========================================
    
    Route::prefix('system')->group(function () {
        Route::get('/health', function() {
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now(),
                'database' => [
                    'att_log_count' => AttendanceLog::count(),
                    'absensi_count' => Absensi::count(),
                    'karyawan_count' => Karyawan::where('status', 'Aktif')->count()
                ]
            ]);
        });
    });
