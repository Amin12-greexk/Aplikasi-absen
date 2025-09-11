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

    public function getAllPayrolls(Request $request): JsonResponse
    {
        $periode = $request->get('periode');
        $departemen_id = $request->get('departemen_id');

        $query = RiwayatGaji::with(['karyawan.departemenSaatIni', 'karyawan.jabatanSaatIni']);

        if ($periode) {
            $query->where('periode', $periode);
        }

        if ($departemen_id) {
            $query->whereHas('karyawan', function ($q) use ($departemen_id) {
                $q->where('departemen_id_saat_ini', $departemen_id);
            });
        }

        $payrolls = $query->orderBy('periode', 'desc')->paginate(20);

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