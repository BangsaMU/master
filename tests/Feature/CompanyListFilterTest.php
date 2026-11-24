<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Support\CompanyListFilter;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Filter "klik company" (permintaan user 2026-11-24): di daftar master/project (kolom Pemilik) dan master/project-detail
 * (kolom Company Code / Company Name), serta nama company di card ringkasan, klik company = daftar hanya menampilkan
 * baris company itu. Parameter `company_id` (CompanyListFilter) cocok persis per id: cari teks "ME" juga menemukan MEB.
 */
class CompanyListFilterTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1; // pemilik bawaan (PROJECT_OWNER_COMPANY_ID)

    private const MEB = 647;

    private const ENL = 648;

    private const PHM = 116; // sudah dihapus

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('MasterCrudConfig.MASTER_HUB', true);
    }

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        View::addNamespace('adminlte', __DIR__.'/../stubs/adminlte');
        $this->createDashboardSettings();
        DB::connection('db_master')->setPdo(DB::connection()->getPdo());
        Schema::table('master_company', fn (Blueprint $table) => $table->string('company_name')->nullable());

        DB::table('master_company')->insert([
            ['id' => self::ME, 'company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah', 'deleted_at' => null],
            ['id' => self::MEB, 'company_code' => 'MEB', 'company_name' => 'PT Meitech <Eka> Bintan', 'deleted_at' => null],
            ['id' => self::ENL, 'company_code' => 'ENL', 'company_name' => 'PT Eka Nusantara Line', 'deleted_at' => null],
            ['id' => self::PHM, 'company_code' => 'PHM', 'company_name' => 'Pertamina Hulu Mahakam', 'deleted_at' => '2026-10-01 08:00:00'],
        ]);

        $sisi = $this->project('21317', null);
        $bintan = $this->project('73101', self::MEB);
        $kapal = $this->project('EN3000', self::ENL);
        $this->project('21000', self::ME); // pemilik ME ditulis eksplisit = sama dengan kosong

        $this->detail($sisi, self::ME, '21317');
        $this->detail($sisi, self::MEB, '73029');
        $this->detail($sisi, self::PHM, '73030');
        $this->detail($bintan, self::MEB, '73101');
        $this->detail($kapal, self::ENL, 'EN3000');
        $this->detail($kapal, self::MEB, 'OLD-1', terhapus: true);
    }

    private function login(bool $admin = true): void
    {
        config(['test.denied_permissions' => $admin ? [] : ['is_admin'], 'test.allowed_permissions' => $admin ? null : ['read_project', 'read_project_detail']]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function project(string $kode, ?int $pemilik): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => 'Project '.$kode, 'internal_external' => 'E', 'owner_company_id' => $pemilik,
        ]);
    }

    private function detail(int $projectId, int $company, string $kode, bool $terhapus = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode,
            'project_name_client' => 'Klien '.$kode, 'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    /** @return array<string, mixed> respon DataTables */
    private function daftar(string $slug, array $params = []): array
    {
        $url = '/master/'.$slug.($params ? '?'.http_build_query($params) : '');

        return $this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
    }

    /** @return list<string> kode baris hasil (Project: project_code, Project Detail: project_code_client) */
    private function kode(array $json, string $kolom): array
    {
        $kode = array_map('strval', array_column($json['data'], $kolom));
        sort($kode);

        return $kode;
    }

    // ---- daftar Project Detail ----

    public function test_project_detail_hanya_baris_company_terpilih_cocok_persis(): void
    {
        $this->login(false);

        // ME tidak ikut menemukan MEB (cari teks kolom Company Code "ME" = LIKE, menemukan keduanya)
        $json = $this->daftar('project-detail', ['company_id' => self::ME]);
        $this->assertSame(['21317'], $this->kode($json, 'project_code_client'));
        $this->assertSame(1, $json['recordsTotal']);
        $this->assertSame(1, $json['recordsFiltered']);

        $this->assertSame(['73029', '73101'], $this->kode($this->daftar('project-detail', ['company_id' => self::MEB]), 'project_code_client'));
        // company yang sudah dihapus tetap bisa dipilih (baris lama masih memakainya)
        $this->assertSame(['73030'], $this->kode($this->daftar('project-detail', ['company_id' => self::PHM]), 'project_code_client'));
    }

    public function test_project_detail_filter_company_digabung_dengan_cari_dan_baris_terhapus_admin(): void
    {
        $this->login();

        // admin melihat baris terhapus (OLD-1 milik MEB) seperti tanpa filter
        $this->assertSame(['73029', '73101', 'OLD-1'], $this->kode($this->daftar('project-detail', ['company_id' => self::MEB]), 'project_code_client'));

        $json = $this->daftar('project-detail', ['company_id' => self::MEB, 'search' => ['value' => '21317']]);
        $this->assertSame(['73029'], $this->kode($json, 'project_code_client'));
        $this->assertSame(3, $json['recordsTotal']);
        $this->assertSame(1, $json['recordsFiltered']);
    }

    public function test_company_id_tidak_sah_atau_tidak_ada_diabaikan(): void
    {
        $this->login(false);

        foreach (['', 'abc', '0', '-5', '647 OR 1=1', '99999'] as $nilai) {
            $this->assertCount(5, $this->daftar('project-detail', ['company_id' => $nilai])['data'], "company_id=$nilai");
            $this->assertCount(4, $this->daftar('project', ['company_id' => $nilai])['data'], "company_id=$nilai");
        }
    }

    public function test_baris_project_detail_membawa_id_dan_label_company_ter_escape(): void
    {
        $this->login(false);

        $rows = collect($this->daftar('project-detail')['data'])->keyBy('project_code_client');

        $this->assertSame(self::MEB, $rows['73029']['company_filter_id']);
        $this->assertSame('MEB - PT Meitech &lt;Eka&gt; Bintan', $rows['73029']['company_filter_label']);
        $this->assertSame('PT Meitech &lt;Eka&gt; Bintan', $rows['73029']['company_name']); // isi sel tetap teks ter-escape
        $this->assertSame(self::PHM, $rows['73030']['company_filter_id']);
    }

    // ---- daftar Project (kolom Pemilik) ----

    public function test_project_hanya_milik_company_terpilih_pemilik_kosong_ikut_pemilik_bawaan(): void
    {
        $this->login(false);

        $json = $this->daftar('project', ['company_id' => self::ME]);
        $this->assertSame(['21000', '21317'], $this->kode($json, 'project_code'));
        $this->assertSame(2, $json['recordsTotal']);

        $this->assertSame(['73101'], $this->kode($this->daftar('project', ['company_id' => self::MEB]), 'project_code'));
        $this->assertSame(['EN3000'], $this->kode($this->daftar('project', ['company_id' => self::ENL]), 'project_code'));
        $this->assertSame([], $this->kode($this->daftar('project', ['company_id' => self::PHM]), 'project_code'));
    }

    public function test_baris_project_membawa_id_dan_label_company_pemilik(): void
    {
        $this->login(false);

        $rows = collect($this->daftar('project')['data'])->keyBy('project_code');

        $this->assertSame('ME', $rows['21317']['pemilik']);
        $this->assertSame(self::ME, $rows['21317']['company_filter_id']);
        $this->assertSame('ME - PT Meindo Elang Indah', $rows['21317']['company_filter_label']);
        $this->assertSame(self::MEB, $rows['73101']['company_filter_id']);
        $this->assertSame('MEB - PT Meitech &lt;Eka&gt; Bintan', $rows['73101']['company_filter_label']);
    }

    // ---- halaman daftar ----

    public function test_halaman_daftar_memasang_bar_filter_dan_tautan_company_di_card(): void
    {
        $this->login();

        $html = $this->get('/master/project-detail')->assertOk()->getContent();
        $this->assertStringContainsString('data-company-filter-bar', $html);
        $this->assertStringContainsString('Hanya baris company', $html);
        $this->assertStringContainsString('window.masterCompanyFilter', $html);
        $this->assertStringContainsString('"columns":["company_code","company_name"]', $html);
        $this->assertStringContainsString('data-company-filter="'.self::MEB.'"', $html);
        $this->assertStringContainsString('href="'.e(route('master.project-detail.index', ['company_id' => self::MEB])).'"', $html);
        $this->assertStringContainsString('data-company-label="MEB - PT Meitech &lt;Eka&gt; Bintan"', $html);

        $html = $this->get('/master/project')->assertOk()->getContent();
        $this->assertStringContainsString('Hanya project milik', $html);
        $this->assertStringContainsString('"columns":["pemilik"]', $html);
        $this->assertStringContainsString('href="'.e(route('master.project.index', ['company_id' => self::ENL])).'"', $html);
    }

    public function test_company_id_di_url_halaman_langsung_memilih_filter(): void
    {
        $this->login();

        $html = $this->get('/master/project-detail?company_id='.self::PHM)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<strong data-company-filter-label>PHM - Pertamina Hulu Mahakam</strong>#', $html);
        $this->assertStringContainsString('var initial = {"id":'.self::PHM.',"label":"PHM - Pertamina Hulu Mahakam","deleted":true};', $html);
        $this->assertDoesNotMatchRegularExpression('#class="[^"]*d-none[^"]*" data-company-filter-bar#', $html);

        $html = $this->get('/master/project-detail?company_id=99999')->assertOk()->getContent();
        $this->assertStringContainsString('var initial = null;', $html);
        $this->assertMatchesRegularExpression('#class="mb-3 d-none" data-company-filter-bar#', $html);
    }

    public function test_label_dan_config(): void
    {
        $this->assertSame('ME - PT Meindo', CompanyListFilter::label('ME', ' PT Meindo '));
        $this->assertSame('ME', CompanyListFilter::label('ME', null));
        $this->assertSame(['company_filter_id' => null, 'company_filter_label' => null], CompanyListFilter::rowFields(null, 'X', 'Y'));
    }
}
