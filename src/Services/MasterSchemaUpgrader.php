<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Upgrade skema tabel master_* yang SUDAH ADA agar sama dengan blueprint model terbaru.
 *
 * Auto-create di model & master:install-tables hanya membuat tabel yang belum ada, dan sync hanya menyalin kolom
 * yang ada di tabel lokal. Upgrade di sini dipanggil dari migration, model, sync, installer, dan import supaya
 * tabel lama di DB mana pun ikut ter-upgrade. Hanya perubahan aman: tambah kolom / ganti index, tanpa hapus data.
 */
class MasterSchemaUpgrader
{
    /** tabel => method upgrade */
    public const UPGRADES = [
        'master_department' => 'upgradeDepartmentAppCode',
    ];

    /** Tabel yang sudah dicek di proses ini (hindari query skema berulang). */
    protected static array $ensured = [];

    /**
     * Upgrade satu tabel bila terdaftar; aman dipanggil berulang (dicek sekali per proses).
     *
     * @return list<string> perubahan yang dilakukan
     */
    public function ensure(string $table): array
    {
        if (! isset(self::UPGRADES[$table]) || isset(self::$ensured[$table])) {
            return [];
        }
        self::$ensured[$table] = true;

        try {
            return $this->{self::UPGRADES[$table]}();
        } catch (Throwable $e) {
            unset(self::$ensured[$table]); // coba lagi di pemanggilan berikutnya
            report($e);

            return [];
        }
    }

    /**
     * Upgrade semua tabel terdaftar yang ada.
     *
     * @return array<string, list<string>> tabel => perubahan (hanya yang berubah)
     */
    public function upgradeAll(): array
    {
        $result = [];
        foreach (array_keys(self::UPGRADES) as $table) {
            unset(self::$ensured[$table]);
            $changes = $this->ensure($table);
            if ($changes !== []) {
                $result[$table] = $changes;
            }
        }

        return $result;
    }

    /** Reset memo per proses (dipakai test / setelah migration). */
    public static function flush(): void
    {
        self::$ensured = [];
    }

    /**
     * master_department: tambah app_code (pemilik data, seperti master_item_code), unik department_code -> (department_code, app_code).
     *
     * @return list<string>
     */
    public function upgradeDepartmentAppCode(): array
    {
        $table = 'master_department';
        if (! Schema::hasTable($table)) {
            return [];
        }

        $changes = [];
        if (! Schema::hasColumn($table, 'app_code')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('app_code', 10)->default('APP03')->after('department_name');
                $blueprint->index('app_code', 'idx_dept_app_code');
            });
            $changes[] = 'tambah kolom app_code';
        }

        $indexes = $this->uniqueIndexes($table);
        $drop = array_keys(array_filter($indexes, fn ($columns) => $columns === ['department_code']));
        // index unik apa pun yang sudah memuat department_code + app_code (termasuk hasil master:scope-upgrade yang
        // menambah company_id) dianggap cukup; jangan dikembalikan ke (department_code, app_code)
        $add = array_filter($indexes, fn ($columns) => in_array('department_code', $columns, true) && in_array('app_code', $columns, true)) === [];

        if ($drop !== [] || $add) {
            Schema::table($table, function (Blueprint $blueprint) use ($drop, $add) {
                foreach ($drop as $name) {
                    $blueprint->dropUnique($name);
                }
                if ($add) {
                    $blueprint->unique(['department_code', 'app_code'], 'uq_dept_code_app');
                }
            });
            $changes[] = 'unik department_code -> (department_code, app_code)';
        }

        return $changes;
    }

    /**
     * Unique index (selain primary) => daftar kolom berurutan, untuk MySQL/MariaDB dan SQLite.
     *
     * @return array<string, list<string>>
     */
    public function uniqueIndexes(string $table): array
    {
        $result = [];

        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
                if ((int) $index->unique === 1 && $index->origin !== 'pk') {
                    $result[$index->name] = array_map(fn ($c) => $c->name, DB::select("PRAGMA index_info('{$index->name}')"));
                }
            }

            return $result;
        }

        foreach (DB::select("SHOW INDEX FROM `{$table}`") as $row) {
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
}
