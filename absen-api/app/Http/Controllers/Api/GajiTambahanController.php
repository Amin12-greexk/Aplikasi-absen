<?php
// app/Http/Controllers/Api/GajiTambahanController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GajiTambahanService;
use App\Models\Karyawan;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class GajiTambahanController extends Controller
{
    protected $gajiTambahanService;

    public function __construct(GajiTambahanService $gajiTambahanService)
    {
        $this->gajiTambahanService = $gajiTambahanService;
    }

    /**
     * Calculate gaji tambahan untuk satu hari
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_karyawan' => 'required|in:produksi,staff',
            'jenis_hari' => 'required|in:weekday,weekend,tanggal_merah',
            'jam_lembur' => 'required|numeric|min:0',
            'jam_selesai_kerja' => 'required|date_format:H:i',
            'hadir_6_hari' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $jamSelesai = \Carbon\Carbon::createFromFormat('H:i', $request->jam_selesai_kerja);
            
            $result = $this->gajiTambahanService->hitungGajiTambahan(
                $request->role_karyawan,
                $request->jenis_hari,
                $request->jam_lembur,
                $jamSelesai,
                $request->hadir_6_hari
            );

            return response()->json([
                'message' => 'Perhitungan berhasil',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghitung gaji tambahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gaji tambahan untuk satu periode karyawan
     */
    public function getPeriode($karyawan_id, $periode): JsonResponse
    {
        $karyawan = Karyawan::find($karyawan_id);

        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
        }

        try {
            $result = $this->gajiTambahanService->hitungGajiTambahanPeriode($karyawan_id, $periode);

            return response()->json([
                'message' => 'Data berhasil diambil',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'role_karyawan']),
                'periode' => $periode,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data gaji tambahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate semua gaji tambahan untuk periode tertentu
     */
    public function recalculateAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'periode' => 'required|date_format:Y-m'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $periode = $request->periode;
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);

            $absensiList = Absensi::with('karyawan')
                ->whereYear('tanggal_absensi', $year)
                ->whereMonth('tanggal_absensi', $month)
                ->get();

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
            }

            return response()->json([
                'message' => 'Recalculation selesai',
                'periode' => $periode,
                'updated_count' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal melakukan recalculation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary gaji tambahan per departemen
     */
    public function getSummaryByDepartemen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'periode' => 'required|date_format:Y-m'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $periode = $request->periode;
        $year = substr($periode, 0, 4);
        $month = substr($periode, 5, 2);

        $summary = Absensi::selectRaw('
                departemen.nama_departemen,
                karyawan.role_karyawan,
                COUNT(*) as total_absensi,
                SUM(upah_lembur) as total_upah_lembur,
                SUM(premi) as total_premi,
                SUM(uang_makan) as total_uang_makan,
                SUM(total_gaji_tambahan) as total_gaji_tambahan
            ')
            ->join('karyawan', 'absensi.karyawan_id', '=', 'karyawan.karyawan_id')
            ->join('departemen', 'karyawan.departemen_id_saat_ini', '=', 'departemen.departemen_id')
            ->whereYear('tanggal_absensi', $year)
            ->whereMonth('tanggal_absensi', $month)
            ->groupBy('departemen.departemen_id', 'departemen.nama_departemen', 'karyawan.role_karyawan')
            ->get();

        return response()->json([
            'periode' => $periode,
            'summary' => $summary
        ]);
    }
}