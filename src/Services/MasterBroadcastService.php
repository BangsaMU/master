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
     * Broadcast an item creation or update event to Senada Reverb Hub.
     *
     * @param Model|array<string, mixed>|object $item
     * @param string $action 'created'|'updated'|'deleted'
     * @return array<string, mixed>
     */
    public function broadcastItemChange($item, string $action = 'updated'): array
    {
        $itemId = is_object($item) ? (int) ($item->id ?? 0) : (int) ($item['id'] ?? 0);
        $itemCode = is_object($item) ? (string) ($item->item_code ?? '') : (string) ($item['item_code'] ?? '');
        $itemName = is_object($item) ? (string) ($item->item_name ?? '') : (string) ($item['item_name'] ?? '');
        $categoryId = is_object($item) ? ($item->category_id ?? null) : ($item['category_id'] ?? null);
        $uomId = is_object($item) ? ($item->uom_id ?? null) : ($item['uom_id'] ?? null);
        $groupId = is_object($item) ? ($item->group_id ?? null) : ($item['group_id'] ?? null);

        // Determine current max ID from master table
        $maxId = $itemId;
        try {
            $tableMax = DB::table('master_item_code')->max('id');
            if ($tableMax) {
                $maxId = (int) $tableMax;
            }
        } catch (Throwable $e) {
            // If table check fails, fallback to itemId
            $maxId = $itemId;
        }

        $payload = [
            'table' => 'master_item_code',
            'entity' => 'item',
            'action' => $action,
            'id' => $itemId,
            'max_id' => $maxId,
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'group_id' => $groupId,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->dispatchToSenada(
            channel: config('MasterConfig.senada.channel', env('SENADA_CHANNEL_MASTER_ITEMS', 'masterdata.items')),
            event: 'MasterItemUpdated',
            payload: $payload,
            isPrivate: false
        );
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
        $apiKey = config('MasterConfig.senada.api_key', env('SENADA_API_KEY', 'snd_masterdata_pilot_key_secret_2026'));
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
