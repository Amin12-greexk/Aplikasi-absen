<?php
// app/Services/FingerspotService.php (UPDATED)

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AttendanceLog;
use App\Models\Karyawan;
use Carbon\Carbon;

class FingerspotService
{
    protected $apiUrl;
    protected $apiKey;
    protected $deviceIp;
    protected $devicePort;

    public function __construct()
    {
        // Ambil dari config yang bisa diset di .env
        $this->apiUrl = config('services.fingerspot.url', 'http://192.168.11.24');
        $this->apiKey = config('services.fingerspot.key');
        $this->deviceIp = config('services.fingerspot.device_ip', '192.168.11.24');
        $this->devicePort = config('services.fingerspot.device_port', 80);
    }

    /**
     * Mengambil data log absensi dari API Fingerspot/Revo SDK
     */
    public function getAttlog(string $startDate, string $endDate)
    {
        try {
            // CONTOH implementasi untuk SDK sebenarnya
            // Sesuaikan dengan dokumentasi SDK Revo 185BNC
            
            $response = Http::timeout(30)->get("{$this->apiUrl}/api/attlog", [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'device_ip' => $this->deviceIp
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Failed to fetch attlog: " . $response->body());

        } catch (\Exception $e) {
            Log::error("Fingerspot API Error", ['error' => $e->getMessage()]);
            
            // Return dummy data untuk development
            return $this->getDummyAttlogData($startDate, $endDate);
        }
    }

    /**
     * Mengambil info user dari mesin fingerprint
     */
    public function getUserInfo($pin = null)
    {
        try {
            $url = "{$this->apiUrl}/api/userinfo";
            if ($pin) {
                $url .= "?pin={$pin}";
            }

            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Failed to fetch userinfo: " . $response->body());

        } catch (\Exception $e) {
            Log::error("Fingerspot getUserInfo Error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Set/Register user ke mesin fingerprint
     */
    public function setUserInfo($userData)
    {
        try {
            $response = Http::timeout(30)->post("{$this->apiUrl}/api/userinfo", [
                'pin' => $userData['pin'],
                'name' => $userData['name'],
                'privilege' => $userData['privilege'] ?? 0,
                'password' => $userData['password'] ?? '',
                'card' => $userData['card'] ?? '',
                'group' => $userData['group'] ?? 1,
                'timezone' => $userData['timezone'] ?? 1
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Failed to set userinfo: " . $response->body());

        } catch (\Exception $e) {
            Log::error("Fingerspot setUserInfo Error", [
                'error' => $e->getMessage(),
                'user_data' => $userData
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Hapus user dari mesin fingerprint
     */
    public function deleteUserInfo($pin)
    {
        try {
            $response = Http::timeout(30)->delete("{$this->apiUrl}/api/userinfo/{$pin}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Failed to delete userinfo: " . $response->body());

        } catch (\Exception $e) {
            Log::error("Fingerspot deleteUserInfo Error", [
                'error' => $e->getMessage(),
                'pin' => $pin
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync user dari database ke mesin fingerprint
     */
    public function syncUserToDevice(Karyawan $karyawan)
    {
        if (!$karyawan->pin_fingerprint) {
            throw new \Exception("Karyawan {$karyawan->nama_lengkap} belum memiliki PIN fingerprint");
        }

        $userData = [
            'pin' => $karyawan->pin_fingerprint,
            'name' => $karyawan->nama_lengkap,
            'privilege' => $karyawan->role === 'hr' || $karyawan->role === 'direktur' ? 14 : 0,
            'group' => 1,
            'timezone' => 1
        ];

        return $this->setUserInfo($userData);
    }

    /**
     * Sync semua user aktif ke mesin
     */
    public function syncAllUsersToDevice()
    {
        $karyawanList = Karyawan::where('status', 'Aktif')
            ->whereNotNull('pin_fingerprint')
            ->get();

        $results = [];
        $errors = [];

        foreach ($karyawanList as $karyawan) {
            try {
                $result = $this->syncUserToDevice($karyawan);
                $results[] = [
                    'karyawan_id' => $karyawan->karyawan_id,
                    'nama' => $karyawan->nama_lengkap,
                    'pin' => $karyawan->pin_fingerprint,
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'karyawan_id' => $karyawan->karyawan_id,
                    'nama' => $karyawan->nama_lengkap,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success_count' => count($results),
            'error_count' => count($errors),
            'results' => $results,
            'errors' => $errors
        ];
    }

    /**
     * Import attendance log dan simpan ke database
     */
    public function importAndSaveAttlog($startDate, $endDate)
    {
        $attlogData = $this->getAttlog($startDate, $endDate);
        $imported = 0;
        $errors = [];

        foreach ($attlogData as $log) {
            try {
                // Check if already exists
                $exists = AttendanceLog::where('device_sn', $log['device_sn'] ?? '')
                    ->where('pin', $log['pin'])
                    ->where('scan_time', $log['scan_time'])
                    ->exists();

                if (!$exists) {
                    AttendanceLog::create([
                        'device_sn' => $log['device_sn'] ?? 'unknown',
                        'pin' => $log['pin'],
                        'scan_time' => Carbon::parse($log['scan_time']),
                        'verify_mode' => $log['verify_mode'] ?? 1,
                        'inout_mode' => $log['inout_mode'] ?? 1,
                        'device_ip' => $log['device_ip'] ?? $this->deviceIp,
                        'is_processed' => false
                    ]);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Error importing log: " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Dummy data untuk development/testing
     */
    private function getDummyAttlogData($startDate, $endDate)
    {
        return [
            [
                'device_sn' => '616272019373447',
                'pin' => '16746',
                'scan_time' => '2025-09-11 07:59:01',
                'verify_mode' => 1,
                'inout_mode' => 1,
                'device_ip' => $this->deviceIp
            ],
            [
                'device_sn' => '616272019373447',
                'pin' => '16746',
                'scan_time' => '2025-09-11 17:05:30',
                'verify_mode' => 1,
                'inout_mode' => 2,
                'device_ip' => $this->deviceIp
            ],
            [
                'device_sn' => '616272019373447',
                'pin' => '193164',
                'scan_time' => '2025-09-11 08:15:22',
                'verify_mode' => 1,
                'inout_mode' => 1,
                'device_ip' => $this->deviceIp
            ]
        ];
    }

    /**
     * Set timezone mesin
     */
    public function setDeviceTime()
    {
        try {
            $currentTime = now()->format('Y-m-d H:i:s');
            
            $response = Http::timeout(30)->post("{$this->apiUrl}/api/settime", [
                'datetime' => $currentTime
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error setting device time", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Restart mesin fingerprint
     */
    public function restartDevice()
    {
        try {
            $response = Http::timeout(30)->post("{$this->apiUrl}/api/restart");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error restarting device", ['error' => $e->getMessage()]);
            return false;
        }
    }
}