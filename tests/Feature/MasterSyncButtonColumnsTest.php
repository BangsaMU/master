<?php

namespace Bangsamu\Master\Tests\Feature;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Tombol Sync halaman master (GET api/master-{tabel}, insert/update library-clay): kolom yang hanya ada di DB master
 * (mis. company_id sebelum master:scope-upgrade lokal) tidak ikut disalin. Dulu baris itu gagal "Unknown column".
 */
class MasterSyncButtonColumnsTest extends TestCase
{
    use CreatesMasterTables {
        defineEnvironment as baseEnvironment;
    }

    private string $masterDb;

    protected function defineEnvironment($app)
    {
        $this->baseEnvironment($app);

        $this->masterDb = sys_get_temp_dir().'/bangsamu-master-sync-btn-'.getmypid().'.sqlite';
        File::put($this->masterDb, '');
        $app['config']->set('database.connections.db_master', [
            'driver' => 'sqlite',
            'database' => $this->masterDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('SsoConfig.main.KEY', 'kunci-sync-test');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('db_master')->create('master_uom', function (Blueprint $table) {
            $table->id();
            $table->string('uom_code');
            $table->string('uom_name');
            $table->string('app_code', 10)->default('APP03');
            $table->unsignedInteger('company_id')->default(0);
            $table->string('uom_symbol')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::connection('db_master')->table('master_uom')->insert([
            ['id' => 1, 'uom_code' => 'KG', 'uom_name' => 'Kilogram baru', 'company_id' => 0, 'uom_symbol' => 'kg'],
            ['id' => 2, 'uom_code' => 'M', 'uom_name' => 'Meter', 'company_id' => 0, 'uom_symbol' => 'm'],
        ]);
        // tabel lokal tertinggal: tanpa company_id dan uom_symbol
        DB::table('master_uom')->insert(['id' => 1, 'uom_code' => 'KG', 'uom_name' => 'Kilogram']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::delete($this->masterDb);
    }

    public function test_sync_seluruh_tabel_melewati_kolom_yang_belum_ada_di_lokal(): void
    {
        $response = $this->getJson('/api/master-uom?_token='.LibraryClayController::api_token(null))->assertOk();

        $this->assertStringNotContainsString('gagal', $response->getContent());
        $this->assertSame('Kilogram baru', DB::table('master_uom')->where('id', 1)->value('uom_name'));
        $this->assertSame('Meter', DB::table('master_uom')->where('id', 2)->value('uom_name'));
        $this->assertFalse(Schema::hasColumn('master_uom', 'uom_symbol'));
    }

    public function test_sync_satu_baris_melewati_kolom_yang_belum_ada_di_lokal(): void
    {
        $response = $this->getJson('/api/master-uom/2?_token='.LibraryClayController::api_token(null))->assertOk();

        $this->assertStringNotContainsString('gagal', $response->getContent());
        $this->assertSame('Meter', DB::table('master_uom')->where('id', 2)->value('uom_name'));
    }
}
