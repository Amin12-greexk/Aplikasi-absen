<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use App\Models\RiwayatGaji;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function generate(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'karyawan_id' => 'required|exists:karyawan,karyawan_id',
            'periode' => 'required|date_format:Y-m'
        ]);

        try {
            $riwayatGaji = $this->payrollService->generate(
                $validatedData['karyawan_id'],
                $validatedData['periode']
            );

            return response()->json([
                'message' => 'Payroll generated successfully',
                'data' => $riwayatGaji
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getHistory(int $karyawan_id): JsonResponse
    {
        $history = RiwayatGaji::where('karyawan_id', $karyawan_id)
            ->with(['karyawan', 'detailGaji'])
            ->orderBy('periode', 'desc')
            ->get();

        return response()->json($history);
    }

    public function getSlipGaji(int $gaji_id): JsonResponse
    {
        $slip = RiwayatGaji::with(['karyawan.departemenSaatIni', 'karyawan.jabatanSaatIni', 'detailGaji'])
            ->findOrFail($gaji_id);

        return response()->json($slip);
    }

    // app/Http/Controllers/Api/PayrollController.php
    public function getAllPayrolls(Request $request): JsonResponse
    {
        $periode = $request->get('periode'); // format: Y-m
        $departemen_id = $request->get('departemen_id');
        $tipe_periode = $request->get('tipe_periode'); // harian/mingguan/bulanan

        $query = RiwayatGaji::with(['karyawan.departemenSaatIni', 'karyawan.jabatanSaatIni']);

        if ($periode) {
            $year = substr($periode, 0, 4);
            $month = substr($periode, 5, 2);
            $query->whereYear('periode_mulai', $year)
                ->whereMonth('periode_mulai', $month);
        }

        if ($departemen_id) {
            $query->whereHas('karyawan', function ($q) use ($departemen_id) {
                $q->where('departemen_id_saat_ini', $departemen_id);
            });
        }

        if ($tipe_periode) {
            $query->where('tipe_periode', $tipe_periode);
        }

        $payrolls = $query->orderBy('periode_mulai', 'desc')->paginate(20);

        // Transform data untuk frontend
        $payrolls->getCollection()->transform(function ($item) {
            return [
                'gaji_id' => $item->gaji_id,
                'nik' => $item->karyawan->nik,
                'nama_karyawan' => $item->karyawan->nama_lengkap,
                'email' => $item->karyawan->email,
                'departemen' => $item->karyawan->departemenSaatIni->nama_departemen,
                'periode' => $item->periode_label, // Ini yang akan tampil di UI
                'periode_raw' => $item->periode,
                'tipe_periode' => $item->tipe_periode,
                'tanggal_mulai' => $item->periode_mulai,
                'tanggal_selesai' => $item->periode_selesai,
                'total_gaji' => $item->gaji_final,
                'status' => $item->status ?? 'draft', // Add status field
                'tanggal_bayar' => $item->tanggal_pembayaran,
            ];
        });

        return response()->json($payrolls);
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'periode' => 'required|date_format:Y-m',
            'karyawan_ids' => 'required|array',
            'karyawan_ids.*' => 'exists:karyawan,karyawan_id'
        ]);

        $results = [];
        $errors = [];

        foreach ($validatedData['karyawan_ids'] as $karyawan_id) {
            try {
                $riwayatGaji = $this->payrollService->generate($karyawan_id, $validatedData['periode']);
                $results[] = $riwayatGaji;
            } catch (\Exception $e) {
                $errors[] = [
                    'karyawan_id' => $karyawan_id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk payroll generation completed',
            'success_count' => count($results),
            'error_count' => count($errors),
            'results' => $results,
            'errors' => $errors
        ]);
    }
}