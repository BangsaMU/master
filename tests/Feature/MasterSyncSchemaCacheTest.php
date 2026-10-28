<?php

namespace Bangsamu\Master\Tests\Feature;

use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * getAllowedSyncTables() dipanggil komponen broadcast-listener di setiap halaman. Cek keberadaan tabel
 * (information_schema / sqlite_master) cukup sekali lalu di-cache, bukan 1 + jumlah tabel whitelist per render.
 */
class MasterSyncSchemaCacheTest extends TestCase
{
    /** @var list<string> */
    private array $schemaQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['master_uom', 'master_project'] as $tableName) {
            Schema::create($tableName, fn (Blueprint $table) => $table->id());
        }
        MasterDataSyncService::ensureDashboardSettingsTable();
        DB::table('dashboard_settings')->insert([
            'key' => MasterDataSyncService::SETTING_KEY_ALLOWED_TABLES,
            'value' => json_encode(['master_uom', 'master_project', 'master_location']),
            'group' => MasterDataSyncService::SETTING_GROUP_SENADA_SYNC,
            'type' => 'json',
        ]);

        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/information_schema|sqlite_master/i', $query->sql)) {
                $this->schemaQueries[] = $query->sql;
            }
        });
    }

    public function test_cek_tabel_hanya_sekali_lalu_dari_cache(): void
    {
        $this->assertSame(['master_uom', 'master_project'], MasterDataSyncService::getAllowedSyncTables());
        $this->assertNotSame([], $this->schemaQueries);

        $this->schemaQueries = [];
        $this->assertSame(['master_uom', 'master_project'], MasterDataSyncService::getAllowedSyncTables());
        $this->assertSame([], $this->schemaQueries);
    }

    public function test_whitelist_yang_diubah_langsung_dipakai(): void
    {
        MasterDataSyncService::getAllowedSyncTables();

        DB::table('dashboard_settings')->where('key', MasterDataSyncService::SETTING_KEY_ALLOWED_TABLES)
            ->update(['value' => json_encode(['master_project'])]);

        $this->assertSame(['master_project'], MasterDataSyncService::getAllowedSyncTables());
    }

    public function test_tabel_baru_ikut_setelah_cache_dikosongkan(): void
    {
        MasterDataSyncService::getAllowedSyncTables();
        Schema::create('master_location', fn (Blueprint $table) => $table->id());

        // tanpa flush: hasil lama dari cache (maks. SCHEMA_CACHE_TTL)
        $this->assertSame(['master_uom', 'master_project'], MasterDataSyncService::getAllowedSyncTables());

        MasterDataSyncService::flushSchemaCache();
        $this->assertSame(['master_uom', 'master_project', 'master_location'], MasterDataSyncService::getAllowedSyncTables());
    }
}
