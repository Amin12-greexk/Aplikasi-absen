<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KaryawanController;
use App\Http\Controllers\Api\DepartemenController;
use App\Http\Controllers\Api\JabatanController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\JadwalShiftController;
use App\Http\Controllers\Api\PayrollController;
// MISSING IMPORTS - ADD THESE:
use App\Http\Controllers\Api\FingerprintController;
use App\Http\Controllers\Api\SettingGajiController;
use App\Http\Controllers\Api\GajiTambahanController;
use App\Http\Controllers\Api\FingerspotIntegrationController;
use App\Http\Controllers\Api\TestingController;

// Endpoint publik
Route::post('/login', [AuthController::class, 'login']);
Route::post('/webhook/fingerspot', function(Request $request) {
    Log::info('Webhook Fingerspot Diterima:', $request->all());
    return response()->json(['message' => 'Webhook received']);
});

// Grup Endpoint yang memerlukan Autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Informasi user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
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

    // Penggajian (EXISTING)
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->middleware('can:process-payroll');
    Route::get('payroll/history/{karyawan_id}', [PayrollController::class, 'getHistory'])->middleware('can:view-any-slip');
    Route::get('payroll/slip/{gaji_id}', [PayrollController::class, 'getSlipGaji']);

    // ========================================
    // NEW FINGERPRINT ROUTES (CONSOLIDATED)
    // ========================================
    
    Route::prefix('fingerprint')->group(function () {
        // Raw attendance log management
        Route::post('import-logs', [FingerprintController::class, 'importAttendanceLog']);
        Route::post('process-logs', [FingerprintController::class, 'processUnprocessedLogs']);
        Route::post('set-pin', [FingerprintController::class, 'setPinFingerprint']);
        Route::get('logs', [FingerprintController::class, 'getAttendanceLogs']);
        Route::get('summary', [FingerprintController::class, 'getAttendanceSummary']);
        
        // Testing/Development endpoints
        Route::post('manual-sync', [FingerprintController::class, 'manualSync']);
        Route::post('generate-dummy-data', [FingerprintController::class, 'generateDummyData']);
        Route::post('simulate-scan', [FingerprintController::class, 'simulateScan']);
    });

    // Device integration (for actual hardware)
    Route::prefix('fingerspot')->group(function () {
        Route::get('userinfo', [FingerspotIntegrationController::class, 'getUserInfo']);
        Route::post('register-user', [FingerspotIntegrationController::class, 'registerUser']);
        Route::delete('remove-user', [FingerspotIntegrationController::class, 'removeUser']);
        Route::post('sync-all-users', [FingerspotIntegrationController::class, 'syncAllUsers']);
        Route::post('import-attendance', [FingerspotIntegrationController::class, 'importAttendance']);
        Route::post('set-time', [FingerspotIntegrationController::class, 'setDeviceTime']);
        Route::post('restart', [FingerspotIntegrationController::class, 'restartDevice']);
        Route::get('status', [FingerspotIntegrationController::class, 'getDeviceStatus']);
        
        // Testing mode
        Route::post('enable-mock-mode', [FingerspotIntegrationController::class, 'enableMockMode']);
        Route::post('disable-mock-mode', [FingerspotIntegrationController::class, 'disableMockMode']);
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
        Route::get('summary/{periode}', [GajiTambahanController::class, 'getSummaryByDepartemen']);
    });

    // ========================================
    // TESTING ROUTES (DEVELOPMENT ONLY)
    // ========================================
    
    Route::prefix('testing')->group(function () {
    Route::post('create-sample-attendance', [TestingController::class, 'createSampleAttendance']);   // ✅ ada
    Route::post('generate-month-data/{karyawan_id}/{periode}', [TestingController::class, 'generateMonthData']); // ✅ ada
    Route::post('generate-sample-data-for-all', [TestingController::class, 'generateSampleDataForAll']); // ✅ ada
    Route::post('test-calculation', [TestingController::class, 'testCalculation']); // ✅ ada
    Route::get('attendance-summary/{periode}', [TestingController::class, 'getAttendanceSummary']); // ✅ ada
    Route::delete('clear-test-data', [TestingController::class, 'clearTestData']); // ✅ ada
});
});