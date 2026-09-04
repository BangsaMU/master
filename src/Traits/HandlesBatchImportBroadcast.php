<?php

declare(strict_types=1);

namespace Bangsamu\Master\Traits;

use Bangsamu\Master\Services\MasterBroadcastService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Throwable;

trait HandlesBatchImportBroadcast
{
    /**
     * Track if single batch summary broadcast has been sent.
     */
    protected bool $summaryBroadcastSent = false;

    /**
     * Initial max id before import starts.
     */
    protected int $startingMaxId = 0;

    /**
     * Get the master database table name being imported.
     */
    abstract public function getImportTable(): string;

    /**
     * Register Maatwebsite Excel lifecycle events.
     *
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                MasterBroadcastService::disableBroadcasting();
                try {
                    $this->startingMaxId = (int) (DB::table($this->getImportTable())->max('id') ?? 0);
                } catch (Throwable $e) {
                    $this->startingMaxId = 0;
                }
            },
            AfterImport::class => function (AfterImport $event) {
                MasterBroadcastService::enableBroadcasting();
                $this->sendBatchBroadcastSummary();
            },
        ];
    }

    /**
     * Send single batch broadcast summary to Senada (idempotent).
     *
     * @return array<string, mixed>|null
     */
    public function sendBatchBroadcastSummary(): ?array
    {
        if ($this->summaryBroadcastSent) {
            return null;
        }
        $this->summaryBroadcastSent = true;

        // Ensure broadcasting is re-enabled
        MasterBroadcastService::enableBroadcasting();

        $table = $this->getImportTable();
        $newMaxId = 0;
        try {
            $newMaxId = (int) (DB::table($table)->max('id') ?? 0);
        } catch (Throwable $e) {
            $newMaxId = 0;
        }

        $successCount = 0;
        if (method_exists($this, 'getSuccess')) {
            $success = $this->getSuccess();
            $successCount = is_array($success) ? count($success) : 0;
        } elseif (isset($this->success) && is_array($this->success)) {
            $successCount = count($this->success);
        }

        return MasterBroadcastService::broadcastBatchSummary(
            $table,
            $newMaxId,
            $successCount,
            'created'
        );
    }
}
