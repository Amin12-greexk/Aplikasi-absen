<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\HistoriJabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class KaryawanController extends Controller
{
    public function index(): JsonResponse
    {
        $karyawan = Karyawan::with(['departemenSaatIni', 'jabatanSaatIni'])->get();
        return response()->json($karyawan);
    }

     public function store(Request $request): JsonResponse
    {
        // Validasi input dari frontend (pastikan lengkap)
        $validatedData = $request->validate([
            
            'nik' => 'required|string|max:20|unique:karyawan,nik',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:karyawan,email',
            'tanggal_masuk' => 'required|date',
            'departemen_id_saat_ini' => 'required|exists:departemen,departemen_id',
            'jabatan_id_saat_ini' => 'required|exists:jabatan,jabatan_id',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tarif_harian' => 'nullable|numeric|min:0',
            'status_perkawinan' => 'required|in:Belum Menikah,Menikah,Cerai',
            'kategori_gaji' => 'required|in:Bulanan,Harian,Borongan',
            'status' => 'required|in:Aktif,Resign',
            'alamat' => 'nullable|string',
            'nomor_telepon' => 'nullable|string|max:20',
        ]);

        return DB::transaction(function () use ($validatedData) {
            // --- PERUBAHAN DI SINI ---
            // Buat password secara otomatis sebelum menyimpan data.
            $validatedData['password'] = Hash::make('tunasesta' . $validatedData['nik']);

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
        // Pastikan validasi di method update juga lengkap
        $validatedData = $request->validate([
            'nik' => 'required|string|max:20|unique:karyawan,nik,' . $karyawan->karyawan_id . ',karyawan_id',
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'alamat' => 'required|string',
            'status_perkawinan' => 'required|in:Belum Menikah,Menikah,Cerai',
            'nomor_telepon' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:karyawan,email,' . $karyawan->karyawan_id . ',karyawan_id',
            'tanggal_masuk' => 'required|date',
            'tarif_harian' => 'nullable|numeric|min:0',
            'kategori_gaji' => 'required|in:Bulanan,Harian,Borongan',
            'jam_kerja_masuk' => 'nullable|date_format:H:i:s',
            'jam_kerja_pulang' => 'nullable|date_format:H:i:s',
            'status' => 'required|in:Aktif,Resign',
            'departemen_id_saat_ini' => 'required|exists:departemen,departemen_id',
            'jabatan_id_saat_ini' => 'required|exists:jabatan,jabatan_id',
        ]);

        return DB::transaction(function () use ($validatedData, $karyawan) {
            if ($karyawan->departemen_id_saat_ini != $validatedData['departemen_id_saat_ini'] ||
                $karyawan->jabatan_id_saat_ini != $validatedData['jabatan_id_saat_ini']) {

                $historiLama = HistoriJabatan::where('karyawan_id', $karyawan->karyawan_id)->latest('tanggal_mulai')->first();
                if($historiLama) {
                    $historiLama->update(['tanggal_selesai' => now()->subDay()->toDateString()]);
                }

                HistoriJabatan::create([
                    'karyawan_id' => $karyawan->karyawan_id,
                    'departemen_id' => $validatedData['departemen_id_saat_ini'],
                    'jabatan_id' => $validatedData['jabatan_id_saat_ini'],
                    'tanggal_mulai' => now()->toDateString(),
                ]);
            }

            $karyawan->update($validatedData);

            return response()->json($karyawan->load(['departemenSaatIni', 'jabatanSaatIni']));
        });
    }

    public function destroy(Karyawan $karyawan): JsonResponse
    {
        $karyawan->update(['status' => 'Resign']);
        return response()->json(null, 204);
    }
}

