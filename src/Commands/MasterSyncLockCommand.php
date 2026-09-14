<?php

declare(strict_types=1);

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterDataSyncService;
use Illuminate\Console\Command;

class MasterSyncLockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master:sync-lock 
                            {--status : Check current master sync lock status on Senada Hub}
                            {--release : Force release the master sync lock on Senada Hub}
                            {--acquire : Manually acquire the lock for testing/maintenance}
                            {--ttl=300 : TTL in seconds when acquiring lock}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect or manage the Central Master Sync Lock on Senada Hub';

    /**
     * Execute the console command.
     */
    public function handle(MasterDataSyncService $syncService): int
    {
        if ($this->option('release')) {
            $this->warn('Releasing Central Master Sync Lock on Senada Hub...');
            $result = $syncService->forceReleaseMasterSyncLock();

            if ($result['released'] ?? false) {
                $this->info('✓ '.($result['message'] ?? 'Master sync lock released successfully.'));
            } else {
                $this->error('Failed to release lock: '.($result['message'] ?? 'Unknown error'));
            }

            return self::SUCCESS;
        }

        if ($this->option('acquire')) {
            $ttl = (int) $this->option('ttl');
            $this->line("Acquiring Master Sync Lock for {$ttl} seconds...");
            $result = $syncService->acquireMasterSyncLock($ttl, 'manual_cli_lock', true);

            if ($result['acquired'] ?? false) {
                $this->info("✓ Lock acquired successfully by '{$result['holder']}'. Token: {$result['lock_token']}");
                $this->line("  Expires at: {$result['expires_at']}");
            } else {
                $this->warn("⚠ Lock active: held by '{$result['holder']}'. TTL remaining: {$result['ttl_remaining_seconds']}s.");
            }

            return self::SUCCESS;
        }

        // Default: Show status
        $this->line('Checking Central Master Sync Lock status on Senada Hub...');
        $status = $syncService->getMasterSyncLockStatus();

        $isLocked = (bool) ($status['is_locked'] ?? false);

        if ($isLocked) {
            $this->warn('● STATUS: LOCKED');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Holder App', $status['holder'] ?? 'unknown'],
                    ['Reason', $status['reason'] ?? '-'],
                    ['Locked At', $status['locked_at'] ?? '-'],
                    ['Expires At', $status['expires_at'] ?? '-'],
                    ['TTL Remaining', ($status['ttl_remaining_seconds'] ?? 0).' seconds'],
                ]
            );
            $this->line("Run 'php artisan master:sync-lock --release' to force release if the lock is stuck.");
        } else {
            $this->info('● STATUS: FREE (No active sync lock on Master DB)');
            if (! empty($status['message'])) {
                $this->line("  {$status['message']}");
            }
        }

        return self::SUCCESS;
    }
}
