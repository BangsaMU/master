<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Commands\MasterScopeUpgradeCommand;
use Bangsamu\Master\Support\MasterModules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Struktur tabel master lokal yang tertinggal dari paket / DB master, untuk banner pengelola aplikasi
 * (partial master::partials.schema-notice) beserta perintah perbaikannya:
 *  - scope        : tabel ber-app_code yang belum dinaikkan ke pemilik data v3 (rencana master:scope-upgrade, sama
 *                   dengan master:scope-doctor);
 *  - scope_tables : (klien) tabel global di lokal yang di DB master sudah ber-pemilik (master:scope-upgrade --table);
 *  - columns      : (klien) kolom yang ada di DB master tetapi belum ada di tabel lokal yang disinkron, tidak ikut
 *                   tersalin (master:schema-columns). Kolom pemilik app_code/company_id diurus scope-upgrade.
 * Hanya membaca. Hasil disimpan di cache CACHE_TTL detik dan dikosongkan perintah perbaikan (flush()).
 */
class MasterSchemaStatus
{
    public const CACHE_KEY = 'bangsamu_master_schema_status';

    public const CACHE_TTL = 3600;

    public const OWNER_COLUMNS = ['app_code', 'company_id'];

    public function __construct(protected MasterScope $scope) {}

    /** user login boleh melihat banner (MasterCrudConfig.SCHEMA_NOTICE_PERMISSION, konvensi checkPermission() app) */
    public function visible(): bool
    {
        $user = auth()->user();
        $permission = (string) config('MasterCrudConfig.SCHEMA_NOTICE_PERMISSION', 'is_admin');
        if ($user === null || $permission === '') {
            return false;
        }

        try {
            return function_exists('checkPermission') ? (bool) checkPermission($permission) : (bool) $user->can($permission);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Masalah struktur (dari cache). Gagal dicek = dianggap tidak ada masalah supaya halaman tidak ikut gagal.
     *
     * @return array{scope: array<string, list<string>>, scope_tables: array<string, list<string>>, columns: array<string, list<string>>}
     */
    public function issues(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->detect());
        } catch (Throwable $e) {
            report($e);

            return ['scope' => [], 'scope_tables' => [], 'columns' => []];
        }
    }

    /**
     * @param  array{scope: array, scope_tables: array, columns: array}|null  $issues
     */
    public function hasIssues(?array $issues = null): bool
    {
        $issues ??= $this->issues();

        return $issues['scope'] !== [] || $issues['scope_tables'] !== [] || $issues['columns'] !== [];
    }

    /**
     * Periksa langsung tanpa cache.
     *
     * @return array{scope: array<string, list<string>>, scope_tables: array<string, list<string>>, columns: array<string, list<string>>}
     */
    public function detect(): array
    {
        $upgrade = app(MasterScopeUpgradeCommand::class);
        $upgrader = app(MasterSchemaUpgrader::class);
        $scope = [];
        foreach ($upgrade->defaultTables() as $table) {
            if ($changes = $upgrade->plan($upgrader, $table)) {
                $scope[$table] = $changes;
            }
        }

        [$scopeTables, $columns] = $this->compareWithMaster();

        return ['scope' => $scope, 'scope_tables' => $scopeTables, 'columns' => $columns];
    }

    /**
     * Kolom DB master yang belum ada di tabel lokal yang disinkron (klien saja; tanpa beda huruf besar/kecil).
     *
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    protected function compareWithMaster(): array
    {
        if ($this->scope->isHub() || (function_exists('is_master_db_same_as_default') && is_master_db_same_as_default())) {
            return [[], []];
        }
        try {
            DB::connection('db_master')->getPdo();
        } catch (Throwable $e) {
            return [[], []]; // DB master tidak terjangkau (mis. dev di luar kantor): jangan coba per tabel
        }

        $scopeTables = [];
        $columns = [];
        foreach (MasterDataSyncService::getAllowedSyncTables() as $table) {
            $master = MasterDataSyncService::masterColumns($table);
            if ($master === [] || ! Schema::hasTable($table)) {
                continue;
            }

            $local = array_map('strtolower', Schema::getColumnListing($table));
            $missing = array_values(array_filter($master, fn ($column) => ! in_array(strtolower($column), $local, true)));
            $missingLower = array_map('strtolower', $missing);

            if (in_array('app_code', $missingLower, true) && $this->canScopeUpgrade($table)) {
                $scopeTables[$table] = ['tambah app_code + company_id (di DB master tabel ini sudah ber-pemilik)'];
            }
            $missing = array_values(array_filter($missing, fn ($column) => ! in_array(strtolower($column), self::OWNER_COLUMNS, true)));
            if ($missing !== []) {
                $columns[$table] = $missing;
            }
        }

        return [$scopeTables, $columns];
    }

    protected function canScopeUpgrade(string $table): bool
    {
        return ! in_array($table, MasterScopeUpgradeCommand::DENY, true) && MasterModules::slugForTable($table) !== null;
    }

    /**
     * Argumen artisan perbaikan, urut dijalankan.
     *
     * @param  array{scope: array, scope_tables: array, columns: array}  $issues
     * @return list<string>
     */
    public function commands(array $issues): array
    {
        $commands = [];
        if ($issues['scope'] !== []) {
            $commands[] = 'master:scope-upgrade';
        }
        if ($issues['scope_tables'] !== []) {
            $commands[] = 'master:scope-upgrade '.implode(' ', array_map(fn ($table) => '--table='.$table, array_keys($issues['scope_tables'])));
        }
        if ($issues['columns'] !== []) {
            $commands[] = 'master:schema-columns';
        }

        return $commands;
    }

    /**
     * Awalan `docker exec` untuk menjalankan artisan dari host bila aplikasi berjalan di container: user = pemilik file
     * artisan (bukan root, supaya file cache tidak jadi milik root), folder = base_path(). Null bila bukan container.
     */
    public function dockerPrefix(): ?string
    {
        $container = (string) config('MasterCrudConfig.DOCKER_CONTAINER');
        if ($container === '' && is_file('/.dockerenv')) {
            $container = (string) gethostname();
        }
        if ($container === '') {
            return null;
        }

        $owner = @fileowner(base_path('artisan'));
        $group = @filegroup(base_path('artisan'));
        $user = is_int($owner) && $owner > 0 ? ' -u '.$owner.':'.(is_int($group) ? $group : $owner).' -e HOME=/tmp' : '';

        return 'docker exec'.$user.' -w '.self::shellArg(base_path()).' '.self::shellArg($container);
    }

    protected static function shellArg(string $value): string
    {
        return preg_match('#^[A-Za-z0-9_./:@-]+$#', $value) ? $value : escapeshellarg($value);
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            // cache tidak tersedia: banner dicek ulang saat cache kedaluwarsa
        }
    }
}
