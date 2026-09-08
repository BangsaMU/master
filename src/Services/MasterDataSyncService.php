<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MasterDataSyncService
{
    public const SETTING_KEY_ALLOWED_TABLES = 'sync_allowed_master_tables';

    public const SETTING_GROUP_SENADA_SYNC = 'senada_sync';

    /**
     * Supported Master Tables configuration registry.
     */
    public const TABLES = [
        'master_item_code' => [
            'label' => 'Item Code',
            'identifier' => 'item_code',
            'columns' => [
                'id', 'item_code', 'item_name', 'uom_id', 'pca_id', 'category_id',
                'group_id', 'remarks', 'app_code', 'attributes', 'nav_code',
                'company_id', 'created_at', 'updated_at', 'deleted_at',
            ],
        ],
        'master_category' => [
            'label' => 'Kategori',
            'identifier' => 'category_code',
            'columns' => [
                'id', 'category_code', 'category_name', 'remark',
                'created_at', 'updated_at', 'deleted_at', 'app_code',
            ],
        ],
        'master_company' => [
            'label' => 'Perusahaan',
            'identifier' => 'company_code',
            'columns' => [
                'id', 'company_code', 'company_name', 'company_short',
                'company_attention', 'company_address', 'company_logo_id',
                'company_logo', 'created_at', 'updated_at', 'deleted_at', 'template_json',
            ],
        ],
        'master_department' => [
            'label' => 'Departemen',
            'identifier' => 'department_code',
            'columns' => [
                'id', 'department_code', 'department_name',
                'created_at', 'updated_at', 'deleted_at',
            ],
        ],
        'master_employee' => [
            'label' => 'Karyawan',
            'identifier' => 'no_ktp', // Menggunakan no_ktp sebagai kode utama sesuai instruksi user
            'columns' => [
                'id', 'job_position_id', 'employee_name', 'employee_email',
                'employee_department', 'employee_blood_type', 'employee_phone',
                'employee_job_title', 'employee_project', 'no_ktp', 'no_id_karyawan',
                'status_id', 'hire_id', 'tanggal_akhir_kontrak', 'tanggal_join',
                'tanggal_akhir_kerja', 'corporate_email', 'keterangan',
                'work_location_id', 'app_code', 'created_at', 'updated_at',
                'deleted_at', 'employee_dob', 'citizenship', 'country_code',
                'job_list', 'company_id', 'emergency_phone', 'gender', 'project_id',
            ],
        ],
        'master_item_group' => [
            'label' => 'Item Group',
            'identifier' => 'item_group_code',
            'columns' => [
                'id', 'item_group_code', 'item_group_name', 'created_at',
                'updated_at', 'deleted_at', 'app_code', 'item_group_attributes',
            ],
        ],
        'master_job_position' => [
            'label' => 'Jabatan',
            'identifier' => 'position_code',
            'columns' => [
                'id', 'department_id', 'position_code', 'position_name',
                'position_ranking_code', 'created_at', 'updated_at', 'deleted_at',
            ],
        ],
        'master_location' => [
            'label' => 'Lokasi',
            'identifier' => 'loc_code',
            'columns' => [
                'id', 'loc_code', 'loc_name', 'group_type',
                'created_at', 'updated_at', 'deleted_at',
            ],
        ],
        'master_pca' => [
            'label' => 'PCA',
            'identifier' => 'pca_code',
            'columns' => [
                'id', 'pca_code', 'pca_name', 'created_at',
                'updated_at', 'deleted_at', 'app_code',
            ],
        ],
        'master_project' => [
            'label' => 'Project',
            'identifier' => 'project_code',
            'columns' => [
                'id', 'project_code', 'project_name', 'internal_external',
                'project_start_date', 'project_complete_date', 'created_at',
                'updated_at', 'deleted_at', 'project_remarks', 'user_id',
            ],
        ],
        'master_project_detail' => [
            'label' => 'Detail Project',
            'identifier' => 'project_code_client',
            'columns' => [
                'id', 'project_id', 'project_code_client', 'company_id',
                'created_at', 'updated_at', 'deleted_at', 'user_id',
                'project_name_client',
            ],
        ],
        'master_status' => [
            'label' => 'Status',
            'identifier' => 'kode',
            'columns' => [
                'id', 'kode', 'status', 'created_at',
                'updated_at', 'deleted_at', 'app_code',
            ],
        ],
        'master_uom' => [
            'label' => 'UoM',
            'identifier' => 'uom_code',
            'columns' => [
                'id', 'uom_code', 'uom_name', 'created_at',
                'updated_at', 'deleted_at', 'app_code',
            ],
        ],
        'master_vendor' => [
            'label' => 'Vendor',
            'identifier' => 'vendor_code',
            'columns' => [
                'id', 'vendor_code', 'vendor_description', 'vendor_address',
                'vendor_phone', 'vendor_fax', 'vendor_email', 'created_at',
                'updated_at', 'deleted_at', 'loc_id',
            ],
        ],
        'master_vendor_contact' => [
            'label' => 'Kontak Vendor',
            'identifier' => 'vendor_contact_name',
            'columns' => [
                'id', 'vendor_id', 'vendor_contact_name', 'vendor_contact_phone',
                'vendor_contact_email', 'vendor_contact_fax', 'created_at',
                'updated_at', 'deleted_at',
            ],
        ],
    ];

    /**
     * Check if a given table is supported for auto-sync.
     */
    public function isTableSupported(string $table): bool
    {
        return isset(self::TABLES[$table]);
    }

    /**
     * Get table configuration.
     */
    public function getTableConfig(string $table): ?array
    {
        return self::TABLES[$table] ?? null;
    }

    /**
     * Synchronize a master table based on broadcast event payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncFromBroadcast(array $payload, int $chunkSize = 250): array
    {
        $table = (string) ($payload['table'] ?? 'master_item_code');
        $id = (int) ($payload['id'] ?? $payload['item_id'] ?? 0);
        $maxId = (int) ($payload['max_id'] ?? $payload['target_max_id'] ?? $id);
        $action = (string) ($payload['action'] ?? 'updated');

        $result = $this->syncTable($table, $id, $maxId, $action, $chunkSize);

        $broadcastId = (int) ($payload['_broadcast_id'] ?? $payload['broadcast_id'] ?? 0);
        if ($broadcastId > 0) {
            self::setLastSyncedBroadcastId($broadcastId);
            $result['broadcast_id'] = $broadcastId;
        }

        return $result;
    }

    /**
     * Get list of detected local database tables with prefix master_ that are valid master entities.
     *
     * @return array<int, string>
     */
    public static function getAvailableLocalMasterTables(): array
    {
        try {
            $tables = [];

            // Fetch table list safely via SHOW TABLES
            $dbTables = DB::select('SHOW TABLES');
            foreach ($dbTables as $tableObj) {
                $tableArr = (array) $tableObj;
                $tableName = reset($tableArr);
                if (is_string($tableName) && Str::startsWith($tableName, 'master_')) {
                    // Filter out backup/dump/temporary tables
                    $lower = strtolower($tableName);
                    if (
                        Str::endsWith($lower, 'old') ||
                        Str::contains($lower, ['_old', 'dump', 'temp', 'backup', 'picture'])
                    ) {
                        continue;
                    }
                    $tables[] = $tableName;
                }
            }

            // Fallback to checking self::TABLES against Schema if SHOW TABLES returned empty
            if (empty($tables)) {
                foreach (array_keys(self::TABLES) as $supportedTable) {
                    if (Schema::hasTable($supportedTable)) {
                        $tables[] = $supportedTable;
                    }
                }
            }

            sort($tables);

            return array_values(array_unique($tables));
        } catch (Throwable $e) {
            Log::warning('[MasterDataSyncService] Failed to get local master tables: '.$e->getMessage());
            $tables = [];
            foreach (array_keys(self::TABLES) as $supportedTable) {
                try {
                    if (Schema::hasTable($supportedTable)) {
                        $tables[] = $supportedTable;
                    }
                } catch (Throwable $e2) {
                }
            }

            return $tables;
        }
    }

    /**
     * Get the allowed master tables to sync from dashboard_settings.
     * If setting does not exist, automatically initialize it from local database tables.
     *
     * @return array<int, string>
     */
    public static function getAllowedSyncTables(): array
    {
        try {
            self::ensureDashboardSettingsTable();

            $setting = DB::table('dashboard_settings')
                ->where('key', self::SETTING_KEY_ALLOWED_TABLES)
                ->first();

            if ($setting && ! empty($setting->value)) {
                $decoded = json_decode($setting->value, true);
                if (is_array($decoded)) {
                    // Filter only existing tables with master_ prefix
                    return array_values(array_filter($decoded, function ($table) {
                        return is_string($table)
                            && Str::startsWith($table, 'master_')
                            && Schema::hasTable($table);
                    }));
                }
            }

            // Auto-initialize: intersection between available local tables and supported tables
            $available = self::getAvailableLocalMasterTables();
            $defaultAllowed = array_values(array_intersect($available, array_keys(self::TABLES)));
            if (empty($defaultAllowed)) {
                $defaultAllowed = $available;
            }

            self::setAllowedSyncTables($defaultAllowed);

            return $defaultAllowed;
        } catch (Throwable $e) {
            Log::warning('[MasterDataSyncService] Failed to read allowed sync tables: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Save allowed master tables to dashboard_settings.
     *
     * @param  array<int, string>  $tables
     */
    public static function setAllowedSyncTables(array $tables): void
    {
        try {
            self::ensureDashboardSettingsTable();

            $cleanTables = array_values(array_unique(array_filter($tables, function ($tbl) {
                return is_string($tbl) && Str::startsWith($tbl, 'master_');
            })));

            $available = self::getAvailableLocalMasterTables();

            $exists = DB::table('dashboard_settings')->where('key', self::SETTING_KEY_ALLOWED_TABLES)->exists();
            if ($exists) {
                DB::table('dashboard_settings')
                    ->where('key', self::SETTING_KEY_ALLOWED_TABLES)
                    ->update([
                        'value' => json_encode($cleanTables),
                        'options' => json_encode($available),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('dashboard_settings')->insert([
                    'key' => self::SETTING_KEY_ALLOWED_TABLES,
                    'value' => json_encode($cleanTables),
                    'group' => self::SETTING_GROUP_SENADA_SYNC,
                    'type' => 'json',
                    'label' => 'Allowed Master Data Sync Tables',
                    'options' => json_encode($available),
                    'order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[MasterDataSyncService] Failed to save allowed sync tables: '.$e->getMessage());
        }
    }

    /**
     * Check if a given table is allowed to be synchronized.
     * Must have prefix 'master_', exist in local DB schema, and be in dashboard_settings whitelist.
     */
    public function isTableSyncAllowed(string $table): bool
    {
        if (! Str::startsWith($table, 'master_')) {
            return false;
        }

        if (! Schema::hasTable($table)) {
            return false;
        }

        $allowed = self::getAllowedSyncTables();

        return in_array($table, $allowed, true);
    }

    /**
     * Core universal synchronization method for any supported master table.
     *
     * @return array<string, mixed>
     */
    public function syncTable(
        string $table,
        ?int $targetId = null,
        ?int $targetMaxId = null,
        string $action = 'updated',
        int $chunkSize = 250
    ): array {
        if (! $this->isTableSyncAllowed($table)) {
            return [
                'success' => true,
                'skipped' => true,
                'table' => $table,
                'message' => "Table '{$table}' is not allowed or does not exist in local database. Sync skipped.",
                'synced_count' => 0,
            ];
        }

        if (function_exists('is_master_db_same_as_default') && is_master_db_same_as_default()) {
            return [
                'success' => true,
                'skipped' => true,
                'table' => $table,
                'message' => 'Master database and local database are identical. Sync skipped.',
                'synced_count' => 0,
            ];
        }

        if (! $this->isTableSupported($table)) {
            return [
                'success' => true,
                'skipped' => true,
                'table' => $table,
                'message' => "Table '{$table}' is not registered in MasterDataSyncService.",
                'synced_count' => 0,
            ];
        }

        if (! Schema::hasTable($table)) {
            return [
                'success' => true,
                'skipped' => true,
                'table' => $table,
                'message' => "Local database table '{$table}' does not exist.",
                'synced_count' => 0,
            ];
        }

        $config = self::TABLES[$table];
        $localColumns = Schema::getColumnListing($table);
        $allowedColumns = array_values(array_intersect($config['columns'], $localColumns));
        $updateColumns = array_values(array_diff($allowedColumns, ['id']));

        $localMaxId = (int) (DB::table($table)->max('id') ?? 0);
        $targetId = $targetId ?? 0;
        $targetMaxId = $targetMaxId ?? $targetId;

        $totalSynced = 0;
        $syncedSingle = false;

        // Step 1: Synchronize specific item if target ID provided
        if ($targetId > 0) {
            $syncedSingle = $this->syncSingleRecord($table, $targetId, $allowedColumns, $updateColumns, $action);
            if ($syncedSingle) {
                $totalSynced++;
            }
        }

        // Step 2: Synchronize range if master max ID is ahead of local max ID
        $rangeReport = null;
        if ($targetMaxId > $localMaxId) {
            $rangeReport = $this->syncRange(
                $table,
                $localMaxId,
                $targetMaxId,
                $allowedColumns,
                $updateColumns,
                $chunkSize
            );
            $totalSynced += (int) ($rangeReport['synced_count'] ?? 0);
        }

        $newLocalMaxId = (int) (DB::table($table)->max('id') ?? $localMaxId);

        return [
            'success' => true,
            'table' => $table,
            'label' => $config['label'],
            'action' => $action,
            'target_id' => $targetId,
            'target_max_id' => $targetMaxId,
            'previous_local_max_id' => $localMaxId,
            'current_local_max_id' => $newLocalMaxId,
            'synced_single' => $syncedSingle,
            'synced_count' => $totalSynced,
            'range_report' => $rangeReport,
        ];
    }

    /**
     * Synchronize a single record by primary key from db_master.
     */
    public function syncSingleRecord(
        string $table,
        int $id,
        array $allowedColumns,
        array $updateColumns,
        string $action = 'updated'
    ): bool {
        try {
            $masterRow = DB::connection('db_master')
                ->table($table)
                ->where('id', $id)
                ->first();

            if ($masterRow) {
                $record = [];
                foreach ($allowedColumns as $col) {
                    $record[$col] = $masterRow->$col ?? null;
                }

                // If action is deleted, ensure deleted_at is preserved or set
                if ($action === 'deleted' && in_array('deleted_at', $allowedColumns, true)) {
                    $record['deleted_at'] = $record['deleted_at'] ?? now();
                }

                DB::table($table)->upsert([$record], ['id'], $updateColumns);

                return true;
            }

            // If not found in db_master and action is deleted, apply soft delete locally
            if ($action === 'deleted' && in_array('deleted_at', $allowedColumns, true)) {
                DB::table($table)->where('id', $id)->update(['deleted_at' => now()]);

                return true;
            }

            return false;
        } catch (Throwable $e) {
            Log::warning("[MasterDataSyncService] Failed to sync single record {$table} ID {$id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Synchronize a range of IDs with chunking.
     */
    public function syncRange(
        string $table,
        int $fromId,
        int $toId,
        array $allowedColumns,
        array $updateColumns,
        int $chunkSize = 250
    ): array {
        $syncedCount = 0;
        $batches = 0;
        $startTime = microtime(true);

        try {
            $query = DB::connection('db_master')
                ->table($table)
                ->where('id', '>', $fromId)
                ->where('id', '<=', $toId)
                ->orderBy('id');

            $query->chunkById($chunkSize, function ($rows) use (
                $table,
                $allowedColumns,
                $updateColumns,
                &$syncedCount,
                &$batches
            ) {
                $batchData = [];
                foreach ($rows as $row) {
                    $item = [];
                    foreach ($allowedColumns as $col) {
                        $item[$col] = $row->$col ?? null;
                    }
                    $batchData[] = $item;
                }

                if (! empty($batchData)) {
                    DB::table($table)->upsert($batchData, ['id'], $updateColumns);
                    $syncedCount += count($batchData);
                    $batches++;
                }
            });

            return [
                'success' => true,
                'from_id' => $fromId,
                'to_id' => $toId,
                'synced_count' => $syncedCount,
                'batches' => $batches,
                'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (Throwable $e) {
            Log::error("[MasterDataSyncService] Range sync error on {$table}: ".$e->getMessage());

            return [
                'success' => false,
                'from_id' => $fromId,
                'to_id' => $toId,
                'synced_count' => $syncedCount,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Backward-compatible method for master_item_code.
     */
    public function syncToMaxId(int $targetMaxId, int $chunkSize = 250): array
    {
        return $this->syncTable('master_item_code', $targetMaxId, $targetMaxId, 'updated', $chunkSize);
    }

    /**
     * Ensure the dashboard_settings table exists.
     */
    public static function ensureDashboardSettingsTable(): void
    {
        if (! Schema::hasTable('dashboard_settings')) {
            Schema::create('dashboard_settings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->string('type')->default('text');
                $table->string('label')->nullable();
                $table->text('options')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Get the last successfully synced broadcast ID from dashboard_settings.
     */
    public static function getLastSyncedBroadcastId(): int
    {
        try {
            self::ensureDashboardSettingsTable();

            $setting = DB::table('dashboard_settings')
                ->where('key', 'last_synced_broadcast_id')
                ->first();

            return $setting ? (int) $setting->value : 0;
        } catch (Throwable $e) {
            Log::warning('[MasterDataSyncService] Failed to read last_synced_broadcast_id: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Record the last successfully synced broadcast ID in dashboard_settings.
     */
    public static function setLastSyncedBroadcastId(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            self::ensureDashboardSettingsTable();

            $exists = DB::table('dashboard_settings')->where('key', 'last_synced_broadcast_id')->exists();
            if ($exists) {
                DB::table('dashboard_settings')
                    ->where('key', 'last_synced_broadcast_id')
                    ->update([
                        'value' => (string) $id,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('dashboard_settings')->insert([
                    'key' => 'last_synced_broadcast_id',
                    'value' => (string) $id,
                    'group' => 'senada_sync',
                    'type' => 'number',
                    'label' => 'Senada Last Synced Broadcast ID',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[MasterDataSyncService] Failed to save last_synced_broadcast_id: '.$e->getMessage());
        }
    }

    /**
     * Fetch and synchronize missed broadcast events from Senada (FCM-like catch-up).
     *
     * @param  int|null  $sinceId  If null, reads from dashboard_settings
     * @param  int  $limit  Max events to process in one catch-up cycle
     * @return array<string, mixed>
     */
    public function catchUpMissedBroadcasts(?int $sinceId = null, int $limit = 100): array
    {
        $checkpoint = $sinceId ?? self::getLastSyncedBroadcastId();

        $senadaUrl = rtrim((string) (config('MasterConfig.senada.url') ?: env('SENADA_URL', 'http://192.168.20.187:9029')), '/');
        $apiKey = (string) (config('MasterConfig.senada.api_key') ?: env('SENADA_API_KEY', 'snd_masterdata_key_secret'));
        $timeout = (int) (config('MasterConfig.senada.timeout') ?: env('SENADA_TIMEOUT', 5));
        $channel = (string) (config('MasterConfig.senada.channel') ?: env('SENADA_CHANNEL_MASTER_ITEMS', 'masterdata.items'));

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$senadaUrl}/api/broadcast/catch-up", [
                    'since_id' => $checkpoint,
                    'channel' => $channel,
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                Log::warning("[MasterDataSyncService] Catch-up failed with status {$response->status()}: ".$response->body());

                return [
                    'success' => false,
                    'message' => "Senada catch-up returned HTTP {$response->status()}",
                    'checkpoint' => $checkpoint,
                    'synced_count' => 0,
                ];
            }

            $data = $response->json();
            $events = $data['events'] ?? [];
            $latestId = (int) ($data['latest_id'] ?? $checkpoint);
            $hasMore = (bool) ($data['has_more'] ?? false);

            if (empty($events)) {
                // If checkpoint was 0 and Senada gave us latest_id, initialize checkpoint to avoid fetching all history next time
                if ($checkpoint === 0 && $latestId > 0) {
                    self::setLastSyncedBroadcastId($latestId);
                }

                return [
                    'success' => true,
                    'message' => 'Up to date. No missed events.',
                    'checkpoint' => $checkpoint,
                    'latest_id' => $latestId,
                    'synced_count' => 0,
                    'has_more' => false,
                    'events_processed' => [],
                ];
            }

            $processed = [];
            $lastProcessedId = $checkpoint;

            foreach ($events as $eventItem) {
                $eventId = (int) ($eventItem['id'] ?? 0);
                $payload = $eventItem['payload'] ?? [];

                if (! empty($payload) && is_array($payload)) {
                    $table = $payload['table'] ?? null;
                    if ($table && $this->isTableSupported($table) && $this->isTableSyncAllowed($table)) {
                        $syncResult = $this->syncFromBroadcast($payload);
                        if (! ($syncResult['skipped'] ?? false)) {
                            $processed[] = [
                                'event_id' => $eventId,
                                'table' => $table,
                                'action' => $payload['action'] ?? 'updated',
                                'result' => $syncResult,
                            ];
                        }
                    }
                }

                if ($eventId > $lastProcessedId) {
                    $lastProcessedId = $eventId;
                }
            }

            // Save new checkpoint
            if ($lastProcessedId > $checkpoint) {
                self::setLastSyncedBroadcastId($lastProcessedId);
            }

            return [
                'success' => true,
                'message' => 'Successfully caught up '.count($processed).' missed events.',
                'previous_checkpoint' => $checkpoint,
                'new_checkpoint' => $lastProcessedId,
                'latest_id' => $latestId,
                'has_more' => $hasMore,
                'synced_count' => count($processed),
                'events_processed' => $processed,
            ];
        } catch (Throwable $e) {
            Log::error('[MasterDataSyncService] Catch-up exception: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'checkpoint' => $checkpoint,
                'synced_count' => 0,
            ];
        }
    }
}
