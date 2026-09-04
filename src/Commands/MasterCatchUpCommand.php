<?php

declare(strict_types=1);

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterDataSyncService;
use Illuminate\Console\Command;

class MasterCatchUpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master:catch-up 
                            {--since= : Specific broadcast ID to start catching up from}
                            {--limit=100 : Maximum events to fetch and sync per cycle}
                            {--info : Display the current sync checkpoint without syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize missed master data broadcast events from Senada Hub (FCM-Like Catch-Up)';

    /**
     * Execute the console command.
     */
    public function handle(MasterDataSyncService $syncService): int
    {
        $currentCheckpoint = MasterDataSyncService::getLastSyncedBroadcastId();

        if ($this->option('info')) {
            $this->info("Current Senada Broadcast Checkpoint: ID #{$currentCheckpoint}");
            return self::SUCCESS;
        }

        $sinceId = $this->option('since') !== null ? (int) $this->option('since') : null;
        $limit = (int) $this->option('limit');

        $this->line("Fetching missed master data events from Senada (Current Checkpoint: #" . ($sinceId ?? $currentCheckpoint) . ")...");

        $result = $syncService->catchUpMissedBroadcasts($sinceId, $limit);

        if (! ($result['success'] ?? false)) {
            $this->error("Catch-up failed: " . ($result['message'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $syncedCount = (int) ($result['synced_count'] ?? 0);
        $newCheckpoint = (int) ($result['new_checkpoint'] ?? $currentCheckpoint);

        if ($syncedCount === 0) {
            $this->info("✓ Everything is up to date. No missed broadcast events (Checkpoint: #{$newCheckpoint}).");
            return self::SUCCESS;
        }

        $this->info("✓ Successfully synced {$syncedCount} missed master data event(s). Checkpoint updated to #{$newCheckpoint}.");

        $rows = [];
        foreach ($result['events_processed'] ?? [] as $item) {
            $rows[] = [
                $item['event_id'],
                $item['table'],
                $item['action'],
                ($item['result']['success'] ?? false) ? 'OK' : 'FAIL',
            ];
        }

        if (! empty($rows)) {
            $this->table(['Event ID', 'Table', 'Action', 'Status'], $rows);
        }

        if ($result['has_more'] ?? false) {
            $this->warn("More events are pending on Senada. Run 'php artisan master:catch-up' again to continue.");
        }

        return self::SUCCESS;
    }
}
