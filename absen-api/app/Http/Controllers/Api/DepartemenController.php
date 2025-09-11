<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DepartemenController extends Controller
{
    public function index(): JsonResponse
    {
        $departemen = Departemen::withCount('karyawan')->get();
        return response()->json($departemen);
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'nama_departemen' => 'required|string|max:100|unique:departemen,nama_departemen',
            'menggunakan_shift' => 'boolean'
        ]);

        $departemen = Departemen::create($validatedData);
        return response()->json($departemen, 201);
    }

    public function show(Departemen $departemen): JsonResponse
    {
        $departemen->load(['karyawan.jabatanSaatIni']);
        return response()->json($departemen);
    }

    public function update(Request $request, Departemen $departemen): JsonResponse
    {
        $validatedData = $request->validate([
            'nama_departemen' => 'required|string|max:100|unique:departemen,nama_departemen,' . $departemen->departemen_id . ',departemen_id',
            'menggunakan_shift' => 'boolean'
        ]);

        $departemen->update($validatedData);
        return response()->json($departemen);
    }

    public function destroy(Departemen $departemen): JsonResponse
    {
        // Cek apakah masih ada karyawan aktif
        if ($departemen->karyawan()->where('status', 'Aktif')->exists()) {
            return response()->json([
                'message' => 'Cannot delete department with active employees'
            ], 422);
        }

        $departemen->delete();
        return response()->json(null, 204);
    }
}