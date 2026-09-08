<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MasterBroadcastService
{
    /**
     * Cache broadcasted events in the current request to prevent duplicates.
     */
    protected static array $broadcastedInRequest = [];

    /**
     * Global flag to disable broadcasting (e.g. during batch imports or seeders).
     */
    public static bool $isBroadcastingDisabled = false;

    public static function disableBroadcasting(): void
    {
        self::$isBroadcastingDisabled = true;
    }

    public static function enableBroadcasting(): void
    {
        self::$isBroadcastingDisabled = false;
    }

    public static function isBroadcastingDisabled(): bool
    {
        return self::$isBroadcastingDisabled;
    }

    /**
     * Execute a callback with broadcasting temporarily suppressed.
     *
     * @param callable $callback
     * @return mixed
     */
    public static function withoutBroadcasting(callable $callback): mixed
    {
        $previous = self::$isBroadcastingDisabled;
        self::$isBroadcastingDisabled = true;

        try {
            return $callback();
        } finally {
            self::$isBroadcastingDisabled = $previous;
        }
    }

    /**
     * Broadcast an item creation or update event to Senada Reverb Hub.
     *
     * @param Model|array<string, mixed>|object $item
     * @param string $action 'created'|'updated'|'deleted'
     * @return array<string, mixed>
     */
    public function broadcastItemChange($item, string $action = 'updated'): array
    {
        return $this->broadcastTableChange('master_item_code', $item, $action);
    }

    /**
     * Broadcast a generic entity change from any master table to Senada Reverb Hub.
     *
     * @param string $table e.g. 'master_company', 'master_project', 'master_employee'
     * @param Model|array<string, mixed>|object $model
     * @param string $action 'created'|'updated'|'deleted'
     * @param string $channel
     * @param string $event
     * @return array<string, mixed>
     */
    public function broadcastChange(
        string $table,
        $model,
        string $action = 'updated',
        string $channel = 'masterdata.items',
        string $event = 'MasterItemUpdated'
    ): array {
        $id = is_object($model) ? (int) ($model->id ?? 0) : (int) ($model['id'] ?? 0);

        $maxId = $id;
        try {
            $tableMax = DB::table($table)->max('id');
            if ($tableMax) {
                $maxId = (int) $tableMax;
            }
        } catch (Throwable $e) {
            $maxId = $id;
        }

        $payload = [
            'table' => $table,
            'action' => $action,
            'id' => $id,
            'max_id' => $maxId,
            'timestamp' => now()->toIso8601String(),
        ];

        if (is_object($model) && method_exists($model, 'toArray')) {
            $payload['data'] = $model->toArray();
        } elseif (is_array($model)) {
            $payload['data'] = $model;
        }

        return $this->dispatchToSenada(
            channel: $channel,
            event: $event,
            payload: $payload,
            isPrivate: false
        );
    }

    /**
     * Broadcast a table modification event for any of the 15 master tables.
     *
     * @param string $table
     * @param Model|array<string, mixed>|object $model
     * @param string $action 'created'|'updated'|'deleted'
     * @return array<string, mixed>
     */
    public function broadcastTableChange(string $table, $model, string $action = 'updated'): array
    {
        $id = is_object($model) ? (int) ($model->id ?? 0) : (int) ($model['id'] ?? 0);

        // Deduplicate events in the same HTTP lifecycle
        $dedupKey = "{$table}:{$id}:{$action}";
        if ($id > 0 && isset(self::$broadcastedInRequest[$dedupKey])) {
            return [
                'success' => true,
                'message' => 'Duplicate broadcast in same request skipped.',
            ];
        }
        if ($id > 0) {
            self::$broadcastedInRequest[$dedupKey] = true;
        }

        // Determine human-readable identifier for notifications (e.g. no_ktp for employee per user request)
        $identifier = $this->resolveEntityIdentifier($table, $model, $id);

        $maxId = $id;
        try {
            $tableMax = DB::table($table)->max('id');
            if ($tableMax) {
                $maxId = (int) $tableMax;
            }
        } catch (Throwable $e) {
            $maxId = $id;
        }

        $tableConfig = MasterDataSyncService::TABLES[$table] ?? null;
        $label = $tableConfig['label'] ?? ucwords(str_replace(['master_', '_'], ['', ' '], $table));

        $payload = [
            'table' => $table,
            'label' => $label,
            'action' => $action,
            'id' => $id,
            'max_id' => $maxId,
            'identifier' => $identifier,
            'timestamp' => now()->toIso8601String(),
        ];

        // If it's master_item_code, retain item_code for backward compatibility
        if ($table === 'master_item_code') {
            $payload['item_code'] = $identifier;
        }

        $channel = config('MasterConfig.senada.channel', env('SENADA_CHANNEL_MASTER_ITEMS', 'masterdata.items'));

        // Dispatch single MasterDataUpdated event
        return $this->dispatchToSenada($channel, 'MasterDataUpdated', $payload, false);
    }

    /**
     * Resolve human-readable identifier from model attributes.
     */
    protected function resolveEntityIdentifier(string $table, $model, int $id): string
    {
        $get = function (string $attr) use ($model) {
            if (is_object($model)) {
                return $model->$attr ?? null;
            }
            return $model[$attr] ?? null;
        };

        return match ($table) {
            'master_employee' => (string) ($get('no_ktp') ?: $get('employee_name') ?: "ID #{$id}"),
            'master_item_code' => (string) ($get('item_code') ?: "ID #{$id}"),
            'master_category' => (string) ($get('category_code') ?: $get('category_name') ?: "ID #{$id}"),
            'master_company' => (string) ($get('company_code') ?: $get('company_name') ?: "ID #{$id}"),
            'master_department' => (string) ($get('department_code') ?: $get('department_name') ?: "ID #{$id}"),
            'master_item_group' => (string) ($get('item_group_code') ?: $get('item_group_name') ?: "ID #{$id}"),
            'master_job_position' => (string) ($get('position_code') ?: $get('position_name') ?: "ID #{$id}"),
            'master_location' => (string) ($get('loc_code') ?: $get('loc_name') ?: "ID #{$id}"),
            'master_pca' => (string) ($get('pca_code') ?: $get('pca_name') ?: "ID #{$id}"),
            'master_project' => (string) ($get('project_code') ?: $get('project_name') ?: "ID #{$id}"),
            'master_project_detail' => (string) ($get('project_code_client') ?: $get('project_name_client') ?: "ID #{$id}"),
            'master_status' => (string) ($get('kode') ?: $get('status') ?: "ID #{$id}"),
            'master_uom' => (string) ($get('uom_code') ?: $get('uom_name') ?: "ID #{$id}"),
            'master_vendor' => (string) ($get('vendor_code') ?: $get('vendor_description') ?: "ID #{$id}"),
            'master_vendor_contact' => (string) ($get('vendor_contact_name') ?: "ID #{$id}"),
            default => "ID #{$id}",
        };
    }

    /**
     * Static helper for quick broadcasting of any table.
     */
    public static function broadcastTable(string $table, $model, string $action = 'updated'): array
    {
        return app(self::class)->broadcastTableChange($table, $model, $action);
    }

    /**
     * Send HTTP POST broadcast request to Senada Hub.
     *
     * @param string $channel
     * @param string $event
     * @param array<string, mixed> $payload
     * @param bool $isPrivate
     * @return array<string, mixed>
     */
    public function dispatchToSenada(
        string $channel,
        string $event,
        array $payload,
        bool $isPrivate = false
    ): array {
        $active = config('MasterConfig.senada.active', env('SENADA_BROADCAST_ACTIVE', true));
        if (! $active) {
            return [
                'success' => false,
                'message' => 'Senada broadcast is disabled by configuration.',
                'payload' => $payload,
            ];
        }

        $senadaUrl = rtrim(
            config('MasterConfig.senada.url', env('SENADA_URL', 'http://192.168.20.187:9029')),
            '/'
        );
        $apiKey = config('MasterConfig.senada.api_key', env('SENADA_API_KEY', 'snd_masterdata_key_secret'));
        $timeout = (int) config('MasterConfig.senada.timeout', env('SENADA_TIMEOUT', 3));
        $channelType = $isPrivate ? 'private' : 'public';
        $targetChannel = $channel;
        if (str_starts_with($targetChannel, 'masterdata.')) {
            $targetChannel = substr($targetChannel, strlen('masterdata.'));
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$senadaUrl}/api/broadcast", [
                    'channel' => $targetChannel,
                    'channel_type' => $channelType,
                    'event' => $event,
                    'payload' => $payload,
                ]);

            if ($response->successful()) {
                Log::info("[MasterBroadcastService] Broadcast dispatched to Senada: {$channel} -> {$event}", [
                    'status' => $response->status(),
                    'payload' => $payload,
                ]);

                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'data' => $response->json(),
                    'payload' => $payload,
                ];
            }

            Log::warning("[MasterBroadcastService] Senada returned non-200: {$response->status()}", [
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'status_code' => $response->status(),
                'message' => $response->body(),
                'payload' => $payload,
            ];
        } catch (Throwable $e) {
            Log::warning('[MasterBroadcastService] Broadcast failed: ' . $e->getMessage(), [
                'senada_url' => $senadaUrl,
                'channel' => $channel,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'payload' => $payload,
            ];
        }
    }

    /**
     * Broadcast a single batch summary event to Senada after a bulk operation (like Excel import).
     *
     * @param string $table e.g. 'master_item_code'
     * @param int $maxId Highest record ID after import
     * @param int $batchCount Total records processed/imported
     * @param string $action e.g. 'created' or 'imported'
     * @return array<string, mixed>
     */
    public static function broadcastBatchSummary(
        string $table,
        int $maxId = 0,
        int $batchCount = 0,
        string $action = 'created'
    ): array {
        if ($maxId <= 0) {
            try {
                $maxId = (int) (DB::table($table)->max('id') ?? 0);
            } catch (Throwable $e) {
                $maxId = 0;
            }
        }

        $tableConfig = MasterDataSyncService::TABLES[$table] ?? null;
        $label = $tableConfig['label'] ?? ucwords(str_replace(['master_', '_'], ['', ' '], $table));
        $formattedCount = number_format($batchCount, 0, ',', '.');
        $identifier = "Batch Import ({$formattedCount} data)";

        $payload = [
            'table' => $table,
            'label' => $label,
            'action' => $action,
            'id' => $maxId,
            'max_id' => $maxId,
            'batch_count' => $batchCount,
            'is_batch' => true,
            'identifier' => $identifier,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($table === 'master_item_code') {
            $payload['item_code'] = $identifier;
        }

        $channel = config('MasterConfig.senada.channel', env('SENADA_CHANNEL_MASTER_ITEMS', 'masterdata.items'));

        return app(self::class)->dispatchToSenada($channel, 'MasterDataUpdated', $payload, false);
    }

    /**
     * Static helper for quick broadcast dispatching.
     *
     * @param Model|array<string, mixed>|object $item
     * @param string $action
     * @return array<string, mixed>
     */
    public static function broadcastItem($item, string $action = 'updated'): array
    {
        return app(self::class)->broadcastItemChange($item, $action);
    }
}

