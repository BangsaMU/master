<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import;

use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Services\Import\Definitions\CategoryImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\ItemCodeImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\ItemGroupImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\LocationImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\PcaImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\ProjectDetailImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\ProjectImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\UomImportDefinition;
use Bangsamu\Master\Services\Import\Definitions\VendorImportDefinition;
use Bangsamu\Master\Services\MasterBroadcastService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Import master data dua tahap: preview (tanpa menulis DB) lalu commit.
 *
 * Status baris preview:
 *  - new     : belum ada, akan dibuat
 *  - update  : sudah ada dan ada kolom berbeda; hanya diproses bila "izinkan update" dicentang
 *  - same    : sudah ada dan tidak ada perubahan
 *  - locked  : sudah ada tetapi milik aplikasi lain (app_code berbeda), tidak boleh diubah
 *  - error   : tidak valid, dilewati
 */
class MasterImportService
{
    public const DEFINITIONS = [
        'category' => CategoryImportDefinition::class,
        'uom' => UomImportDefinition::class,
        'item-group' => ItemGroupImportDefinition::class,
        'item-code' => ItemCodeImportDefinition::class,
        'pca' => PcaImportDefinition::class,
        'location' => LocationImportDefinition::class,
        'project' => ProjectImportDefinition::class,
        'project-detail' => ProjectDetailImportDefinition::class,
        'vendor' => VendorImportDefinition::class,
    ];

    public const STATUSES = ['new', 'update', 'same', 'locked', 'error'];

    protected const CACHE_PREFIX = 'master-import-preview:';

    protected const CACHE_HOURS = 2;

    public function definition(string $entity): MasterImportDefinition
    {
        abort_unless(isset(self::DEFINITIONS[$entity]), 404, "Import master '{$entity}' tidak tersedia.");

        return app(self::DEFINITIONS[$entity]);
    }

    public function canCreate(MasterImportDefinition $definition): bool
    {
        return $this->hasPermission('create_'.$definition->permission());
    }

    public function canUpdate(MasterImportDefinition $definition): bool
    {
        return $this->hasPermission('update_'.$definition->permission());
    }

    protected function hasPermission(string $permission): bool
    {
        if (! function_exists('checkPermission')) {
            return auth()->check();
        }

        return checkPermission('is_admin') || checkPermission($permission);
    }

    /**
     * Baca sheet pertama file menjadi baris ter-normalisasi.
     *
     * @return list<array{row:int, data:array<string, string>}>
     */
    public function readFile(MasterImportDefinition $definition, UploadedFile|string $file): array
    {
        // ToCollection kosong: hanya dipakai sebagai penanda import (kompatibel maatwebsite 3.1 & versi baru)
        $reader = $definition->usesHeadingRow()
            ? new class implements ToCollection, WithHeadingRow
            {
                public function collection(Collection $collection): void {}
            }
        : new class implements ToCollection
        {
            public function collection(Collection $collection): void {}
        };

        $sheet = Excel::toCollection($reader, $file)->first() ?? collect();

        $rows = [];
        foreach ($sheet as $index => $raw) {
            $raw = $raw->toArray();

            if (! $definition->usesHeadingRow() && $index === 0) {
                $definition->readHeader($raw);

                continue;
            }

            if (! array_filter($raw, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }

            $rows[] = [
                'row' => $index + ($definition->usesHeadingRow() ? 2 : 1), // nomor baris di Excel
                'data' => $definition->normalize($raw),
            ];
        }

        return $rows;
    }

    /**
     * Simpan hasil baca file untuk dipakai halaman preview dan commit.
     */
    public function store(MasterImportDefinition $definition, array $rows, string $fileName, int|string $userId): string
    {
        $token = (string) Str::uuid();

        Cache::put(self::CACHE_PREFIX.$token, [
            'entity' => $definition->entity(),
            'user_id' => $userId,
            'file_name' => $fileName,
            'meta' => $definition->meta(),
            'rows' => $rows,
            'created_at' => now()->toDateTimeString(),
        ], now()->addHours(self::CACHE_HOURS));

        return $token;
    }

    /**
     * Ambil preview milik user; null bila kedaluwarsa / bukan miliknya / beda master.
     */
    public function load(string $token, MasterImportDefinition $definition, int|string $userId): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($payload) || $payload['entity'] !== $definition->entity() || (string) $payload['user_id'] !== (string) $userId) {
            return null;
        }

        $definition->withMeta($payload['meta'] ?? []);

        return $payload;
    }

    public function forget(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX.$token);
    }

    /**
     * Klasifikasikan setiap baris terhadap kondisi DB saat ini (tidak menulis apa pun).
     *
     * @param  list<array{row:int, data:array<string, string>}>  $rows
     * @return array{rows:list<array>, summary:array<string, int>, update_fields:array<string, int>}
     */
    public function analyze(MasterImportDefinition $definition, array $rows): array
    {
        $definition->prepare(array_column($rows, 'data'));

        $fields = $definition->fields();
        $appCode = (string) config('SsoConfig.main.APP_CODE', 'APP03');
        $seen = [];
        $result = [];
        $summary = array_fill_keys(self::STATUSES, 0);
        $updateFields = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            $item = [
                'row' => $row['row'],
                'key' => '',
                'data' => $data,
                'status' => 'error',
                'message' => null,
                'changes' => [],
                'notes' => [],
                'id' => null,
            ];

            $error = $definition->validate($data);
            if ($error === null) {
                $key = $definition->key($data);
                $item['key'] = $definition->keyDisplay($data);

                if (isset($seen[$key])) {
                    $error = "{$definition->keyLabel()} '{$item['key']}' duplikat dengan baris {$seen[$key]} di file yang sama.";
                } else {
                    $seen[$key] = $row['row'];
                    $existing = $definition->existing($data);

                    if ($existing && $existing['trashed']) {
                        $error = "{$definition->keyLabel()} '{$item['key']}' sudah ada dengan status terhapus (soft delete).";
                    } else {
                        $error = $definition->check($data, $existing);
                    }

                    if ($error === null) {
                        $item['notes'] = $definition->notes($data);
                        $item['id'] = $existing['id'] ?? null;

                        if (! $existing) {
                            $item['status'] = 'new';
                        } elseif ($definition->ownershipColumn() && $existing['owner'] !== null && (string) $existing['owner'] !== $appCode) {
                            $item['status'] = 'locked';
                            $item['message'] = "Data milik aplikasi {$existing['owner']}, tidak dapat diupdate dari aplikasi ini.";
                        } else {
                            foreach ($definition->updatableFields() as $field) {
                                if (! $definition->hasValue($data, $field)) {
                                    continue; // kolom kosong di file tidak mengosongkan data lama
                                }
                                $old = $existing['values'][$field] ?? '';
                                $new = $definition->mergeValue($field, $old, $data[$field]);
                                if ($new !== $old) {
                                    $item['changes'][$field] = ['label' => $fields[$field] ?? $field, 'old' => $old, 'new' => $new];
                                    $updateFields[$field] = ($updateFields[$field] ?? 0) + 1;
                                }
                            }
                            $item['status'] = $item['changes'] ? 'update' : 'same';
                        }
                    }
                }
            }

            if ($error !== null) {
                $item['status'] = 'error';
                $item['message'] = $error;
            }

            $summary[$item['status']]++;
            $result[] = $item;
        }

        $labels = [];
        foreach ($updateFields as $field => $count) {
            $labels[$fields[$field] ?? $field] = $count;
        }

        return ['rows' => $result, 'summary' => $summary, 'update_fields' => $labels];
    }

    /**
     * Jalankan import dari preview. Analisis diulang supaya memakai kondisi DB terbaru.
     *
     * @return array{created:int, updated:int, skipped:int, failed:int, success:list<string>, errors:list<string>}
     */
    public function commit(
        MasterImportDefinition $definition,
        string $token,
        array $payload,
        bool $allowUpdate,
        ?string $reason,
        Authenticatable $user,
    ): array {
        $analysis = $this->analyze($definition, $payload['rows']);
        $reason = $allowUpdate ? trim((string) $reason) : null;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'success' => [], 'errors' => []];
        $synced = [];

        foreach ($analysis['rows'] as $item) {
            $label = "Baris {$item['row']} [{$item['key']}]";

            if ($item['status'] === 'error') {
                $result['failed']++;
                $result['errors'][] = "{$label}: {$item['message']}";

                continue;
            }

            if ($item['status'] === 'same' || $item['status'] === 'locked' || ($item['status'] === 'update' && ! $allowUpdate)) {
                $result['skipped']++;

                continue;
            }

            try {
                $logs = DB::transaction(function () use ($definition, $item, $token, $payload, $reason, $user) {
                    $logs = $item['status'] === 'new'
                        ? $definition->create($item['data'])
                        : $definition->update((int) $item['id'], $item['changes'], $item['data']);

                    foreach ($logs as $log) {
                        $this->writeActivityLog($definition, $log, $item, $token, $payload['file_name'], $reason, $user);
                    }

                    return $logs;
                });
            } catch (Throwable $e) {
                report($e);
                $result['failed']++;
                $result['errors'][] = "{$label}: gagal disimpan - {$e->getMessage()}";

                continue;
            }

            foreach ($logs as $log) {
                $synced[] = $log['model'];
            }

            if ($item['status'] === 'new') {
                $result['created']++;
                $result['success'][] = "{$label}: berhasil dibuat.";
            } else {
                $result['updated']++;
                $fieldLabels = implode(', ', array_column($item['changes'], 'label'));
                $result['success'][] = "{$label}: berhasil diupdate ({$fieldLabels}).";
            }
        }

        foreach ($synced as $model) {
            $definition->syncToMaster($model);
        }

        $this->broadcastSummary($definition, $result);
        $this->forget($token);

        return $result;
    }

    /**
     * Tulis activity_logs; alasan update ditambahkan di description.
     * Contoh: "bagas setyo updated Category (via import: koreksi nama dari SAP)".
     */
    protected function writeActivityLog(
        MasterImportDefinition $definition,
        array $log,
        array $item,
        string $token,
        string $fileName,
        ?string $reason,
        Authenticatable $user,
    ): void {
        $model = $log['model'];
        $name = $user->name ?? 'System';
        $subject = class_basename($model);

        if ($log['action'] === 'created') {
            $description = "{$name} created a new {$subject} (via import)";
            $properties = $log['after'];
        } else {
            $description = $reason !== null && $reason !== ''
                ? "{$name} updated {$subject} (via import: {$reason})"
                : "{$name} updated {$subject} (via import)";
            $properties = ['before' => $log['before'], 'after' => $log['after']];
        }

        $properties['import'] = [
            'source' => 'import',
            'master' => $definition->entity(),
            'batch' => $token,
            'file' => $fileName,
            'row' => $item['row'],
            'key' => $item['key'],
            'reason' => $reason,
        ];

        ActivityLog::log($log['action'], $description, $model, $properties);
    }

    protected function broadcastSummary(MasterImportDefinition $definition, array $result): void
    {
        try {
            foreach (['created', 'updated'] as $action) {
                if ($result[$action] > 0) {
                    MasterBroadcastService::broadcastBatchSummary($definition->table(), 0, $result[$action], $action);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
