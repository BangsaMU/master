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
                            {--force-lock : Force acquire lock even if another lock is active or stuck}
                            {--info : Display the current sync checkpoint without syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize missed master data broadcast events from Senada Hub (Protected by Master Sync Lock)';

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
        $forceLock = (bool) $this->option('force-lock');

        $this->line('Fetching missed master data events from Senada (Current Checkpoint: #'.($sinceId ?? $currentCheckpoint).')...');

        $result = $syncService->catchUpMissedBroadcasts($sinceId, $limit, $forceLock);

        if (! ($result['success'] ?? false)) {
            $this->error('Catch-up failed: '.($result['message'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        // Handle Master Lock Postponement
        if ($result['locked'] ?? false) {
            $holder = $result['holder'] ?? 'another_app';
            $ttlRemaining = (int) ($result['ttl_remaining'] ?? 0);

            $this->warn("⚠ [LOCK ACTIVE] Master sync is currently in progress by '{$holder}' (TTL remaining: {$ttlRemaining}s).");
            $this->line('  Synchronization cycle postponed to prevent connection contention on Master DB.');
            $this->line("  Use 'php artisan master:catch-up --force-lock' or 'php artisan master:sync-lock --release' if lock is stuck.");

            return self::SUCCESS;
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
