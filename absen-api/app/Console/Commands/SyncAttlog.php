<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FingerspotService;
use App\Models\Absensi;
use App\Models\Karyawan;
use Carbon\Carbon;

class SyncAttlog extends Command
{
    protected $signature = 'sync:attlog {--date=}';
    protected $description = 'Sinkronisasi data absensi dari mesin Fingerspot untuk tanggal tertentu (default: kemarin)';
    
    protected $fingerspotService;

    public function __construct(FingerspotService $fingerspotService)
    {
        parent::__construct();
        $this->fingerspotService = $fingerspotService;
    }

    public function handle()
    {
        $targetDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $this->info("Memulai sinkronisasi data absensi untuk tanggal: " . $targetDate->format('Y-m-d'));

        // Panggil service untuk mengambil data dari API
        $logs = $this->fingerspotService->getAttlog($targetDate->format('Y-m-d'), $targetDate->format('Y-m-d'));

        if (empty($logs)) {
            $this->warn("Tidak ada data absensi ditemukan untuk tanggal tersebut.");
            return 0;
        }

        // Kelompokkan log berdasarkan PIN (NIK) karyawan
        $scansByEmployee = [];
        foreach ($logs as $log) {
            $scansByEmployee[$log['pin']][] = $log['scan_date'];
        }

        foreach ($scansByEmployee as $nik => $scans) {
            $karyawan = Karyawan::where('nik', $nik)->first();
            if (!$karyawan) {
                $this->error("Karyawan dengan NIK {$nik} tidak ditemukan. Log dilewati.");
                continue;
            }

            sort($scans); // Urutkan dari yang paling pagi
            $scanMasuk = $scans[0] ?? null;
            $scanPulang = count($scans) > 1 ? end($scans) : null;

            // Simpan atau update data absensi
            Absensi::updateOrCreate(
                [
                    'karyawan_id' => $karyawan->karyawan_id,
                    'tanggal_absensi' => $targetDate->format('Y-m-d'),
                ],
                [
                    'jam_scan_masuk' => $scanMasuk,
                    'jam_scan_pulang' => $scanPulang,
                    'status' => 'Hadir' // Status awal, bisa diupdate oleh observer
                ]
            );
             $this->info("Data absensi untuk NIK {$nik} berhasil disinkronkan.");
        }

        $this->info("Sinkronisasi selesai.");
        return 0;
    }
}
