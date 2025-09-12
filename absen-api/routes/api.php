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
    Route::get('jadwal-shift/{departemen_id}/{tahun}/{bulan}', [JadwalShiftController::class, 'getJadwalByDepartemen']); // Tambahkan middleware jika perlu
    Route::post('jadwal-shift', [JadwalShiftController::class, 'updateJadwal']); // Tambahkan middleware jika perlu

    // Penggajian
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->middleware('can:process-payroll');
    Route::get('payroll/history/{karyawan_id}', [PayrollController::class, 'getHistory'])->middleware('can:view-any-slip');
    Route::get('payroll/slip/{gaji_id}', [PayrollController::class, 'getSlipGaji']); // Logika akses slip ada di dalam controller
});