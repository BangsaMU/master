<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MasterItemSyncService
{
    /**
     * Columns that are cloned from master_item_code in db_master to local database.
     */
    public const ALLOWED_COLUMNS = [
        'id',
        'item_code',
        'item_name',
        'uom_id',
        'pca_id',
        'category_id',
        'group_id',
        'remarks',
        'app_code',
        'attributes',
        'nav_code',
        'company_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns to update when an upsert conflict on primary key (id) occurs.
     */
    public const UPDATE_COLUMNS = [
        'item_code',
        'item_name',
        'uom_id',
        'pca_id',
        'category_id',
        'group_id',
        'remarks',
        'app_code',
        'attributes',
        'nav_code',
        'company_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Synchronize items up to a specified maximum ID with memory-safe chunking.
     *
     * Rules:
     * - If targetMaxId > localMaxId: Sync all items from (localMaxId + 1) up to targetMaxId in chunks.
     * - If targetMaxId <= localMaxId: Update the specific target item via upsert.
     *
     * @param int $targetMaxId The maximum ID reported by master-data.
     * @param int $chunkSize Number of records to process per batch (default 250).
     * @return array<string, mixed> Detailed sync report.
     */
    public function syncToMaxId(int $targetMaxId, int $chunkSize = 250): array
    {
        if (function_exists('is_master_db_same_as_default') && is_master_db_same_as_default()) {
            return [
                'success' => true,
                'message' => 'Master database and local database are identical. Sync skipped.',
                'synced_count' => 0,
            ];
        }

        $localMaxId = (int) (DB::table('master_item_code')->max('id') ?? 0);

        if ($targetMaxId > $localMaxId) {
            return $this->syncRange($localMaxId, $targetMaxId, $chunkSize);
        }

        return $this->syncSingleItem($targetMaxId);
    }

    /**
     * Synchronize a specific ID range from db_master to local database using chunkById.
     *
     * @param int $fromId Exclusive starting ID (e.g. current local max ID).
     * @param int $toId Inclusive ending ID (target max ID).
     * @param int $chunkSize
     * @return array<string, mixed>
     */
    public function syncRange(int $fromId, int $toId, int $chunkSize = 250): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $syncedCount = 0;
        $batches = 0;

        try {
            $query = DB::connection('db_master')
                ->table('master_item_code')
                ->where('id', '>', $fromId)
                ->where('id', '<=', $toId)
                ->orderBy('id');

            $query->chunkById($chunkSize, function ($rows) use (&$syncedCount, &$batches) {
                $batchData = [];

                foreach ($rows as $row) {
                    $cleanedRow = $this->filterColumns((array) $row);
                    if (! empty($cleanedRow)) {
                        $batchData[] = $cleanedRow;
                    }
                }

                if (! empty($batchData)) {
                    DB::table('master_item_code')->upsert(
                        $batchData,
                        ['id'],
                        self::UPDATE_COLUMNS
                    );

                    $syncedCount += count($batchData);
                    $batches++;
                }
            }, 'id');

            $currentLocalMax = (int) (DB::table('master_item_code')->max('id') ?? 0);
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            $memoryUsedMb = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);

            $result = [
                'success' => true,
                'action' => 'synced_range',
                'target_max_id' => $toId,
                'previous_local_max_id' => $fromId,
                'current_local_max_id' => $currentLocalMax,
                'synced_count' => $syncedCount,
                'batches_processed' => $batches,
                'chunk_size' => $chunkSize,
                'execution_time_ms' => $executionTimeMs,
                'memory_used_mb' => $memoryUsedMb,
            ];

            Log::info('[MasterItemSyncService] Range sync completed', $result);

            return $result;

        } catch (Throwable $e) {
            Log::error('[MasterItemSyncService] Range sync failed: ' . $e->getMessage(), [
                'from_id' => $fromId,
                'to_id' => $toId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to synchronize items: ' . $e->getMessage(),
                'target_max_id' => $toId,
                'previous_local_max_id' => $fromId,
                'synced_count' => $syncedCount,
            ];
        }
    }

    /**
     * Process sync triggered from a broadcast event payload.
     *
     * Guarantee:
     * 1. If an existing item was updated (or action == updated), that item is ALWAYS updated directly.
     * 2. If new items exist (targetMax > localMax), range sync is executed with chunking.
     *
     * @param array<string, mixed> $payload Broadcast event data.
     * @param int $chunkSize Batch size for chunking.
     * @return array<string, mixed>
     */
    public function syncFromBroadcast(array $payload, int $chunkSize = 250): array
    {
        $itemId = (int) ($payload['id'] ?? $payload['item_id'] ?? 0);
        $action = (string) ($payload['action'] ?? '');
        $maxId = (int) ($payload['max_id'] ?? $payload['target_max_id'] ?? 0);

        if ($itemId <= 0 && $maxId <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid broadcast payload: missing item ID or max ID.',
            ];
        }

        $localMaxId = (int) (DB::table('master_item_code')->max('id') ?? 0);
        $syncedCount = 0;
        $actionsTaken = [];

        // 1. If an item was modified or is an existing record, ALWAYS update its clone directly
        if ($itemId > 0) {
            $singleRes = $this->syncSingleItem($itemId);
            if ($singleRes['success'] ?? false) {
                $syncedCount += ($singleRes['synced_count'] ?? 1);
                $actionsTaken[] = "updated_item_{$itemId}";
            }
        }

        // 2. If there are new items beyond localMaxId, sync range in chunks
        $targetMax = max($itemId, $maxId);
        if ($targetMax > $localMaxId) {
            $rangeRes = $this->syncRange($localMaxId, $targetMax, $chunkSize);
            if ($rangeRes['success'] ?? false) {
                $syncedCount += ($rangeRes['synced_count'] ?? 0);
                $actionsTaken[] = "synced_range_{$localMaxId}_to_{$targetMax}";
            }
        }

        $currentLocalMax = (int) (DB::table('master_item_code')->max('id') ?? 0);

        return [
            'success' => true,
            'action' => ! empty($actionsTaken) ? implode(', ', $actionsTaken) : 'synced',
            'item_id' => $itemId,
            'target_max_id' => $targetMax,
            'previous_local_max_id' => $localMaxId,
            'current_local_max_id' => $currentLocalMax,
            'synced_count' => $syncedCount,
        ];
    }

    /**
     * Synchronize a specific single item by its ID.
     *
     * @param int $itemId
     * @return array<string, mixed>
     */
    public function syncSingleItem(int $itemId): array
    {
        try {
            $row = DB::connection('db_master')
                ->table('master_item_code')
                ->where('id', $itemId)
                ->first();

            if (! $row) {
                return [
                    'success' => false,
                    'message' => "Item with ID {$itemId} not found in master database.",
                ];
            }

            $cleaned = $this->filterColumns((array) $row);

            DB::table('master_item_code')->upsert(
                [$cleaned],
                ['id'],
                self::UPDATE_COLUMNS
            );

            return [
                'success' => true,
                'action' => 'updated_single_item',
                'item_id' => $itemId,
                'synced_count' => 1,
            ];
        } catch (Throwable $e) {
            Log::error("[MasterItemSyncService] Single item sync failed for ID {$itemId}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'item_id' => $itemId,
            ];
        }
    }

    /**
     * Filter array to keep only allowed columns that exist in the local master_item_code table.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterColumns(array $data): array
    {
        $filtered = [];

        foreach (self::ALLOWED_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $filtered[$col] = $data[$col];
            }
        }

        return $filtered;
    }
}
