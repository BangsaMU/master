<?php

namespace Bangsamu\Master\Tests\Feature;

use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\MasterTableInstaller;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MasterSchemaUpgraderTest extends TestCase
{
    use CreatesMasterTables {
        defineEnvironment as baseEnvironment;
    }

    private string $masterDb;

    protected function defineEnvironment($app)
    {
        $this->baseEnvironment($app);

        // DB master terpisah (file sqlite) supaya sync benar-benar menyalin antar database
        $this->masterDb = sys_get_temp_dir().'/bangsamu-master-db-'.getmypid().'.sqlite';
        File::put($this->masterDb, '');
        $app['config']->set('database.connections.db_master', [
            'driver' => 'sqlite',
            'database' => $this->masterDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::delete($this->masterDb);
    }

    public function test_upgrade_tabel_lama_aman_dan_sekali_per_proses(): void
    {
        DB::table('master_department')->insert(['department_code' => 'ACC', 'department_name' => 'Accounting']);
        $upgrader = app(MasterSchemaUpgrader::class);

        $this->assertSame(['tambah kolom app_code', 'unik department_code -> (department_code, app_code)'], $upgrader->ensure('master_department'));
        $this->assertSame([], $upgrader->ensure('master_department'), 'tidak dicek ulang di proses yang sama');
        $this->assertSame([], $upgrader->upgradeAll(), 'tabel yang sudah terbaru tidak berubah');

        $this->assertSame('APP03', DB::table('master_department')->value('app_code'));
        $this->assertSame(['uq_dept_code_app' => ['department_code', 'app_code']], $upgrader->uniqueIndexes('master_department'));
        $this->assertSame([], $upgrader->ensure('master_tidak_terdaftar'));
    }

    public function test_sync_mengupgrade_tabel_lokal_lama_dan_menyalin_app_code(): void
    {
        Http::fake();
        Schema::connection('db_master')->create('master_department', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 15);
            $table->string('department_name', 100);
            $table->string('app_code', 10)->default('APP03');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['department_code', 'app_code'], 'uq_dept_code_app');
        });
        DB::connection('db_master')->table('master_department')->insert([
            ['id' => 1, 'department_code' => 'ACC', 'department_name' => 'Accounting', 'app_code' => 'APP03'],
            ['id' => 2, 'department_code' => 'ACC', 'department_name' => 'Accounting APP09', 'app_code' => 'APP09'],
        ]);
        MasterDataSyncService::setAllowedSyncTables(['master_department']);

        // tabel lokal masih skema lama: unik hanya department_code, tanpa app_code
        $result = app(MasterDataSyncService::class)->syncTable('master_department', null, 2);

        $this->assertTrue($result['success']);
        $this->assertTrue(Schema::hasColumn('master_department', 'app_code'));
        $this->assertSame(
            [['ACC', 'APP03'], ['ACC', 'APP09']],
            DB::table('master_department')->orderBy('id')->get()->map(fn ($r) => [$r->department_code, $r->app_code])->all()
        );
    }

    public function test_sync_menyalin_yearly_reset_dan_pemilik_project(): void
    {
        Http::fake();
        MasterDataSyncService::flushSchemaCache();
        Schema::connection('db_master')->create('master_project', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 10);
            $table->string('project_name', 116)->nullable();
            $table->integer('owner_company_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::connection('db_master')->create('master_project_detail', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->string('project_code_client', 10)->nullable();
            $table->string('project_name_client', 100)->nullable();
            $table->boolean('number_reset_yearly')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::connection('db_master')->table('master_project')->insert([
            ['id' => 1, 'project_code' => '21000', 'project_name' => 'MEINDO', 'owner_company_id' => null],
            ['id' => 2, 'project_code' => '73100', 'project_name' => 'Project MEB', 'owner_company_id' => 647],
        ]);
        DB::connection('db_master')->table('master_project_detail')->insert([
            ['id' => 1, 'project_id' => 1, 'company_id' => 1, 'project_code_client' => null, 'number_reset_yearly' => 1],
            ['id' => 2, 'project_id' => 2, 'company_id' => 647, 'project_code_client' => '73100', 'number_reset_yearly' => 0],
        ]);
        // baris lama di app klien: penanda masih 0 (sebelum v3.16.1 kolom ini tidak ikut sync)
        DB::table('master_project')->insert(['id' => 1, 'project_code' => '21000', 'project_name' => 'MEINDO']);
        DB::table('master_project_detail')->insert(['id' => 1, 'project_id' => 1, 'company_id' => 1, 'number_reset_yearly' => 0]);
        MasterDataSyncService::setAllowedSyncTables(['master_project', 'master_project_detail']);
        $sync = app(MasterDataSyncService::class);

        // siaran perubahan baris yang sudah ada (id 1) sekaligus baris baru sampai id 2
        $sync->syncTable('master_project_detail', 1, 2);
        $sync->syncTable('master_project', 1, 2);

        $this->assertSame([1, 0], DB::table('master_project_detail')->orderBy('id')->pluck('number_reset_yearly')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([null, 647], DB::table('master_project')->orderBy('id')->pluck('owner_company_id')->map(fn ($value) => $value === null ? null : (int) $value)->all());
    }

    public function test_installer_melaporkan_tabel_yang_diupgrade(): void
    {
        $result = app(MasterTableInstaller::class)->install();

        $this->assertArrayHasKey('master_department', $result['upgraded']);
        $this->assertTrue(Schema::hasColumn('master_department', 'app_code'));
    }
}
