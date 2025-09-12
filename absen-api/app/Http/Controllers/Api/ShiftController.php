<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    /**
     * Menampilkan semua data shift.
     */
    public function index(): JsonResponse
    {
        return response()->json(Shift::all());
    }

    /**
     * Menyimpan data shift baru.
     */
    public function store(Request $request): JsonResponse
    {
        // --- PERUBAHAN DI SINI ---
        $validatedData = $request->validate([
            'kode_shift' => 'required|string|max:10|unique:shift,kode_shift',
            'jam_masuk' => 'nullable|date_format:H:i:s', // Diubah dari H:i menjadi H:i:s
            'jam_pulang' => 'nullable|date_format:H:i:s', // Diubah dari H:i menjadi H:i:s
            'hari_berikutnya' => 'required|boolean',
        ]);

        $shift = Shift::create($validatedData);

        return response()->json($shift, 201);
    }

    /**
     * Menampilkan satu data shift spesifik.
     */
    public function show(Shift $shift): JsonResponse
    {
        return response()->json($shift);
    }

    /**
     * Memperbarui data shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        // --- DAN PERUBAHAN DI SINI ---
        $validatedData = $request->validate([
            'kode_shift' => 'required|string|max:10|unique:shift,kode_shift,' . $shift->shift_id . ',shift_id',
            'jam_masuk' => 'nullable|date_format:H:i:s', // Diubah dari H:i menjadi H:i:s
            'jam_pulang' => 'nullable|date_format:H:i:s', // Diubah dari H:i menjadi H:i:s
            'hari_berikutnya' => 'required|boolean',
        ]);

        $shift->update($validatedData);

        return response()->json($shift);
    }

    /**
     * Menghapus data shift.
     */
    public function destroy(Shift $shift): JsonResponse
    {
        if ($shift->jadwalShift()->exists()) {
            return response()->json(['message' => 'Shift tidak dapat dihapus karena masih digunakan dalam jadwal.'], 409);
        }
        
        $shift->delete();

        return response()->json(null, 204);
    }
}
