<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Services\MasterSchemaStatus;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Banner struktur tabel master lokal (master::partials.schema-notice): pengelola aplikasi melihat tabel yang belum
 * dinaikkan / kolom DB master yang belum ada, beserta perintah docker exec + artisan perbaikannya; perintah
 * master:scope-upgrade dan master:schema-columns membereskannya dan banner hilang.
 */
class MasterSchemaNoticeTest extends TestCase
{
    use CreatesMasterTables {
        defineEnvironment as baseEnvironment;
    }

    private string $masterDb;

    protected function defineEnvironment($app)
    {
        $this->baseEnvironment($app);

        $this->masterDb = sys_get_temp_dir().'/bangsamu-master-schema-notice-'.getmypid().'.sqlite';
        File::put($this->masterDb, '');
        $app['config']->set('database.connections.db_master', [
            'driver' => 'sqlite',
            'database' => $this->masterDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('MasterCrudConfig.SCHEMA_NOTICE_PERMISSION', 'is_root');
        $app['config']->set('MasterCrudConfig.DOCKER_CONTAINER', 'spb-php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->createDashboardSettings();
        MasterDataSyncService::flushSchemaCache();
        MasterSchemaStatus::flush();
        Schema::connection('db_master')->create('master_uom', function (Blueprint $table) {
            $table->id();
            $table->string('uom_code');
            $table->string('uom_name');
            $table->string('app_code', 10)->default('APP03');
            $table->unsignedInteger('company_id')->default(0);
            $table->string('uom_symbol', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        MasterDataSyncService::setAllowedSyncTables(['master_uom']);
        $this->actingAs(User::create(['name' => 'Root', 'email' => 'root@meindo.test']));
    }

    protected function tearDown(): void
    {
        MasterDataSyncService::flushSchemaCache();
        parent::tearDown();
        File::delete($this->masterDb);
    }

    private function notice(): string
    {
        return trim(view('master::partials.schema-notice')->render());
    }

    public function test_pengelola_melihat_tabel_tertinggal_dan_perintah_perbaikan(): void
    {
        config(['test.allowed_permissions' => ['is_root']]);

        $issues = app(MasterSchemaStatus::class)->detect();
        $this->assertArrayHasKey('master_uom', $issues['scope']);
        // company_id diurus scope-upgrade, tidak dihitung kolom yang kurang
        $this->assertSame(['master_uom' => ['uom_symbol']], $issues['columns']);

        $html = $this->notice();
        $this->assertStringContainsString('id="master-schema-notice"', $html);
        // -u pemilik file artisan (bukan root) bila bisa dibaca
        $this->assertMatchesRegularExpression('#docker exec( -u \d+:\d+ -e HOME=/tmp)? -w '.preg_quote(base_path(), '#').' spb-php php artisan master:scope-upgrade --dry-run#', $html);
        $this->assertStringContainsString('spb-php php artisan master:schema-columns', $html);
        $this->assertStringContainsString('vendor/bin/master-scope-setup --skip-composer', $html);
        $this->assertStringContainsString('uom_symbol', $html);
    }

    public function test_bukan_pengelola_tidak_melihat_banner(): void
    {
        config(['test.allowed_permissions' => ['is_admin']]);

        $this->assertSame('', $this->notice());
    }

    public function test_perintah_perbaikan_menghilangkan_banner(): void
    {
        config(['test.allowed_permissions' => ['is_root']]);
        $this->assertNotSame('', $this->notice()); // hasil cek tersimpan di cache

        Artisan::call('master:schema-columns', ['--dry-run' => true]);
        $this->assertFalse(Schema::hasColumn('master_uom', 'uom_symbol'), '--dry-run tidak mengubah tabel');

        Artisan::call('master:scope-upgrade');
        $this->assertSame(0, Artisan::call('master:schema-columns'));

        $this->assertTrue(Schema::hasColumn('master_uom', 'company_id'));
        $this->assertTrue(Schema::hasColumn('master_uom', 'uom_symbol'));
        $this->assertFalse(app(MasterSchemaStatus::class)->hasIssues());
        $this->assertSame('', $this->notice());
    }

    public function test_hub_tidak_membandingkan_kolom_dengan_db_master(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => true]);

        $this->assertSame([], app(MasterSchemaStatus::class)->detect()['columns']);
        Artisan::call('master:schema-columns');
        $this->assertStringContainsString('memakai DB master sendiri', Artisan::output());
        $this->assertFalse(Schema::hasColumn('master_uom', 'uom_symbol'));
    }
}
