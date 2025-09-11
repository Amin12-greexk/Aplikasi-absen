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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Endpoint untuk Autentikasi (Public)
Route::post('/login', [AuthController::class, 'login']);

// Endpoint Webhook dari Fingerspot (Public, perlu validasi token/IP sendiri)
Route::post('/webhook/fingerspot', function(Request $request) {
    // Logika untuk menangani data realtime attlog dari mesin
    // Sebaiknya dibuatkan Controller dan Service khusus untuk ini
    Log::info('Webhook Fingerspot Diterima:', $request->all());
    return response()->json(['message' => 'Webhook received']);
});


// Grup Endpoint yang memerlukan Autentikasi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Informasi user yang sedang login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('absensi', AbsensiController::class);
    Route::get('absensi/laporan/bulanan', [AbsensiController::class, 'getLaporanBulanan']);
    Route::get('absensi/rekap/{tanggal}', [AbsensiController::class, 'getRekapHarian']);
    Route::get('jadwal-shift/karyawan/{karyawan_id}/{periode}', [JadwalShiftController::class, 'getJadwalKaryawan']);
    Route::delete('jadwal-shift', [JadwalShiftController::class, 'deleteJadwal']);
    
    // Payroll tambahan  
    Route::get('payroll', [PayrollController::class, 'getAllPayrolls']);
    Route::post('payroll/bulk-generate', [PayrollController::class, 'bulkGenerate']);

    // CRUD Endpoints
    Route::apiResource('karyawan', KaryawanController::class);
    Route::apiResource('departemen', DepartemenController::class);
    Route::apiResource('jabatan', JabatanController::class);
    Route::apiResource('shift', ShiftController::class);

    // Endpoint khusus untuk Jadwal Shift
    Route::get('jadwal-shift/{departemen_id}/{tahun}/{bulan}', [JadwalShiftController::class, 'getJadwalByDepartemen']);
    Route::post('jadwal-shift', [JadwalShiftController::class, 'updateJadwal']);

    // Endpoint untuk Penggajian
    Route::post('payroll/generate', [PayrollController::class, 'generate']);
    Route::get('payroll/history/{karyawan_id}', [PayrollController::class, 'getHistory']);
    Route::get('payroll/slip/{gaji_id}', [PayrollController::class, 'getSlipGaji']);
});
