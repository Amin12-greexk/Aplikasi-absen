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
        // Anda bisa menambahkan ini di config/services.php nanti
        // $this->apiUrl = config('services.fingerspot.url');
        // $this->apiKey = config('services.fingerspot.key');
    }

    /**
     * Mengambil data log absensi dari API Fingerspot.
     * INI HANYA CONTOH, SESUAIKAN DENGAN SDK/API SEBENARNYA.
     */
    public function getAttlog(string $startDate, string $endDate)
    {
        // Logika untuk memanggil API Fingerspot SDK
        // Data dummy untuk pengembangan agar tidak error
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

