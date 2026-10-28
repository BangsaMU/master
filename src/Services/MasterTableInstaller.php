<?php

namespace Bangsamu\Master\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

/**
 * Membuat tabel master_* lokal yang belum ada memakai skema auto-create di model package
 * (tanpa file migration). Datanya diisi kemudian oleh fitur sync master.
 */
class MasterTableInstaller
{
    public function __construct(protected ?MasterSchemaUpgrader $upgrader = null)
    {
        $this->upgrader ??= new MasterSchemaUpgrader;
    }

    /**
     * @return array{created: list<string>, failed: array<string, string>, upgraded: array<string, list<string>>}
     */
    public function install(): array
    {
        $created = [];
        $failed = [];

        foreach ($this->localMasterModels() as $class => $table) {
            if (Schema::hasTable($table)) {
                continue;
            }

            try {
                new $class;
            } catch (Throwable $e) {
                $failed[$table] = $e->getMessage();

                continue;
            }

            if (Schema::hasTable($table)) {
                $created[] = $table;
            } else {
                // auto-create di model hanya jalan sekali per proses (saat boot pertama)
                $failed[$table] = "Model {$class} sudah di-boot di proses ini; jalankan php artisan master:install-tables.";
            }
        }

        // tabel yang sudah ada: samakan dengan blueprint terbaru (tambah kolom/index, tanpa hapus data)
        $upgraded = $this->upgrader->upgradeAll();

        // tabel baru langsung ikut whitelist sinkron, tidak menunggu cache cek tabel kedaluwarsa
        if ($created !== []) {
            MasterDataSyncService::flushSchemaCache();
        }

        return ['created' => $created, 'failed' => $failed, 'upgraded' => $upgraded];
    }

    /**
     * Model lokal (tanpa $connection) bertabel master_* yang punya auto-create.
     *
     * @return array<class-string<Model>, string>
     */
    public function localMasterModels(): array
    {
        $models = [];

        foreach (glob(__DIR__.'/../Models/*.php') as $file) {
            $class = 'Bangsamu\\Master\\Models\\'.basename($file, '.php');
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $defaults = $reflection->getDefaultProperties();
            $table = $defaults['table'] ?? null;

            if (! is_string($table) || ! str_starts_with($table, 'master_')
                || ! empty($defaults['connection']) || ! $reflection->hasProperty('hasCheckedTable')) {
                continue;
            }

            $models[$class] ??= $table;
        }

        // satu tabel bisa punya beberapa model (mis. User & MasterUser); cukup satu
        return array_unique($models);
    }
}
