<?php
// app/Http/Controllers/Api/SettingGajiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SettingGaji;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SettingGajiController extends Controller
{
    /**
     * Get all setting gaji
     */
    public function index(): JsonResponse
    {
        $settings = SettingGaji::orderBy('created_at', 'desc')->get();
        return response()->json($settings);
    }

    /**
     * Create new setting gaji
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'premi_produksi' => 'required|numeric|min:0',
            'premi_staff' => 'required|numeric|min:0',
            'uang_makan_produksi_weekday' => 'required|numeric|min:0',
            'uang_makan_produksi_weekend_5_10' => 'required|numeric|min:0',
            'uang_makan_produksi_weekend_10_20' => 'required|numeric|min:0',
            'uang_makan_staff_weekday' => 'required|numeric|min:0',
            'uang_makan_staff_weekend_5_10' => 'required|numeric|min:0',
            'uang_makan_staff_weekend_10_20' => 'required|numeric|min:0',
            'tarif_lembur_produksi_per_jam' => 'required|numeric|min:0',
            'tarif_lembur_staff_per_jam' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Deactivate all previous settings
        SettingGaji::where('is_active', true)->update(['is_active' => false]);

        // Create new active setting
        $setting = SettingGaji::create(array_merge($request->all(), ['is_active' => true]));

        return response()->json([
            'message' => 'Setting gaji berhasil dibuat',
            'data' => $setting
        ], 201);
    }

    /**
     * Get specific setting
     */
    public function show($id): JsonResponse
    {
        $setting = SettingGaji::find($id);

        if (!$setting) {
            return response()->json(['message' => 'Setting tidak ditemukan'], 404);
        }

        return response()->json($setting);
    }

    /**
     * Update setting gaji
     */
    public function update(Request $request, $id): JsonResponse
    {
        $setting = SettingGaji::find($id);

        if (!$setting) {
            return response()->json(['message' => 'Setting tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'premi_produksi' => 'sometimes|numeric|min:0',
            'premi_staff' => 'sometimes|numeric|min:0',
            'uang_makan_produksi_weekday' => 'sometimes|numeric|min:0',
            'uang_makan_produksi_weekend_5_10' => 'sometimes|numeric|min:0',
            'uang_makan_produksi_weekend_10_20' => 'sometimes|numeric|min:0',
            'uang_makan_staff_weekday' => 'sometimes|numeric|min:0',
            'uang_makan_staff_weekend_5_10' => 'sometimes|numeric|min:0',
            'uang_makan_staff_weekend_10_20' => 'sometimes|numeric|min:0',
            'tarif_lembur_produksi_per_jam' => 'sometimes|numeric|min:0',
            'tarif_lembur_staff_per_jam' => 'sometimes|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $setting->update($request->all());

        return response()->json([
            'message' => 'Setting gaji berhasil diupdate',
            'data' => $setting
        ]);
    }

    /**
     * Activate specific setting
     */
    public function activate($id): JsonResponse
    {
        $setting = SettingGaji::find($id);

        if (!$setting) {
            return response()->json(['message' => 'Setting tidak ditemukan'], 404);
        }

        // Deactivate all settings
        SettingGaji::where('is_active', true)->update(['is_active' => false]);

        // Activate selected setting
        $setting->update(['is_active' => true]);

        return response()->json([
            'message' => 'Setting berhasil diaktifkan',
            'data' => $setting
        ]);
    }

    /**
     * Get active setting
     */
    public function getActiveSetting(): JsonResponse
    {
        $setting = SettingGaji::getActiveSetting();

        if (!$setting) {
            return response()->json(['message' => 'Tidak ada setting aktif'], 404);
        }

        return response()->json($setting);
    }
}