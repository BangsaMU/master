<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Exports\DataExport;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Services\MasterFilterGroups;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** company pengguna SPB (MEB, ENL) sebagai class invokable untuk RELATION_FILTERS.exclusive */
class RelationFilterExclusiveCompanies
{
    /** @return list<int> */
    public function __invoke(): array
    {
        return [647, 648];
    }
}

/**
 * Filter relasi (MasterCrudConfig.RELATION_FILTERS, Services\MasterFilterGroups): baris Setting master_project
 * `company_id` = {USER_COMPANY} menyaring project lewat master_project_detail, karena master_project tidak punya
 * kolom company_id.
 *  - company biasa: project yang punya baris detail aktif company itu;
 *  - company pemilik (owner): barisnya ada di semua project, jadi project yang juga punya baris company `exclusive`
 *    tidak ikut;
 *  - selalu AND: baris Setting lain tidak melonggarkannya;
 *  - config kosong = perilaku lama (baris Setting ber-kolom tidak dikenal diabaikan).
 * Berlaku di daftar master/project, dropdown api/getmaster_projectbyparams, dan export tabel.
 */
class RelationFilterTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;      // company pemilik

    private const KLIEN = 116; // company klien (bukan pengguna SPB)

    private const MEB = 647;

    private const ENL = 648;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        // export membaca koneksi db_master: di test satu database dengan koneksi default
        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('MasterCrudConfig.SELECT2_SETTING_FILTER', true);
        $app['config']->set('MasterCrudConfig.RELATION_FILTERS', ['master_project' => ['company_id' => [
            'table' => 'master_project_detail', 'local' => 'id', 'foreign' => 'project_id',
            'owner' => self::ME, 'exclusive' => [self::MEB, self::ENL],
        ]]]);
    }

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->createDashboardSettings();
        DB::connection('db_master')->setPdo(DB::connection()->getPdo());

        // P-ME: hanya pemilik; P-KLIEN: pemilik + klien; P-MEB: pemilik + MEB; P-ENL: pemilik + ENL;
        // P-BEKAS: pemilik + baris MEB yang sudah dihapus; P-KOSONG: tanpa baris detail
        $this->project('P-ME', [self::ME]);
        $this->project('P-KLIEN', [self::ME, self::KLIEN]);
        $this->project('P-MEB', [self::ME, self::MEB]);
        $this->project('P-ENL', [self::ME, self::ENL]);
        $this->project('P-BEKAS', [self::ME], terhapus: [self::MEB]);
        $this->project('P-KOSONG', []);
    }

    /**
     * @param  list<int>  $companies  baris detail aktif
     * @param  list<int>  $terhapus  baris detail soft delete
     */
    private function project(string $kode, array $companies, array $terhapus = []): int
    {
        $id = DB::table('master_project')->insertGetId(['project_code' => $kode, 'project_name' => 'Project '.$kode]);
        foreach ([[$companies, null], [$terhapus, now()]] as [$daftar, $deletedAt]) {
            foreach ($daftar as $company) {
                DB::table('master_project_detail')->insert([
                    'project_id' => $id, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Project '.$kode,
                    'deleted_at' => $deletedAt,
                ]);
            }
        }

        return $id;
    }

    /** user biasa (bukan admin, izin baca project) dengan company di user_details = isi {USER_COMPANY} */
    private function loginCompany(int $company): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => ['read_project']]);
        $user = User::create(['name' => 'Rani Wibowo', 'email' => 'rani'.uniqid().'@meindo.test']);
        DB::table('user_details')->insert(['user_id' => $user->id, 'user_email' => $user->email, 'field_key' => 'company_id', 'field_value' => (string) $company]);
        MasterScope::flush();
        $this->actingAs($user);
    }

    private function setting(string $name, string $value): void
    {
        DB::table('setting')->insert(['category' => 'master_project', 'name' => $name, 'value' => $value]);
    }

    /** @return list<string> kode project di daftar master/project */
    private function daftar(): array
    {
        $json = $this->getJson('/master/project', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
        $kode = collect($json['data'])->pluck('project_code')->sort()->values()->all();
        $this->assertSame(count($kode), $json['recordsTotal'], 'recordsTotal ikut filter');

        return $kode;
    }

    /** @return list<string> kode project di dropdown select2 */
    private function dropdown(array $query = []): array
    {
        $response = $this->getJson('/api/getmaster_projectbyparams?'.http_build_query(
            $query + ['limit' => 50, 'set' => ['text' => 'project_name', 'field' => ['project_code']]]
        ))->assertOk();

        return collect($response->json())->pluck('project_code')->filter()->sort()->values()->all();
    }

    /** @return list<string> kode project di export tabel */
    private function export(): array
    {
        $export = new DataExport;
        $export->setTable('master_project');

        return $export->collection()->pluck('project_code')->sort()->values()->all();
    }

    // ---- hanya IN ----

    public function test_company_bukan_pemilik_hanya_melihat_project_ber_baris_detail_aktifnya(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->daftar());

        $this->loginCompany(self::ENL);
        $this->assertSame(['P-ENL'], $this->daftar());
    }

    public function test_baris_detail_soft_delete_tidak_dihitung(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');

        // P-BEKAS: baris MEB-nya sudah dihapus, jadi bukan project MEB lagi dan kembali tampil untuk pemilik
        $this->loginCompany(self::MEB);
        $this->assertNotContains('P-BEKAS', $this->daftar());

        $this->loginCompany(self::ME);
        $this->assertContains('P-BEKAS', $this->daftar());
    }

    // ---- pengecualian company pemilik ----

    public function test_pemilik_tidak_melihat_project_ber_baris_company_exclusive(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::ME);

        // P-KLIEN tetap tampil: company klien bukan exclusive; P-KOSONG tidak punya baris pemilik
        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $this->daftar());
    }

    public function test_nilai_berisi_pemilik_dan_satu_exclusive_hanya_mengecualikan_exclusive_sisanya(): void
    {
        // V = [1, 647]: IN (1, 647) AND NOT IN (648)
        $this->setting('company_id', self::ME.','.self::MEB);
        $this->loginCompany(self::ME);

        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME', 'P-MEB'], $this->daftar());
    }

    public function test_tanpa_exclusive_atau_tanpa_owner_tidak_ada_pengecualian(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::ME);
        $semuaBerbarisPemilik = ['P-BEKAS', 'P-ENL', 'P-KLIEN', 'P-ME', 'P-MEB'];

        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => []]);
        $this->assertSame($semuaBerbarisPemilik, $this->daftar());

        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => [self::MEB, self::ENL]]);
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.owner' => null]);
        $this->assertSame($semuaBerbarisPemilik, $this->daftar());
    }

    public function test_exclusive_berupa_class_invokable(): void
    {
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => RelationFilterExclusiveCompanies::class]);
        $this->setting('company_id', '{USER_COMPANY}');

        $this->loginCompany(self::ME);
        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $this->daftar());

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->daftar());
    }

    public function test_exclusive_berupa_closure(): void
    {
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => fn () => [self::ENL]]);
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::ME);

        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME', 'P-MEB'], $this->daftar());
    }

    public function test_exclusive_berupa_teks_angka_dipisah_koma(): void
    {
        // bentuk yang datang dari .env: "647, 648"
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => self::MEB.', '.self::ENL]);
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::ME);

        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $this->daftar());
    }

    public function test_exclusive_berupa_nama_class_yang_tidak_ada_ditolak_bukan_diabaikan(): void
    {
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.exclusive' => 'App\\Tidak\\Ada']);

        $this->expectException(InvalidArgumentException::class);

        MasterFilterGroups::apply(DB::table('master_project'), 'master_project', [['company_id' => [(string) self::ME]]]);
    }

    public function test_baris_detail_tanpa_project_id_tidak_mengosongkan_daftar_pemilik(): void
    {
        // NOT IN (..., NULL) bernilai NULL untuk semua baris: subquery wajib membuang project_id NULL
        DB::table('master_project_detail')->insert(['project_id' => null, 'company_id' => self::MEB, 'project_code_client' => 'YATIM']);
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::ME);

        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $this->daftar());
    }

    // ---- selalu AND ----

    public function test_baris_setting_lain_tidak_melonggarkan_filter_relasi(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');
        // baris kolom biasa yang cocok dengan semua project: dengan OR antar baris, filter company akan hilang
        $this->setting('project_code', 'P-ME,P-KLIEN,P-MEB,P-ENL,P-BEKAS,P-KOSONG');

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->daftar());
        $this->assertSame(['P-MEB'], $this->dropdown());
    }

    public function test_baris_setting_lain_tetap_mempersempit_di_dalam_filter_relasi(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');
        $this->setting('project_code', 'P-ME,P-MEB');

        $this->loginCompany(self::ME);
        $this->assertSame(['P-ME'], $this->daftar());
    }

    public function test_kolom_relasi_di_baris_scope_dikeluarkan_menjadi_syarat_and(): void
    {
        // baris scope: kolom biasa + kolom relasi. Kolom relasi berlaku untuk semua baris, sisa kolom tetap satu
        // kelompok OR bersama baris `project_name`
        $this->setting('scope.spb', json_encode(['project_code' => ['P-MEB'], 'company_id' => ['{USER_COMPANY}']]));
        $this->setting('project_name', 'Project P-ENL,Project P-ME');

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->daftar());

        // pemilik: (project_code = P-MEB OR project_name IN (...)) AND milik pemilik -> P-ME saja
        $this->loginCompany(self::ME);
        $this->assertSame(['P-ME'], $this->daftar());
    }

    public function test_baris_scope_yang_hanya_berisi_kolom_relasi_tidak_menjadi_kelompok_lolos_semua(): void
    {
        $this->setting('scope.company', json_encode(['company_id' => ['{USER_COMPANY}']]));
        $this->setting('project_code', 'P-ENL');

        // tanpa membuang kelompok kosong: (kosong OR project_code = P-ENL) = semua baris
        $this->loginCompany(self::ENL);
        $this->assertSame(['P-ENL'], $this->daftar());

        $this->loginCompany(self::MEB);
        $this->assertSame([], $this->daftar());
    }

    // ---- perilaku lama ----

    public function test_config_kosong_baris_setting_company_id_diabaikan_seperti_dulu(): void
    {
        config(['MasterCrudConfig.RELATION_FILTERS' => []]);
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::MEB);
        $semua = ['P-BEKAS', 'P-ENL', 'P-KLIEN', 'P-KOSONG', 'P-ME', 'P-MEB'];

        $this->assertSame($semua, $this->daftar());
        $this->assertSame($semua, $this->dropdown());
        $this->assertSame($semua, $this->export());
        $this->assertFalse(MasterFilterGroups::isRelationColumn('master_project', 'company_id'));

        // baris kolom biasa tetap OR antar baris
        $this->setting('project_code', 'P-ME');
        $this->setting('project_name', 'Project P-ENL');
        $this->assertSame(['P-ENL', 'P-ME'], $this->daftar());
    }

    public function test_kolom_tidak_dikenal_dan_tidak_terdaftar_tetap_diabaikan(): void
    {
        $this->setting('tidak_ada', 'x');
        $this->setting('scope.salah', json_encode(['project_code' => ['P-ME'], 'tidak_ada' => ['x']]));
        $this->loginCompany(self::MEB);

        $this->assertCount(6, $this->daftar());
        $this->assertFalse(MasterFilterGroups::isRelationColumn('master_project', 'tidak_ada'));
        $this->assertFalse(MasterFilterGroups::isRelationColumn('master_location', 'company_id'));
        $this->assertTrue(MasterFilterGroups::isRelationColumn('master_project', 'company_id'));
    }

    public function test_tabel_relasi_tanpa_kolomnya_menutup_daftar_bukan_meloloskan_semua(): void
    {
        config(['MasterCrudConfig.RELATION_FILTERS.master_project.company_id.table' => 'master_location']);
        $this->setting('company_id', '{USER_COMPANY}');
        $this->loginCompany(self::MEB);

        $this->assertSame([], $this->daftar());
    }

    // ---- dropdown dan export ----

    public function test_dropdown_select2_ikut_filter_relasi(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->dropdown());
        // pencarian select2 tetap di dalam filter: project company lain tidak ketemu walau kodenya dicari
        $this->assertSame([], $this->dropdown(['search' => ['project_code' => 'KLIEN']]));
        $this->assertSame(['P-MEB'], $this->dropdown(['search' => ['project_code' => 'MEB']]));

        $this->loginCompany(self::ME);
        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $this->dropdown());

        config(['MasterCrudConfig.SELECT2_SETTING_FILTER' => false]);
        $this->assertCount(6, $this->dropdown());
    }

    public function test_export_tabel_ikut_filter_relasi(): void
    {
        $this->setting('company_id', '{USER_COMPANY}');
        // baris kolom biasa di export digabung OR seperti dulu, tetapi tidak melonggarkan filter relasi
        $this->setting('project_code', 'P-ME,P-MEB,P-ENL');

        $this->loginCompany(self::MEB);
        $this->assertSame(['P-MEB'], $this->export());

        $this->loginCompany(self::ME);
        $this->assertSame(['P-ME'], $this->export());
    }

    // ---- API publik untuk app ----

    public function test_apply_dipakai_langsung_dengan_alias_dan_kelompok_yang_sudah_diganti_nilainya(): void
    {
        $kode = function (array $groups): array {
            $query = DB::table('master_project as t')->whereNull('t.deleted_at');
            MasterFilterGroups::apply($query, 'master_project', $groups, 't');

            return $query->orderBy('t.project_code')->pluck('t.project_code')->all();
        };

        $this->assertCount(6, $kode([]));
        $this->assertSame(['P-MEB'], $kode([['company_id' => [(string) self::MEB]]]));
        $this->assertSame(['P-BEKAS', 'P-KLIEN', 'P-ME'], $kode([['company_id' => [self::ME]]]));
        $this->assertSame(['P-ME'], $kode([['company_id' => ['1']], ['project_code' => ['P-ME', 'P-MEB']]]));
        // kelompok kolom biasa saja: OR antar kelompok, AND di dalam kelompok (perilaku lama)
        $this->assertSame(['P-ENL', 'P-ME'], $kode([
            ['project_code' => ['P-ME'], 'project_name' => ['Project P-ME']],
            ['project_code' => ['P-ENL']],
        ]));
    }

    public function test_bentuk_sql_in_dan_not_in_per_nilai(): void
    {
        $sql = function (array $values): string {
            $query = DB::table('master_project as mp');
            MasterFilterGroups::apply($query, 'master_project', [['company_id' => $values]], 'mp');

            return $query->toRawSql();
        };
        $sub = fn (string $operator, string $daftar): string => '"mp"."id" '.$operator.' (select "project_id" from "master_project_detail" where "company_id" in ('.$daftar.')'
            .' and "project_id" is not null and "deleted_at" is null)';

        // V = [647]: hanya IN
        $this->assertSame('select * from "master_project" as "mp" where '.$sub('in', "'647'"), $sql(['647']));
        // V = [1] (pemilik): IN (1) AND NOT IN (647, 648)
        $this->assertSame(
            'select * from "master_project" as "mp" where '.$sub('in', "'1'").' and '.$sub('not in', "'647', '648'"),
            $sql(['1'])
        );
        // V = [1, 647]: IN (1, 647) AND NOT IN (648)
        $this->assertSame(
            'select * from "master_project" as "mp" where '.$sub('in', "'1', '647'").' and '.$sub('not in', "'648'"),
            $sql(['1', '647'])
        );
    }

    public function test_apply_menerima_eloquent_builder_tanpa_alias(): void
    {
        $query = Project::query();
        MasterFilterGroups::apply($query, 'master_project', [['company_id' => [(string) self::ENL]]]);

        $this->assertSame(['P-ENL'], $query->pluck('project_code')->all());
    }

    public function test_tabel_relasi_tanpa_deleted_at_tidak_memakai_syarat_soft_delete(): void
    {
        Schema::table('master_project_detail', fn ($table) => $table->dropColumn('deleted_at'));
        \Bangsamu\Master\Services\SchemaColumns::flush();

        $query = DB::table('master_project');
        MasterFilterGroups::apply($query, 'master_project', [['company_id' => [(string) self::MEB]]]);

        $this->assertStringNotContainsString('deleted_at', $query->toSql());
        // tanpa soft delete, baris MEB milik P-BEKAS ikut dihitung
        $this->assertSame(['P-BEKAS', 'P-MEB'], $query->orderBy('project_code')->pluck('project_code')->all());
    }
}
