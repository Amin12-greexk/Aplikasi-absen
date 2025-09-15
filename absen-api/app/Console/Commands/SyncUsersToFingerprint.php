<?php
// app/Console/Commands/SyncUsersToFingerprint.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FingerspotService;
use App\Models\Karyawan;

class SyncUsersToFingerprint extends Command
{
    protected $signature = 'fingerprint:sync-users {--karyawan=} {--force}';
    protected $description = 'Sync karyawan data to fingerprint device';

    protected $fingerspotService;

    public function __construct(FingerspotService $fingerspotService)
    {
        parent::__construct();
        $this->fingerspotService = $fingerspotService;
    }

    public function handle()
    {
        $karyawanId = $this->option('karyawan');
        $force = $this->option('force');

        $this->info('🔄 Syncing karyawan data to fingerprint device...');

        if ($karyawanId) {
            $karyawan = Karyawan::find($karyawanId);
            if (!$karyawan) {
                $this->error("❌ Karyawan with ID {$karyawanId} not found");
                return 1;
            }

            if (!$karyawan->pin_fingerprint) {
                $this->error("❌ Karyawan {$karyawan->nama_lengkap} doesn't have PIN fingerprint");
                return 1;
            }

            try {
                $result = $this->fingerspotService->syncUserToDevice($karyawan);
                $this->info("✅ Synced: {$karyawan->nama_lengkap} (PIN: {$karyawan->pin_fingerprint})");
                return 0;
            } catch (\Exception $e) {
                $this->error("❌ Failed to sync {$karyawan->nama_lengkap}: " . $e->getMessage());
                return 1;
            }
        }

        // Sync all users
        if (!$force && !$this->confirm('⚠️  This will sync all active karyawan to fingerprint device. Continue?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            $result = $this->fingerspotService->syncAllUsersToDevice();

            $this->info("✅ Sync completed!");
            $this->info("📊 Successfully synced: {$result['success_count']} users");
            
            if ($result['error_count'] > 0) {
                $this->warn("⚠️  Failed to sync: {$result['error_count']} users");
                
                if ($this->option('verbose')) {
                    foreach ($result['errors'] as $error) {
                        $this->line("   - {$error['nama']}: {$error['error']}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            return 1;
        }
        
     $this->info('🎉 Fingerprint sync completed successfully!');
        return 0;
    }
}