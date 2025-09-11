<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $shifts = Shift::all();
        return response()->json($shifts);
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'kode_shift' => 'required|string|max:10|unique:shift,kode_shift',
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i',
            'hari_berikutnya' => 'boolean'
        ]);

        $shift = Shift::create($validatedData);
        return response()->json($shift, 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json($shift);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validatedData = $request->validate([
            'kode_shift' => 'required|string|max:10|unique:shift,kode_shift,' . $shift->shift_id . ',shift_id',
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i',
            'hari_berikutnya' => 'boolean'
        ]);

        $shift->update($validatedData);
        return response()->json($shift);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        // Cek apakah shift masih digunakan dalam jadwal
        if ($shift->jadwalShift()->exists()) {
            return response()->json([
                'message' => 'Cannot delete shift that is still in use'
            ], 422);
        }

        $shift->delete();
        return response()->json(null, 204);
    }
}