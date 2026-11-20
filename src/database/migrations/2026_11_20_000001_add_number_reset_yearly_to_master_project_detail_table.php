<?php

use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\ProjectDetailNumberReset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * master_project_detail: tambah number_reset_yearly = penanda reset nomor tahunan per project × company (bawaan 0 =
 * tidak reset), jadi baris lama tidak diubah.
 *
 * Logika upgrade ada di MasterSchemaUpgrader (dipakai juga oleh sync dan master:install-tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MasterSchemaUpgrader::class)->upgradeProjectDetailNumberReset();
    }

    public function down(): void
    {
        $table = ProjectDetailNumberReset::TABLE;
        $column = ProjectDetailNumberReset::COLUMN;
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        // Kolom hanya dihapus bila belum ada baris bertanda reset tahunan (menghindari kehilangan penanda).
        if (DB::table($table)->where($column, 1)->exists()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });
        MasterSchemaUpgrader::flush();
    }
};
