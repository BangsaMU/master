<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Baris Project Detail yang company-nya terhapus / tidak ada di master (v3.15.0, permintaan user 2026-11-19):
 *  - kolom Action daftar: peringatan + tombol "Pindah company" (admin hub) / petunjuk minta admin;
 *  - Edit Project Detail → Pindah company: pengecualian terbatas keputusan N2 (company aktif tetap tidak bisa diganti),
 *    id baris + kode/nama client tetap, company tujuan aktif, bukan pemilik project, belum punya baris di project itu;
 *  - card ringkasan di atas daftar + filter `masalah` (company / project bermasalah).
 * Di DB dev 2026-11-19: 5 baris memakai company yang sudah dihapus (PHM 73030, MED 73028/73031/73032, PHE 73033).
 */
class ProjectDetailMoveCompanyTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private const ENL = 648;

    private const KSO = 649;

    private const PHM = 116; // company klien, sudah dihapus

    private const MED = 357; // sudah dihapus

    private const HILANG = 999; // tidak ada di master_company

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('MasterCrudConfig.MASTER_HUB', true);
        $app['config']->set('MasterCrudConfig.MASTER_DIRECT_EDIT', true);
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
            ['id' => self::MEB, 'company_code' => 'MEB', 'company_name' => 'PT Meitech Eka Bintan', 'deleted_at' => null],
            ['id' => self::ENL, 'company_code' => 'ENL', 'company_name' => 'PT Eka Nusantara Line', 'deleted_at' => null],
            ['id' => self::KSO, 'company_code' => 'KSO', 'company_name' => 'KSO Meindo', 'deleted_at' => null],
            ['id' => self::PHM, 'company_code' => 'PHM', 'company_name' => 'Pertamina Hulu Mahakam', 'deleted_at' => '2026-10-01 08:00:00'],
            ['id' => self::MED, 'company_code' => 'MED', 'company_name' => 'Medco', 'deleted_at' => '2026-10-01 08:00:00'],
        ]);
    }

    /** admin Master Data di hub (is_admin) */
    private function login(): void
    {
        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function loginBukanAdmin(): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => ['read_project_detail', 'update_project_detail', 'read_project']]);
        $this->actingAs(User::create(['name' => 'Staf Master', 'email' => 'staf'.uniqid().'@meindo.test']));
    }

    private function project(string $kode, ?int $pemilik = null, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => 'Project '.$kode, 'internal_external' => 'E',
            'owner_company_id' => $pemilik, 'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    private function detail(int $projectId, ?int $company, string $kode, bool $terhapus = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode,
            'project_name_client' => 'Klien '.$kode, 'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    /** @return array<string, string> Project Code Client => isi kolom Action (kunci 'K' + kode: kode angka tetap string) */
    private function aksiDaftar(string $masalah = ''): array
    {
        $url = '/master/project-detail'.($masalah !== '' ? '?masalah='.$masalah : '');

        return collect($this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'))
            ->mapWithKeys(fn ($row) => ['K'.$row['project_code_client'] => $row['action']])->all();
    }

    private function pindah(int $detailId, ?int $company, bool $konfirmasi = true)
    {
        return $this->post(route('master.project-detail.move-company', $detailId), array_filter([
            'move_company_id' => $company, 'confirm_move_company' => $konfirmasi ? '1' : null,
        ]));
    }

    private function companyBaris(int $detailId): int
    {
        return (int) DB::table('master_project_detail')->where('id', $detailId)->value('company_id');
    }

    // ---- kolom Action ----

    public function test_daftar_menandai_company_terhapus_dan_tidak_ada_dengan_tombol_pindah_untuk_admin(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');
        $phm = $this->detail($p, self::PHM, '73030');
        $hilang = $this->detail($p, self::HILANG, '73099');

        $aksi = $this->aksiDaftar();

        $this->assertStringNotContainsString('data-company-problem', $aksi['K21317']);
        $this->assertStringContainsString('Company PHM sudah dihapus dari master', $aksi['K73030']);
        $this->assertStringContainsString(e(route('master.project-detail.edit', $phm).'#project-detail-move-company'), $aksi['K73030']);
        $this->assertStringContainsString('Pindah company', $aksi['K73030']);
        $this->assertStringContainsString('Company tidak ada di master', $aksi['K73099']);
        $this->assertStringContainsString(e(route('master.project-detail.edit', $hilang).'#project-detail-move-company'), $aksi['K73099']);
    }

    public function test_baris_pemilik_atau_project_terhapus_diberi_alasan_bukan_tombol(): void
    {
        $this->login();
        $milikPhm = $this->project('73200', pemilik: self::PHM);   // pemilik project = company yang sudah dihapus
        $this->detail($milikPhm, self::PHM, '73200');
        $lama = $this->project('20001', terhapus: true);
        $this->detail($lama, self::MED, '73201');

        $aksi = $this->aksiDaftar();

        $this->assertStringContainsString('Pindah pemilik', $aksi['K73200']);
        $this->assertStringNotContainsString('#project-detail-move-company', $aksi['K73200']);
        $this->assertStringContainsString('Project baris ini tidak ada atau sudah dihapus', $aksi['K73201']);
        $this->assertStringNotContainsString('#project-detail-move-company', $aksi['K73201']);
    }

    public function test_bukan_admin_hanya_melihat_peringatan_dan_ditolak_saat_memindah(): void
    {
        $p = $this->project('21317');
        $phm = $this->detail($p, self::PHM, '73030');
        $this->loginBukanAdmin();

        $aksi = $this->aksiDaftar();
        $this->assertStringContainsString('Company PHM sudah dihapus dari master', $aksi['K73030']);
        $this->assertStringContainsString('Minta admin Master Data', $aksi['K73030']);
        $this->assertStringNotContainsString('Pindah company', $aksi['K73030']);

        $html = $this->get(route('master.project-detail.edit', $phm))->assertOk()->getContent();
        $this->assertStringContainsString('data-move-company-ask-admin', $html);
        $this->assertStringNotContainsString('name="move_company_id"', $html);

        $this->pindah($phm, self::KSO)->assertForbidden();
        $this->assertSame(self::PHM, $this->companyBaris($phm));
    }

    // ---- halaman Edit + pindah ----

    public function test_halaman_edit_menawarkan_company_aktif_yang_belum_punya_baris_dan_bukan_pemilik(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');                // pemilik project (bawaan ME)
        $this->detail($p, self::MEB, '73029');               // sudah punya baris aktif
        $this->detail($p, self::ENL, 'EN-1', terhapus: true); // punya baris terhapus (index unik project + company)
        $phm = $this->detail($p, self::PHM, '73030');

        $html = $this->get(route('master.project-detail.edit', $phm))->assertOk()->getContent();

        $this->assertStringContainsString('id="project-detail-move-company"', $html);
        $this->assertStringContainsString('data-move-company-problem="deleted"', $html);
        $this->assertStringContainsString(route('master.project-detail.move-company', $phm), $html);
        $this->assertMatchesRegularExpression('#<select[^>]*name="move_company_id".*?</select>#s', $html);
        preg_match('#<select[^>]*name="move_company_id".*?</select>#s', $html, $select);
        $this->assertStringContainsString('KSO - KSO Meindo', $select[0]);
        foreach (['ME - ', 'MEB', 'ENL', 'PHM', 'MED'] as $tidak) {
            $this->assertStringNotContainsString($tidak, $select[0], "$tidak tidak boleh ditawarkan");
        }

        // company baris masih aktif: tidak ada card
        $meb = DB::table('master_project_detail')->where('company_id', self::MEB)->value('id');
        $this->assertStringNotContainsString('id="project-detail-move-company"', $this->get(route('master.project-detail.edit', $meb))->getContent());
    }

    public function test_pindah_company_hanya_mengganti_company_baris(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');
        $phm = $this->detail($p, self::PHM, '73030');
        $hilang = $this->detail($p, null, '73099');

        $this->pindah($phm, self::MEB)
            ->assertRedirect(route('master.project-detail.edit', $phm))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success_message', fn ($pesan) => str_contains($pesan, 'dari PHM (sudah dihapus) ke MEB'));
        $this->pindah($hilang, self::KSO)
            ->assertSessionHas('success_message', fn ($pesan) => str_contains($pesan, 'dari (kosong) (tidak ada di master) ke KSO'));

        $this->assertSame(
            ['id' => $phm, 'project_id' => $p, 'company_id' => self::MEB, 'project_code_client' => '73030', 'project_name_client' => 'Klien 73030'],
            array_map(fn ($v) => is_numeric($v) && ! str_starts_with((string) $v, '7') ? (int) $v : $v, (array) DB::table('master_project_detail')->where('id', $phm)->first(['id', 'project_id', 'company_id', 'project_code_client', 'project_name_client']))
        );
        $this->assertSame(self::KSO, $this->companyBaris($hilang));
        $this->assertStringNotContainsString('id="project-detail-move-company"', $this->get(route('master.project-detail.edit', $phm))->getContent());
    }

    public function test_pindah_company_ditolak_dengan_alasan(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');
        $meb = $this->detail($p, self::MEB, '73029');
        $this->detail($p, self::ENL, 'EN-1', terhapus: true);
        $phm = $this->detail($p, self::PHM, '73030');
        $milikPhm = $this->project('73200', pemilik: self::PHM);
        $pemilikHapus = $this->detail($milikPhm, self::PHM, '73200');
        $lama = $this->project('20001', terhapus: true);
        $projectHapus = $this->detail($lama, self::MED, '73201');
        $barisHapus = $this->detail($p, self::MED, '73031', terhapus: true);

        $kasus = [
            [$meb, self::KSO, 'masih aktif di master'],                   // N2: company aktif tetap tidak bisa diganti
            [$phm, self::MEB, 'MEB sudah punya baris di project ini'],
            [$phm, self::ENL, 'terhapus): bila baris ini dobel'],
            [$phm, self::ME, 'ME pemilik project ini'],
            [$phm, self::MED, 'tidak ditemukan atau sudah dihapus'],
            [$phm, self::HILANG, 'tidak ditemukan atau sudah dihapus'],
            [$pemilikHapus, self::KSO, 'baris pemilik project'],
            [$projectHapus, self::KSO, 'Project baris ini tidak ada atau sudah dihapus'],
            [$barisHapus, self::KSO, 'sudah dihapus: pulihkan (Restore) dulu'],
        ];
        foreach ($kasus as [$baris, $tujuan, $alasan]) {
            $this->pindah($baris, $tujuan)->assertSessionHasErrors('move_company_id');
            $this->assertStringContainsString($alasan, session('errors')->first('move_company_id'), "baris $baris -> company $tujuan");
        }
        $this->pindah($phm, self::KSO, konfirmasi: false)->assertSessionHasErrors('confirm_move_company');
        $this->pindah($phm, null)->assertSessionHasErrors('move_company_id');

        $this->assertSame(
            [self::MEB, self::PHM, self::PHM, self::MED, self::MED],
            array_map(fn ($id) => $this->companyBaris($id), [$meb, $phm, $pemilikHapus, $projectHapus, $barisHapus])
        );
    }

    public function test_form_simpan_biasa_tetap_tidak_bisa_mengganti_company(): void
    {
        $this->login();
        $p = $this->project('21317');
        $phm = $this->detail($p, self::PHM, '73030');

        $this->post(route('master.project-detail.store'), ['id' => $phm, 'project_id' => $p, 'company_id' => self::KSO, 'project_code_client' => '73030', 'project_name_client' => 'Klien 73030'])
            ->assertSessionHasErrors('company_id');
        $this->assertSame(self::PHM, $this->companyBaris($phm));
    }

    // ---- card ringkasan + filter ----

    public function test_card_ringkasan_dan_filter_baris_bermasalah(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');
        $this->detail($p, self::MEB, '73029');
        $this->detail($p, self::PHM, '73030');
        $this->detail($p, self::HILANG, '73099');
        $this->detail($p, self::MED, '73031', terhapus: true); // baris terhapus tidak dihitung
        $lama = $this->project('20001', terhapus: true);
        $this->detail($lama, self::ME, '20001');

        $html = $this->get('/master/project-detail')->assertOk()->getContent();
        $this->assertStringContainsString('id="project-detail-summary"', $html);
        $this->assertMatchesRegularExpression('#data-summary="detail_total">5<#', $html);
        $this->assertMatchesRegularExpression('#data-summary="company_problem">2<#', $html);
        $this->assertMatchesRegularExpression('#data-summary="project_problem">1<#', $html);
        $this->assertMatchesRegularExpression('#data-summary-company="PHM">.*?company sudah dihapus.*?>1</td>#s', $html);
        $this->assertMatchesRegularExpression('#data-summary-company="ME">.*?>2</td>#s', $html);
        $this->assertStringContainsString('data-summary-company="-"', $html); // company tidak ada di master
        $this->assertStringContainsString('window.masterListParams', $html);

        // hanya baris aktif (73031 terhapus tidak ikut, sama dengan hitungan card)
        $this->assertEqualsCanonicalizing(['K73030', 'K73099'], array_keys($this->aksiDaftar('company')));
        $this->assertSame(['K20001'], array_keys($this->aksiDaftar('project')));
        $this->assertCount(6, $this->aksiDaftar('lain')); // nilai tak dikenal = tanpa filter (admin ikut melihat baris terhapus)

        // ?masalah= di URL halaman langsung memilih filternya (tautan dari card daftar Project)
        $html = $this->get('/master/project-detail?masalah=company')->assertOk()->getContent();
        $this->assertStringContainsString('var current = "company";', $html);
    }

    public function test_card_daftar_project_menautkan_baris_bermasalah_ke_project_detail(): void
    {
        $this->login();
        $p = $this->project('21317');
        $this->detail($p, self::ME, '21317');
        $this->detail($p, self::PHM, '73030');

        $html = $this->get('/master/project')->assertOk()->getContent();

        $this->assertStringContainsString(route('master.project-detail.index', ['masalah' => 'company']), $html);
    }

    public function test_di_luar_hub_tanpa_card_dan_tanpa_pindah_company(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => false]);
        $this->login();
        $p = $this->project('21317');
        $phm = $this->detail($p, self::PHM, '73030');

        $this->assertStringNotContainsString('id="project-detail-summary"', $this->get('/master/project-detail')->assertOk()->getContent());
        $aksi = $this->aksiDaftar();
        $this->assertStringContainsString('Company PHM sudah dihapus dari master', $aksi['K73030']);
        $this->assertStringNotContainsString('Pindah company', $aksi['K73030']);
        $this->pindah($phm, self::KSO)->assertNotFound();
    }
}
