<?php

use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * master_department: tambah app_code (pemilik data, sama seperti master_item_code)
 * dan ganti kunci unik department_code -> (department_code, app_code).
 *
 * Aman untuk data: hanya menambah kolom & mengganti index; baris lama mendapat app_code default APP03.
 * Logika upgrade ada di MasterSchemaUpgrader (dipakai juga oleh model, sync, dan master:install-tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MasterSchemaUpgrader::class)->upgradeDepartmentAppCode();
    }

    public function down(): void
    {
        $upgrader = app(MasterSchemaUpgrader::class);
        if (! Schema::hasTable('master_department') || ! isset($upgrader->uniqueIndexes('master_department')['uq_dept_code_app'])) {
            return;
        }

        // Kolom app_code sengaja tidak dihapus (menghindari kehilangan data); hanya kunci unik dikembalikan.
        Schema::table('master_department', function (Blueprint $table) {
            $table->dropUnique('uq_dept_code_app');
            $table->unique('department_code', 'uq_dept_code');
        });
        MasterSchemaUpgrader::flush();
    }
};
