<?php
// app/Console/Commands/SyncFingerprintData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FingerspotService;
use App\Services\FingerprintImportService;
use Carbon\Carbon;

class SyncFingerprintData extends Command
{
    protected $signature = 'fingerprint:sync {--date=} {--days=1} {--process}';
    protected $description = 'Sync attendance data from fingerprint device';

    protected $fingerspotService;
    protected $importService;

    public function __construct(FingerspotService $fingerspotService, FingerprintImportService $importService)
    {
        parent::__construct();
        $this->fingerspotService = $fingerspotService;
        $this->importService = $importService;
    }

    public function handle()
    {
        $this->info('🔄 Starting fingerprint data sync...');

        try {
            // Determine date range
            $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
            $days = (int) $this->option('days');
            
            $startDate = $date->format('Y-m-d');
            $endDate = $date->copy()->addDays($days - 1)->format('Y-m-d');

            $this->info("📅 Syncing data from {$startDate} to {$endDate}");

            // Import from device
            $result = $this->fingerspotService->importAndSaveAttlog($startDate, $endDate);
            
            $this->info("✅ Imported {$result['imported']} attendance logs");
            
            if (!empty($result['errors'])) {
                $this->warn("⚠️  Encountered " . count($result['errors']) . " errors during import");
                foreach ($result['errors'] as $error) {
                    $this->line("   - {$error}");
                }
            }

            // Process logs if requested
            if ($this->option('process')) {
                $this->info('🔄 Processing unprocessed logs...');
                $processResult = $this->importService->processUnprocessedLogs();
                
                $this->info("✅ Processed {$processResult['processed']} logs into attendance records");
                
                if (!empty($processResult['errors'])) {
                    $this->warn("⚠️  Processing errors:");
                    foreach ($processResult['errors'] as $error) {
                        $this->line("   - {$error}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}