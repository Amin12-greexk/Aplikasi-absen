<?php
// app/Http/Controllers/Api/FingerspotIntegrationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FingerspotService;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FingerspotIntegrationController extends Controller
{
    protected $fingerspotService;

    public function __construct(FingerspotService $fingerspotService)
    {
        $this->fingerspotService = $fingerspotService;
    }

    /**
     * Get user info from fingerprint device
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        $pin = $request->get('pin');
        
        try {
            $userInfo = $this->fingerspotService->getUserInfo($pin);
            
            return response()->json([
                'message' => 'User info retrieved successfully',
                'data' => $userInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get user info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register karyawan to fingerprint device
     */
    public function registerUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'karyawan_id' => 'required|exists:karyawan,karyawan_id',
            'pin' => 'required|string|max:20|unique:karyawan,pin_fingerprint'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $karyawan = Karyawan::find($request->karyawan_id);
            
            // Update PIN di database
            $karyawan->update(['pin_fingerprint' => $request->pin]);

            // Sync ke mesin fingerprint
            $result = $this->fingerspotService->syncUserToDevice($karyawan);

            return response()->json([
                'message' => 'User registered successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap', 'pin_fingerprint']),
                'device_result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove user from fingerprint device
     */
    public function removeUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'karyawan_id' => 'required|exists:karyawan,karyawan_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $karyawan = Karyawan::find($request->karyawan_id);
            
            if (!$karyawan->pin_fingerprint) {
                return response()->json([
                    'message' => 'Karyawan tidak memiliki PIN fingerprint'
                ], 400);
            }

            // Remove dari mesin
            $result = $this->fingerspotService->deleteUserInfo($karyawan->pin_fingerprint);

            // Clear PIN dari database
            $karyawan->update(['pin_fingerprint' => null]);

            return response()->json([
                'message' => 'User removed successfully',
                'karyawan' => $karyawan->only(['karyawan_id', 'nama_lengkap']),
                'device_result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all users to device
     */
    public function syncAllUsers(): JsonResponse
    {
        try {
            $result = $this->fingerspotService->syncAllUsersToDevice();

            return response()->json([
                'message' => 'Sync completed',
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count'],
                'results' => $result['results'],
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import attendance from device for date range
     */
    public function importAttendance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->fingerspotService->importAndSaveAttlog(
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'message' => 'Import completed',
                'imported_count' => $result['imported'],
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set device time
     */
    public function setDeviceTime(): JsonResponse
    {
        try {
            $success = $this->fingerspotService->setDeviceTime();

            if ($success) {
                return response()->json([
                    'message' => 'Device time updated successfully',
                    'current_time' => now()->format('Y-m-d H:i:s')
                ]);
            }

            return response()->json([
                'message' => 'Failed to update device time'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set device time',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart fingerprint device
     */
    public function restartDevice(): JsonResponse
    {
        try {
            $success = $this->fingerspotService->restartDevice();

            if ($success) {
                return response()->json([
                    'message' => 'Device restart command sent successfully'
                ]);
            }

            return response()->json([
                'message' => 'Failed to restart device'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restart device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get device status and info
     */
    public function getDeviceStatus(): JsonResponse
    {
        try {
            // Coba ambil user info untuk test koneksi
            $userInfo = $this->fingerspotService->getUserInfo();
            
            return response()->json([
                'status' => 'online',
                'message' => 'Device is accessible',
                'user_count' => count($userInfo),
                'last_check' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'offline',
                'message' => 'Device is not accessible',
                'error' => $e->getMessage(),
                'last_check' => now()->format('Y-m-d H:i:s')
            ], 503);
        }
    }
}