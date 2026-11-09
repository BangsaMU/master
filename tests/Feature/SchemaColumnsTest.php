<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterDataSyncService;
use Bangsamu\Master\Services\SchemaColumns;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cek tabel/kolom di jalur panas (dropdown api/getmaster_*byparams, daftar master, boot model) memakai SchemaColumns:
 * sekali query skema per tabel lalu dari memo request dan cache lintas request, bukan query information_schema tiap cek.
 */
class SchemaColumnsTest extends TestCase
{
    /** @var list<string> */
    private array $schemaQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('master_uji', function (Blueprint $table) {
            $table->id();
            $table->string('kode_uji');
        });

        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/information_schema|sqlite_master|pragma_table/i', $query->sql)) {
                $this->schemaQueries[] = $query->sql;
            }
        });
    }

    /** Request berikutnya: instance scoped dibuat ulang, memo kosong, cache tetap. */
    private function nextRequest(): void
    {
        $this->app->forgetScopedInstances();
        $this->schemaQueries = [];
    }

    public function test_cek_berulang_satu_query_lalu_memo_lalu_cache(): void
    {
        $this->assertTrue(SchemaColumns::hasTable('master_uji'));
        $this->assertNotSame([], $this->schemaQueries);

        $this->schemaQueries = [];
        $this->assertTrue(SchemaColumns::hasColumn('master_uji', 'KODE_UJI')); // tidak peka huruf besar/kecil
        $this->assertFalse(SchemaColumns::hasColumn('master_uji', 'tidak_ada'));
        $this->assertSame(['id', 'kode_uji'], SchemaColumns::listing('master_uji'));
        $this->assertSame([], $this->schemaQueries, 'request yang sama: dari memo');

        $this->nextRequest();
        $this->assertTrue(SchemaColumns::hasColumn('master_uji', 'kode_uji'));
        $this->assertSame([], $this->schemaQueries, 'request berikutnya: dari cache');
    }

    public function test_tabel_yang_belum_ada_tidak_di_cache(): void
    {
        $this->assertFalse(SchemaColumns::hasTable('master_baru'));

        Schema::create('master_baru', fn (Blueprint $table) => $table->id());

        $this->assertTrue(SchemaColumns::hasTable('master_baru'));
    }

    public function test_kolom_baru_terbaca_setelah_migrate_atau_flush(): void
    {
        SchemaColumns::listing('master_uji');
        Schema::table('master_uji', fn (Blueprint $table) => $table->string('app_code')->nullable());

        $this->nextRequest();
        $this->assertFalse(SchemaColumns::hasColumn('master_uji', 'app_code'), 'tanpa flush: daftar lama dari cache (maks. TTL)');

        event(new MigrationsEnded('up'));
        $this->assertTrue(SchemaColumns::hasColumn('master_uji', 'app_code'));

        Schema::table('master_uji', fn (Blueprint $table) => $table->string('company_id')->nullable());
        MasterDataSyncService::flushSchemaCache(); // installer / master:scope-upgrade / master:schema-columns
        $this->nextRequest();
        $this->assertTrue(SchemaColumns::hasColumn('master_uji', 'company_id'));
    }

    /** Nama kolom dari parameter request yang tidak dikirim (null) = false seperti Schema::hasColumn, bukan TypeError. */
    public function test_nama_kolom_bukan_string_atau_kosong_dianggap_tidak_ada(): void
    {
        $this->assertFalse(SchemaColumns::hasColumn('master_uji', null));
        $this->assertFalse(SchemaColumns::hasColumn('master_uji', ''));
        $this->assertFalse(SchemaColumns::hasColumn('master_uji', ['kode_uji']));
        $this->assertTrue(SchemaColumns::hasColumn('master_uji', 'kode_uji'));
    }

    public function test_ttl_nol_hanya_memo_per_request(): void
    {
        config(['MasterConfig.schema_cache_ttl' => 0]);

        SchemaColumns::listing('master_uji');
        $this->nextRequest();
        SchemaColumns::listing('master_uji');

        $this->assertNotSame([], $this->schemaQueries);
    }

    public function test_dropdown_master_tidak_query_skema_di_request_berikutnya(): void
    {
        Schema::create('master_department', function (Blueprint $table) {
            $table->id();
            $table->string('department_code');
            $table->string('department_name');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('master_department')->insert(['department_code' => 'IT', 'department_name' => 'Dept IT']);
        $this->actingAs(User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']));
        $url = '/api/getmaster_departmentbyparams?'.http_build_query(['set' => ['text' => 'department_name', 'field' => ['department_code']]]);

        $this->getJson($url)->assertOk()->assertJsonFragment(['department_code' => 'IT']);
        $this->assertNotSame([], $this->schemaQueries);

        $this->nextRequest();
        $this->getJson($url)->assertOk()->assertJsonFragment(['department_code' => 'IT']);
        $this->assertSame([], $this->schemaQueries);
    }
}
