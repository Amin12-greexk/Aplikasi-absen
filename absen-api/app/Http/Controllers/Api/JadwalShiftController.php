<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JadwalShiftController extends Controller
{
    /**
     * Mengambil data jadwal shift untuk satu departemen dalam rentang bulan dan tahun tertentu.
     * Dirancang untuk ditampilkan di antarmuka kalender.
     */
    public function getJadwalByDepartemen(int $departemen_id, int $tahun, int $bulan): JsonResponse
    {
        $jadwal = Karyawan::where('departemen_id_saat_ini', $departemen_id)
            ->where('status', 'Aktif')
            ->with(['jadwalShift' => function ($query) use ($tahun, $bulan) {
                $query->whereYear('tanggal_jadwal', $tahun)
                      ->whereMonth('tanggal_jadwal', $bulan)
                      ->with('shift'); // Eager load data shift
            }])
            ->get();

        return response()->json($jadwal);
    }

    /**
     * Menyimpan atau memperbarui satu atau beberapa jadwal shift sekaligus.
     * Fleksibel untuk drag-and-drop atau update massal.
     */
    public function updateJadwal(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'jadwal' => 'required|array',
            'jadwal.*.karyawan_id' => 'required|exists:karyawan,karyawan_id',
            'jadwal.*.tanggal_jadwal' => 'required|date_format:Y-m-d',
            'jadwal.*.shift_id' => 'required|exists:shift,shift_id',
        ]);

        DB::transaction(function () use ($validatedData) {
            foreach ($validatedData['jadwal'] as $item) {
                JadwalShift::updateOrCreate(
                    [
                        'karyawan_id' => $item['karyawan_id'],
                        'tanggal_jadwal' => $item['tanggal_jadwal'],
                    ],
                    [
                        'shift_id' => $item['shift_id'],
                    ]
                );
            }
        });

        return response()->json(['message' => 'Jadwal shift berhasil diperbarui.']);
    }
}