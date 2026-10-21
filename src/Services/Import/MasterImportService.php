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
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    protected const PROGRESS_PREFIX = 'master-import-progress:';

    protected const LOCK_PREFIX = 'master-import-lock:';

    protected const CACHE_HOURS = 2;

    /** Default baris per request commit (AJAX) agar tidak melewati timeout nginx/PHP-FPM. */
    public const CHUNK_SIZE = 250;

    protected const MAX_SUCCESS_MESSAGES = 100;

    protected const MAX_ERROR_MESSAGES = 300;

    public function definition(string $entity): MasterImportDefinition
    {
        abort_unless(isset(self::DEFINITIONS[$entity]), 404, "Import master '{$entity}' tidak tersedia.");

        return app(self::DEFINITIONS[$entity]);
    }

    /** Batas baris per file; file dibaca utuh ke memori saat preview (~9MB per 1.000 baris). */
    public const MAX_ROWS = 40000;

    public function maxRows(): int
    {
        return max(1, (int) config('MasterConfig.import.max_rows', self::MAX_ROWS));
    }

    /**
     * Jumlah baris sheet pertama tanpa memuat seluruh isi file (null bila tidak bisa dibaca).
     */
    public function countRows(UploadedFile|string $file): ?int
    {
        try {
            $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $extension = strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($path, PATHINFO_EXTENSION));
            $type = match ($extension) {
                'xlsx' => 'Xlsx',
                'xls' => 'Xls',
                'csv', 'txt' => 'Csv',
                default => null,
            };
            $reader = $type ? IOFactory::createReader($type) : IOFactory::createReaderForFile($path);
            $info = $reader->listWorksheetInfo($path);

            return isset($info[0]['totalRows']) ? (int) $info[0]['totalRows'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Baris per batch commit; bisa diatur lewat config MasterConfig.import.chunk_size. */
    public function chunkSize(): int
    {
        return max(1, (int) config('MasterConfig.import.chunk_size', self::CHUNK_SIZE));
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
     * Jalankan seluruh import dalam satu request (fallback tanpa JavaScript).
     *
     * @return array<string, mixed> progress akhir (lihat commitChunk)
     */
    public function commit(
        MasterImportDefinition $definition,
        string $token,
        array $payload,
        bool $allowUpdate,
        ?string $reason,
        Authenticatable $user,
    ): array {
        return $this->commitChunk($definition, $token, $payload, 0, PHP_INT_MAX, $allowUpdate, $reason, $user);
    }

    /**
     * Proses satu batch baris [offset, offset + limit) supaya request tidak melewati timeout web server.
     *
     * Opsi update & alasan dikunci saat batch pertama; offset yang tidak sesuai progress (retry/double submit)
     * tidak memproses ulang, cukup mengembalikan progress terakhir.
     * Analisis diulang tiap batch supaya memakai kondisi DB terbaru.
     *
     * @return array{total:int, next_offset:int, done:bool, created:int, updated:int, skipped:int, failed:int,
     *               success:list<string>, errors:list<string>, more_success:int, more_errors:int}
     */
    public function commitChunk(
        MasterImportDefinition $definition,
        string $token,
        array $payload,
        int $offset,
        int $limit,
        bool $allowUpdate,
        ?string $reason,
        Authenticatable $user,
    ): array {
        return $this->withLock($token, function () use ($definition, $token, $payload, $offset, $limit, $allowUpdate, $reason, $user) {
            $progress = Cache::get(self::PROGRESS_PREFIX.$token) ?? [
                'allow_update' => $allowUpdate,
                'reason' => $allowUpdate ? trim((string) $reason) : null,
                'total' => count($payload['rows']),
                'next_offset' => 0,
                'done' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'success' => [],
                'errors' => [],
                'more_success' => 0,
                'more_errors' => 0,
            ];

            if ($progress['done'] || $offset !== $progress['next_offset']) {
                return $progress;
            }

            $analysis = $this->analyze($definition, $payload['rows']);
            $synced = [];

            foreach (array_slice($analysis['rows'], $offset, $limit) as $item) {
                foreach ($this->processRow($definition, $item, $token, $payload['file_name'], $progress, $user) as $model) {
                    $synced[] = $model;
                }
            }

            foreach ($synced as $model) {
                $definition->syncToMaster($model);
            }

            $progress['next_offset'] = (int) min($progress['total'], $offset + $limit);
            $progress['done'] = $progress['next_offset'] >= $progress['total'];

            if ($progress['done']) {
                $this->broadcastSummary($definition, $progress);
                $this->forget($token);
                Cache::forget(self::PROGRESS_PREFIX.$token);
            } else {
                Cache::put(self::PROGRESS_PREFIX.$token, $progress, now()->addHours(self::CACHE_HOURS));
            }

            return $progress;
        });
    }

    /**
     * Simpan satu baris hasil analisis dan catat hasilnya di $progress.
     *
     * @return list<\Illuminate\Database\Eloquent\Model> model yang perlu disinkron ke DB master
     */
    protected function processRow(
        MasterImportDefinition $definition,
        array $item,
        string $token,
        string $fileName,
        array &$progress,
        Authenticatable $user,
    ): array {
        $label = "Baris {$item['row']} [{$item['key']}]";

        if ($item['status'] === 'error') {
            $progress['failed']++;
            $this->pushMessage($progress, 'errors', "{$label}: {$item['message']}");

            return [];
        }

        if ($item['status'] === 'same' || $item['status'] === 'locked' || ($item['status'] === 'update' && ! $progress['allow_update'])) {
            $progress['skipped']++;

            return [];
        }

        try {
            $logs = DB::transaction(function () use ($definition, $item, $token, $fileName, $progress, $user) {
                $logs = $item['status'] === 'new'
                    ? $definition->create($item['data'])
                    : $definition->update((int) $item['id'], $item['changes'], $item['data']);

                foreach ($logs as $log) {
                    $this->writeActivityLog($definition, $log, $item, $token, $fileName, $progress['reason'], $user);
                }

                return $logs;
            });
        } catch (Throwable $e) {
            report($e);
            $progress['failed']++;
            $this->pushMessage($progress, 'errors', "{$label}: gagal disimpan - {$e->getMessage()}");

            return [];
        }

        if ($item['status'] === 'new') {
            $progress['created']++;
            $this->pushMessage($progress, 'success', "{$label}: berhasil dibuat.");
        } else {
            $progress['updated']++;
            $fieldLabels = implode(', ', array_column($item['changes'], 'label'));
            $this->pushMessage($progress, 'success', "{$label}: berhasil diupdate ({$fieldLabels}).");
        }

        return array_column($logs, 'model');
    }

    /** Simpan pesan detail secukupnya; sisanya hanya dihitung supaya session tidak membengkak. */
    protected function pushMessage(array &$progress, string $type, string $message): void
    {
        $limit = $type === 'errors' ? self::MAX_ERROR_MESSAGES : self::MAX_SUCCESS_MESSAGES;

        if (count($progress[$type]) < $limit) {
            $progress[$type][] = $message;
        } else {
            $progress[$type === 'errors' ? 'more_errors' : 'more_success']++;
        }
    }

    /**
     * Cegah dua batch untuk preview yang sama berjalan bersamaan (double submit / dua tab).
     */
    protected function withLock(string $token, callable $callback): mixed
    {
        $store = Cache::getStore();
        if (! $store instanceof LockProvider) {
            return $callback();
        }

        return Cache::lock(self::LOCK_PREFIX.$token, 600)->block(30, $callback);
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
