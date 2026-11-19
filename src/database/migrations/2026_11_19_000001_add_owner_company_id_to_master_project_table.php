<?php

use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * master_project: tambah owner_company_id = company pemilik project (project non-Meindo). NULL = pemilik bawaan
 * (MasterCrudConfig.PROJECT_OWNER_COMPANY_ID), jadi baris lama tidak diubah.
 *
 * Logika upgrade ada di MasterSchemaUpgrader (dipakai juga oleh sync dan master:install-tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MasterSchemaUpgrader::class)->upgradeProjectOwnerCompany();
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_project') || ! Schema::hasColumn('master_project', 'owner_company_id')) {
            return;
        }

        // Kolom hanya dihapus bila belum ada project non-Meindo (menghindari kehilangan pemilik project).
        if (\Illuminate\Support\Facades\DB::table('master_project')->whereNotNull('owner_company_id')->exists()) {
            return;
        }

        Schema::table('master_project', function (Blueprint $table) {
            $table->dropIndex('idx_project_owner_company');
            $table->dropColumn('owner_company_id');
        });
        MasterSchemaUpgrader::flush();
    }
};
