<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * master_department: tambah app_code (pemilik data, sama seperti master_item_code)
 * dan ganti kunci unik department_code -> (department_code, app_code).
 *
 * Aman untuk data: hanya menambah kolom & mengganti index; baris lama mendapat app_code default APP03.
 */
return new class extends Migration
{
    private const TABLE = 'master_department';

    private const UNIQUE = 'uq_dept_code_app';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return; // tabel baru dibuat oleh model Department dengan skema terbaru
        }

        if (! Schema::hasColumn(self::TABLE, 'app_code')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('app_code', 10)->default('APP03')->after('department_name');
                $table->index('app_code', 'idx_dept_app_code');
            });
        }

        $indexes = $this->uniqueIndexes();

        Schema::table(self::TABLE, function (Blueprint $table) use ($indexes) {
            foreach ($indexes as $name => $columns) {
                if ($columns === ['department_code']) {
                    $table->dropUnique($name);
                }
            }
            if (! isset($indexes[self::UNIQUE])) {
                $table->unique(['department_code', 'app_code'], self::UNIQUE);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! isset($this->uniqueIndexes()[self::UNIQUE])) {
            return;
        }

        // Kolom app_code sengaja tidak dihapus (menghindari kehilangan data); hanya kunci unik dikembalikan.
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE);
            $table->unique('department_code', 'uq_dept_code');
        });
    }

    /**
     * Unique index (selain primary) => daftar kolom, untuk MySQL/MariaDB dan SQLite.
     *
     * @return array<string, list<string>>
     */
    private function uniqueIndexes(): array
    {
        $result = [];
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('".self::TABLE."')") as $index) {
                if ((int) $index->unique === 1 && $index->origin !== 'pk') {
                    $result[$index->name] = array_map(fn ($c) => $c->name, DB::select("PRAGMA index_info('{$index->name}')"));
                }
            }

            return $result;
        }

        foreach (DB::select('SHOW INDEX FROM `'.self::TABLE.'`') as $row) {
            if ((int) $row->Non_unique === 0 && $row->Key_name !== 'PRIMARY') {
                $result[$row->Key_name][(int) $row->Seq_in_index - 1] = $row->Column_name;
            }
        }

        foreach ($result as $name => $columns) {
            ksort($columns);
            $result[$name] = array_values($columns);
        }

        return $result;
    }
};
