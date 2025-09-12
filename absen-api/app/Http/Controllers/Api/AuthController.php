<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Karyawan; // Ganti User ke Karyawan
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'nik' => 'required|string',
            'password' => 'required|string',
        ]);

        $karyawan = Karyawan::where('nik', $credentials['nik'])->first();

        if (! $karyawan || ! Hash::check($credentials['password'], $karyawan->password)) {
            return response()->json(['message' => 'NIK atau Password salah.'], 401);
        }

        // Hapus token lama jika ada, lalu buat yang baru
        $karyawan->tokens()->delete();
        $token = $karyawan->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $karyawan // Kirim data karyawan yang login
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out']);
    }
}