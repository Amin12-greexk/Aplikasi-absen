<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JabatanController extends Controller
{
    public function index(): JsonResponse
    {
        $jabatan = Jabatan::withCount('karyawan')->get();
        return response()->json($jabatan);
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan,nama_jabatan'
        ]);

        $jabatan = Jabatan::create($validatedData);
        return response()->json($jabatan, 201);
    }

    public function show(Jabatan $jabatan): JsonResponse
    {
        $jabatan->load(['karyawan.departemenSaatIni']);
        return response()->json($jabatan);
    }

    public function update(Request $request, Jabatan $jabatan): JsonResponse
    {
        $validatedData = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan,nama_jabatan,' . $jabatan->jabatan_id . ',jabatan_id'
        ]);

        $jabatan->update($validatedData);
        return response()->json($jabatan);
    }

    public function destroy(Jabatan $jabatan): JsonResponse
    {
        // Cek apakah masih ada karyawan aktif
        if ($jabatan->karyawan()->where('status', 'Aktif')->exists()) {
            return response()->json([
                'message' => 'Cannot delete position with active employees'
            ], 422);
        }

        $jabatan->delete();
        return response()->json(null, 204);
    }
}