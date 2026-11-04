<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Services\MasterSchemaStatus;
use Bangsamu\Master\Services\MasterScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * (Klien) Tambah kolom yang ada di tabel DB master tetapi belum ada di tabel master lokal yang disinkron, supaya ikut
 * tersalin. Kolom baru selalu NULL-able, tanpa default/index, dengan tipe dari DB master; data tidak diubah. Kolom
 * pemilik app_code/company_id tidak ditambahkan di sini (master:scope-upgrade). Sengaja perintah eksplisit (DDL).
 */
class MasterSchemaColumnsCommand extends Command
{
    protected $signature = 'master:schema-columns
        {--table=* : Tabel (boleh berulang); kosong = semua tabel sinkron}
        {--dry-run : Hanya tampilkan rencana perubahan}';

    protected $description = 'Tambah kolom tabel master lokal yang ada di DB master tetapi belum ada di sini (NULL-able, tanpa mengubah data)';

    /** tipe kolom DB master yang dipakai di DDL, mis. varchar(255), int(10) unsigned, decimal(15,2), enum('a','b') */
    private const TYPE_PATTERN = "/^[a-z][a-z ]*(\\((\\d+(,\\s*\\d+)?|'[^']*'(,\\s*'[^']*')*)\\))?( unsigned)?( zerofill)?$/i";

    public function handle(MasterScope $scope): int
    {
        if ($scope->isHub() || (function_exists('is_master_db_same_as_default') && is_master_db_same_as_default())) {
            $this->info('Aplikasi ini memakai DB master sendiri, tidak ada yang disesuaikan.');

            return self::SUCCESS;
        }

        $requested = array_values(array_filter((array) $this->option('table')));
        $tables = $requested !== [] ? $requested : MasterDataSyncService::getAllowedSyncTables();
        $dry = (bool) $this->option('dry-run');
        $failed = false;

        try {
            foreach ($tables as $table) {
                if (! isset(MasterDataSyncService::TABLES[$table]) || ! Schema::hasTable($table)) {
                    $this->error("{$table} bukan tabel sinkron master (MasterDataSyncService::TABLES) yang ada di DB lokal.");
                    $failed = true;

                    continue;
                }

                $changes = $this->addMissingColumns($table, $dry);
                $this->line(($dry ? '[dry-run] ' : '').$table.': '.($changes === [] ? 'sudah sesuai' : implode('; ', $changes)));
            }
        } finally {
            MasterDataSyncService::flushSchemaCache();
            MasterSchemaStatus::flush();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    protected function addMissingColumns(string $table, bool $dry): array
    {
        try {
            $masterColumns = Schema::connection('db_master')->getColumns($table);
        } catch (Throwable $e) {
            return ['DB master tidak terbaca ('.$e->getMessage().')'];
        }

        $local = array_map('strtolower', Schema::getColumnListing($table));
        $grammar = DB::connection()->getQueryGrammar();
        $changes = [];
        foreach ($masterColumns as $column) {
            $name = (string) $column['name'];
            $lower = strtolower($name);
            if (in_array($lower, $local, true) || in_array($lower, MasterSchemaStatus::OWNER_COLUMNS, true)) {
                continue;
            }

            $type = trim((string) ($column['type'] ?? ''));
            if (! empty($column['auto_increment']) || ! preg_match(self::TYPE_PATTERN, $type)) {
                $changes[] = "lewati {$name} (tipe {$type} tidak bisa ditambahkan otomatis)";

                continue;
            }

            $changes[] = "tambah {$name} {$type} NULL";
            if (! $dry) {
                DB::statement('ALTER TABLE '.$grammar->wrapTable($table).' ADD COLUMN '.$grammar->wrap($name).' '.$type.' NULL');
            }
        }

        return $changes;
    }
}
