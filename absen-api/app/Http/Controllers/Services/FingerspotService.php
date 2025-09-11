<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FingerspotService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        // Ambil dari .env, jangan hardcode
        $this->apiUrl = config('services.fingerspot.url');
        $this->apiKey = config('services.fingerspot.key');
    }

    /**
     * Mengambil data log absensi dari API Fingerspot.
     * INI HANYA CONTOH, SESUAIKAN DENGAN SDK/API SEBENARNYA.
     */
    public function getAttlog(string $startDate, string $endDate)
    {
        // Logika untuk memanggil API Fingerspot SDK
        // Contoh menggunakan Laravel HTTP Client
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $this->apiKey,
        // ])->get($this->apiUrl . '/get_attlog', [
        //     'start_date' => $startDate,
        //     'end_date' => $endDate,
        // ]);
        
        // return $response->json();
        
        // Data dummy untuk pengembangan
        return [
            ['pin' => '12345', 'scan_date' => '2025-09-11 07:59:01', 'verify_mode' => 1],
            ['pin' => '12345', 'scan_date' => '2025-09-11 17:05:30', 'verify_mode' => 1],
        ];
    }
    
    // Implementasikan method lain sesuai kebutuhan:
    // public function getUserInfo($pin) {}
    // public function setUserInfo($data) {}
    // public function deleteUserInfo($pin) {}
}
