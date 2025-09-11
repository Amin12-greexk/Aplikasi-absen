<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\HistoriJabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class KaryawanController extends Controller
{
    public function index(): JsonResponse
    {
        $karyawan = Karyawan::with(['departemenSaatIni', 'jabatanSaatIni'])->get();
        return response()->json($karyawan);
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'nik' => 'required|string|max:20|unique:karyawan,nik',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:karyawan,email',
            'tanggal_masuk' => 'required|date',
            'departemen_id_saat_ini' => 'required|exists:departemen,departemen_id',
            'jabatan_id_saat_ini' => 'required|exists:jabatan,jabatan_id',
            // Tambahkan validasi lain sesuai kebutuhan
        ]);

        return DB::transaction(function () use ($validatedData) {
            $karyawan = Karyawan::create($validatedData);

            // Buat histori jabatan pertama kali
            HistoriJabatan::create([
                'karyawan_id' => $karyawan->karyawan_id,
                'departemen_id' => $karyawan->departemen_id_saat_ini,
                'jabatan_id' => $karyawan->jabatan_id_saat_ini,
                'tanggal_mulai' => $karyawan->tanggal_masuk,
            ]);

            return response()->json($karyawan, 201);
        });
    }

    public function show(Karyawan $karyawan): JsonResponse
    {
        $karyawan->load(['departemenSaatIni', 'jabatanSaatIni', 'historiJabatan.departemen', 'historiJabatan.jabatan']);
        return response()->json($karyawan);
    }

    public function update(Request $request, Karyawan $karyawan): JsonResponse
    {
        $validatedData = $request->validate([
             'nik' => 'required|string|max:20|unique:karyawan,nik,' . $karyawan->karyawan_id . ',karyawan_id',
             'nama_lengkap' => 'required|string|max:255',
             'email' => 'required|email|max:255|unique:karyawan,email,' . $karyawan->karyawan_id . ',karyawan_id',
             'departemen_id_saat_ini' => 'required|exists:departemen,departemen_id',
             'jabatan_id_saat_ini' => 'required|exists:jabatan,jabatan_id',
             // ... validasi lainnya
        ]);

        return DB::transaction(function () use ($validatedData, $karyawan) {
            // Cek jika ada perubahan departemen atau jabatan
            if ($karyawan->departemen_id_saat_ini != $validatedData['departemen_id_saat_ini'] ||
                $karyawan->jabatan_id_saat_ini != $validatedData['jabatan_id_saat_ini']) {
                
                // 1. Akhiri histori jabatan yang lama
                $historiLama = HistoriJabatan::where('karyawan_id', $karyawan->karyawan_id)->latest('tanggal_mulai')->first();
                if($historiLama) {
                    $historiLama->update(['tanggal_selesai' => now()->subDay()]);
                }

                // 2. Buat histori jabatan yang baru
                HistoriJabatan::create([
                    'karyawan_id' => $karyawan->karyawan_id,
                    'departemen_id' => $validatedData['departemen_id_saat_ini'],
                    'jabatan_id' => $validatedData['jabatan_id_saat_ini'],
                    'tanggal_mulai' => now(),
                ]);
            }

            $karyawan->update($validatedData);

            return response()->json($karyawan);
        });
    }

    public function destroy(Karyawan $karyawan): JsonResponse
    {
        // Sebaiknya jangan hapus, tapi ubah status menjadi 'Resign'
        $karyawan->update(['status' => 'Resign']);
        return response()->json(null, 204);
    }
}
