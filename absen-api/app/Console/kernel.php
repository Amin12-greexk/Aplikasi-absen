<?php
// app/Console/Kernel.php (UPDATE - Tambah scheduled tasks)

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SyncFingerprintData::class,
        Commands\RecalculateGajiTambahan::class,
        Commands\SyncUsersToFingerprint::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Sync fingerprint data setiap 30 menit pada jam kerja
        $schedule->command('fingerprint:sync --process')
            ->everyThirtyMinutes()
            ->between('06:00', '19:00')
            ->weekdays()
            ->appendOutputTo(storage_path('logs/fingerprint-sync.log'));

        // Sync data weekend (lebih jarang)
        $schedule->command('fingerprint:sync --process')
            ->hourly()
            ->weekends()
            ->appendOutputTo(storage_path('logs/fingerprint-sync.log'));

        // Recalculate gaji tambahan setiap akhir bulan
        $schedule->command('gaji:recalculate ' . now()->subMonth()->format('Y-m') . ' --force')
            ->monthlyOn(1, '02:00')
            ->appendOutputTo(storage_path('logs/gaji-recalculate.log'));
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}