<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Support\MasterModules;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Naikkan tabel master ke pemilik data app_code + company_id (MasterScope). Sengaja perintah eksplisit, tidak jalan
 * otomatis saat halaman dibuka: DB master dipakai bersama, dan DDL meng-commit transaksi yang sedang berjalan.
 *
 * Tanpa --table: tabel master yang sudah punya app_code diberi company_id. Dengan --table: tabel global (mis.
 * master_location) diberi app_code (default GLOBAL_APP_CODE = data bersama) + company_id. Setiap index unik lama
 * diperluas dengan app_code/company_id. Hanya menambah kolom dan mengganti index (melonggarkan), tanpa mengubah/menghapus
 * data; validasi form tetap unik global sampai tabelnya dicantumkan di MasterCrudConfig.UNIQUE_PER_OWNER.
 */
class MasterScopeUpgradeCommand extends Command
{
    /** tabel dengan aturan khusus / bukan data ber-pemilik: tidak boleh dinaikkan lewat perintah ini */
    public const DENY = [
        'master_employee', 'master_status', 'master_increment', 'master_project_detail', 'master_company',
        'master_apps', 'master_app_groups', 'master_user', 'master_user_details', 'master_user_position',
        'master_gallery', 'master_file_manager', 'master_item_code_picture', 'master_vendor_contact',
    ];

    protected $signature = 'master:scope-upgrade
        {--table=* : Tabel yang dinaikkan (boleh berulang); kosong = tabel master yang sudah punya app_code}
        {--connection= : Koneksi database (default koneksi default aplikasi)}
        {--dry-run : Hanya tampilkan rencana perubahan}';

    protected $description = 'Tambah pemilik data app_code + company_id ke tabel master dan perluas index uniknya';

    public function handle(MasterSchemaUpgrader $upgrader): int
    {
        $connection = $this->option('connection') ?: DB::getDefaultConnection();
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            $tables = $this->targetTables();
            if ($tables === null) {
                return self::FAILURE;
            }

            $dry = (bool) $this->option('dry-run');
            foreach ($tables as $table) {
                $changes = $this->upgrade($upgrader, $table, $dry);
                $this->line(($dry ? '[dry-run] ' : '').$table.': '.($changes === [] ? 'sudah sesuai' : implode('; ', $changes)));
            }
        } finally {
            DB::setDefaultConnection($previous);
            MasterScope::flush();
            MasterSchemaUpgrader::flush();
            MasterDataSyncService::flushSchemaCache();
        }

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    protected function targetTables(): ?array
    {
        $requested = array_values(array_filter((array) $this->option('table')));
        if ($requested === []) {
            $tables = [];
            foreach (array_keys(MasterModules::MODULES) as $slug) {
                $table = MasterModules::table($slug);
                if (! in_array($table, self::DENY, true) && Schema::hasTable($table) && Schema::hasColumn($table, 'app_code')) {
                    $tables[] = $table;
                }
            }

            return $tables;
        }

        foreach ($requested as $table) {
            if (in_array($table, self::DENY, true)) {
                $this->error("{$table} punya aturan khusus, tidak dinaikkan lewat perintah ini.");

                return null;
            }
            if (MasterModules::slugForTable($table) === null) {
                $this->error("{$table} bukan tabel modul master (lihat MasterModules::MODULES).");

                return null;
            }
            if (! Schema::hasTable($table)) {
                $this->error("Tabel {$table} tidak ada.");

                return null;
            }
        }

        return $requested;
    }

    /** @return list<string> */
    protected function upgrade(MasterSchemaUpgrader $upgrader, string $table, bool $dry): array
    {
        $changes = [];
        $globalApp = (string) config('MasterCrudConfig.GLOBAL_APP_CODE', 'APP03');

        if (! Schema::hasColumn($table, 'app_code')) {
            $changes[] = "tambah app_code (default {$globalApp})";
            if (! $dry) {
                Schema::table($table, function (Blueprint $blueprint) use ($globalApp, $table) {
                    $blueprint->string('app_code', 10)->default($globalApp);
                    $blueprint->index('app_code', $this->indexName($table, 'idx', 'app_code'));
                });
            }
        }

        if (! Schema::hasColumn($table, 'company_id')) {
            $changes[] = 'tambah company_id (default 0 = semua company)';
            if (! $dry) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->unsignedInteger('company_id')->default(0);
                    $blueprint->index(['app_code', 'company_id'], $this->indexName($table, 'idx', 'owner'));
                });
            }
        } elseif (DB::table($table)->whereNull('company_id')->exists()) {
            $changes[] = 'company_id NULL -> 0';
            if (! $dry) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => 0]);
            }
        }

        $ownerColumns = ['app_code', 'company_id'];
        $indexes = Schema::hasTable($table) ? $upgrader->uniqueIndexes($table) : [];
        $existingSets = array_map(fn ($columns) => implode(',', $columns), $indexes);

        foreach ($indexes as $name => $columns) {
            if (array_diff($ownerColumns, $columns) === []) {
                continue; // sudah memuat pemilik
            }
            $scoped = array_values(array_unique(array_merge(array_values(array_diff($columns, $ownerColumns)), $ownerColumns)));
            $set = implode(',', $scoped);
            $duplicate = in_array($set, $existingSets, true);

            $changes[] = $duplicate
                ? "hapus unik {$name} (".implode(',', $columns).'), sudah ada ('.$set.')'
                : "unik {$name} (".implode(',', $columns).") -> ({$set})";

            if (! $dry) {
                Schema::table($table, function (Blueprint $blueprint) use ($name, $scoped, $duplicate, $table) {
                    $blueprint->dropUnique($name);
                    if (! $duplicate) {
                        $blueprint->unique($scoped, $this->indexName($table, 'uq', implode('_', array_diff($scoped, ['app_code', 'company_id'])).'_owner'));
                    }
                });
            }
            $existingSets[] = $set;
        }

        return $changes;
    }

    protected function indexName(string $table, string $prefix, string $suffix): string
    {
        $name = $prefix.'_'.substr($table, strlen('master_')).'_'.$suffix;

        return strlen($name) <= 64 ? $name : $prefix.'_'.substr(md5($table.$suffix), 0, 12);
    }
}
