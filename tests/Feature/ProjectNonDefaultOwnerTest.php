<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Bangsamu\Master\Services\SchemaColumns;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Project non-Meindo (v3.13.0; rancangan SPB docs/rancangan-project-non-meindo.md, keputusan N1–N7):
 *  - master_project.owner_company_id = company pemilik project, NULL = pemilik bawaan (PROJECT_OWNER_COMPANY_ID);
 *  - Create Project: centang "bukan <pemilik bawaan>" + company (admin: semua company, delegasi: company-nya,
 *    tercentang dari awal) = satu baris detail company itu saja, tanpa baris Meindo;
 *  - baris detail: project/company tidak bisa diganti, baris pemilik tidak bisa diubah/dihapus dari Project Detail;
 *  - Pindah pemilik (admin): baris company tujuan dipakai/dipulihkan/dibuat, kode/nama project ikut baris itu, baris
 *    pemilik lama tetap ada;
 *  - kartu Buat Detail, cermin, import Project (Owner Company Code), daftar Project (kolom Pemilik).
 */
class ProjectNonDefaultOwnerTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private const ENL = 648;

    private const KLIEN = 116;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('libraryclay.activity_log.console', true);
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
            ['id' => self::ME, 'company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah'],
            ['id' => self::KLIEN, 'company_code' => 'PHM', 'company_name' => 'Pertamina Hulu Mahakam'],
            ['id' => self::MEB, 'company_code' => 'MEB', 'company_name' => 'PT Meitech Eka Bintan'],
            ['id' => self::ENL, 'company_code' => 'ENL', 'company_name' => 'PT Eka Nusantara Line'],
        ]);
    }

    /** tim master-data di hub (is_admin) */
    private function login(): void
    {
        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    /** user hub tanpa is_admin, hanya izin modul */
    private function loginBukanAdmin(array $izin): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => $izin]);
        $this->actingAs(User::create(['name' => 'Staf Master', 'email' => 'staf'.uniqid().'@meindo.test']));
    }

    /** user aplikasi lain (SPB, company $company) yang membuka hub lewat launch token */
    private function loginDelegasi(int $company, array $izin = ['create_project', 'update_project']): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => $izin]);
        $this->actingAs(User::create(['name' => 'Admin MEB', 'email' => 'meb'.uniqid().'@meindo.test']));
        $this->withSession([MasterScope::SESSION_KEY => [
            'app_code' => 'APP32', 'app_name' => 'SPB V2', 'company_id' => $company, 'company_code' => 'MEB',
            'all_co' => false, 'mods' => ['master_project' => ['create', 'update']], 'back' => null, 'exp' => time() + 600,
        ]]);
    }

    private function project(string $kode, ?string $nama = null, ?int $pemilik = null, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => $nama ?? 'Project '.$kode, 'owner_company_id' => $pemilik,
            'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    private function detail(int $projectId, int $company, string $kode, ?string $nama = null, bool $terhapus = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => $nama ?? 'Project '.$kode,
            'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    /** @return list<array{company_id: int, project_code_client: string, project_name_client: ?string}> urut id */
    private function barisDetail(int $projectId): array
    {
        return DB::table('master_project_detail')->where('project_id', $projectId)->whereNull('deleted_at')->orderBy('id')
            ->get(['company_id', 'project_code_client', 'project_name_client'])
            ->map(fn ($row) => ['company_id' => (int) $row->company_id, 'project_code_client' => $row->project_code_client, 'project_name_client' => $row->project_name_client])
            ->all();
    }

    /** @param  array<string, mixed>  $tambahan */
    private function simpanBaru(string $kode, string $nama, array $tambahan = [])
    {
        return $this->post(route('master.project.store'), ['project_code' => $kode, 'project_name' => $nama] + $tambahan);
    }

    private function idProject(string $kode): ?int
    {
        $id = DB::table('master_project')->where('project_code', $kode)->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function pemilikKolom(int $projectId): ?int
    {
        $value = DB::table('master_project')->where('id', $projectId)->value('owner_company_id');

        return $value !== null ? (int) $value : null;
    }

    private function upload(string $entity, string $csv): string
    {
        $response = $this->post(route('master.import.preview', $entity), ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)]);
        $response->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    /** @return array<int, ?string> error per baris preview import */
    private function errorPreview(string $entity, string $token): array
    {
        return collect($this->get(route('master.import.show', [$entity, $token]))->viewData('analysis')['rows'])
            ->map(fn (array $row) => $row['status'] === 'error' ? ($row['message'] ?? '') : null)->all();
    }

    // ---- skema ----

    public function test_upgrader_menambah_kolom_pemilik_tanpa_mengubah_baris_lama(): void
    {
        Schema::table('master_project', fn (Blueprint $table) => $table->dropColumn('owner_company_id'));
        SchemaColumns::flush();
        MasterSchemaUpgrader::flush();
        $id = DB::table('master_project')->insertGetId(['project_code' => 'L001', 'project_name' => 'Project Lama']);

        $this->assertSame(self::ME, app(ProjectOwnerDetail::class)->ownerCompanyIdOf($id), 'kolom belum ada = pemilik bawaan');
        $this->assertSame(['tambah kolom owner_company_id'], app(MasterSchemaUpgrader::class)->ensure('master_project'));
        $this->assertArrayNotHasKey('master_project', app(MasterSchemaUpgrader::class)->upgradeAll(), 'sekali saja');

        $this->assertTrue(Schema::hasColumn('master_project', 'owner_company_id'));
        $this->assertNull(DB::table('master_project')->where('id', $id)->value('owner_company_id'));
        $this->assertSame(self::ME, app(ProjectOwnerDetail::class)->ownerCompanyIdOf($id), 'NULL = pemilik bawaan');
    }

    public function test_tanpa_kolom_pemilik_form_tanpa_pilihan_dan_pilihan_company_ditolak(): void
    {
        Schema::table('master_project', fn (Blueprint $table) => $table->dropColumn('owner_company_id'));
        SchemaColumns::flush();
        MasterSchemaUpgrader::flush();
        // controller meng-upgrade tabel sendiri (ensureSchema), jadi upgrade ditandai sudah dicek di proses ini
        app(MasterSchemaUpgrader::class)->ensure('master_department');
        $this->login();

        $service = app(ProjectOwnerDetail::class);
        $this->assertTrue($service->ownerChoices()->isEmpty());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->newProjectPlan(self::MEB);
    }

    // ---- Create Project ----

    public function test_admin_membuat_project_milik_meb_hanya_dengan_baris_meb(): void
    {
        $this->login();

        $this->simpanBaru('73101', 'Proyek Bintan Satu', ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB])
            ->assertRedirect(route('master.project.index'))->assertSessionHasNoErrors();

        $id = $this->idProject('73101');
        $this->assertSame(self::MEB, $this->pemilikKolom($id));
        $this->assertSame([['company_id' => self::MEB, 'project_code_client' => '73101', 'project_name_client' => 'Proyek Bintan Satu']], $this->barisDetail($id));
        $this->assertTrue(app(ProjectOwnerDetail::class)->hasOtherOwner($id));
        $this->assertSame(1, ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'created')->count());
    }

    public function test_tanpa_centang_perilaku_lama_dan_pemilik_ditulis_eksplisit(): void
    {
        $this->login();

        // company terpilih diabaikan bila centang tidak menyala
        $this->simpanBaru('21500', 'Proyek Meindo', ['project_owner_other' => '0', 'project_owner_company_id' => self::MEB])->assertSessionHasNoErrors();

        $id = $this->idProject('21500');
        $this->assertSame(self::ME, $this->pemilikKolom($id));
        $this->assertSame([self::ME], array_column($this->barisDetail($id), 'company_id'));
    }

    public function test_centang_tanpa_company_ditolak(): void
    {
        $this->login();

        $this->simpanBaru('73102', 'Proyek Tanpa Company', ['project_owner_other' => '1'])->assertSessionHasErrors('project_owner_company_id');

        $this->assertNull($this->idProject('73102'));
    }

    public function test_bukan_admin_tidak_bisa_memilih_pemilik_lain(): void
    {
        $this->loginBukanAdmin(['create_project']);

        $this->simpanBaru('73103', 'Proyek Staf', ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB])
            ->assertSessionHasErrors('project_owner_company_id');

        $this->assertNull($this->idProject('73103'));
    }

    public function test_kode_yang_sudah_dipakai_company_itu_di_project_lain_ditolak(): void
    {
        $this->login();
        $bersama = $this->project('21317', 'EPSCC Meindo');
        $this->detail($bersama, self::ME, '21317', 'EPSCC Meindo');
        $this->detail($bersama, self::MEB, '73029', 'Sisi Nubia AOI');

        $this->simpanBaru('73029', 'Proyek Bentrok', ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB])
            ->assertSessionHasErrors('project_code');

        $this->assertNull($this->idProject('73029'));
    }

    public function test_form_create_menampilkan_pilihan_pemilik_untuk_admin(): void
    {
        $this->login();

        $html = $this->get(route('master.project.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Pemilik project bukan ME', $html);
        $this->assertStringContainsString('name="project_owner_company_id"', $html);
        $this->assertStringContainsString('ENL - PT Eka Nusantara Line', $html);
    }

    public function test_bukan_admin_tanpa_delegasi_tidak_melihat_pilihan_pemilik(): void
    {
        $this->loginBukanAdmin(['create_project']);

        $this->assertStringNotContainsString('Pemilik project bukan', $this->get(route('master.project.create'))->assertOk()->getContent());
    }

    // ---- delegasi (N3) ----

    public function test_delegasi_form_tercentang_dengan_company_delegasi_saja(): void
    {
        $this->loginDelegasi(self::MEB);

        $html = $this->get(route('master.project.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="project_owner_other"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.self::MEB.'"\s+selected/', $html);
        $this->assertStringNotContainsString('ENL - PT Eka Nusantara Line', $html, 'delegasi hanya company-nya sendiri');
    }

    public function test_delegasi_centang_menyala_project_milik_meb(): void
    {
        $this->loginDelegasi(self::MEB);

        $this->simpanBaru('73104', 'Proyek Delegasi', ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB])->assertSessionHasNoErrors();

        $id = $this->idProject('73104');
        $this->assertSame(self::MEB, $this->pemilikKolom($id));
        $this->assertSame([self::MEB], array_column($this->barisDetail($id), 'company_id'));
    }

    public function test_delegasi_centang_dimatikan_perilaku_lama_dua_baris(): void
    {
        $this->loginDelegasi(self::MEB);

        $this->simpanBaru('P205', 'Proyek Bersama', ['project_owner_other' => '0'])->assertSessionHasNoErrors();

        $id = $this->idProject('P205');
        $this->assertSame(self::ME, $this->pemilikKolom($id));
        $this->assertSame([self::ME, self::MEB], array_column($this->barisDetail($id), 'company_id'));
    }

    public function test_delegasi_tidak_bisa_memilih_company_lain(): void
    {
        $this->loginDelegasi(self::MEB);

        $this->simpanBaru('73105', 'Proyek ENL', ['project_owner_other' => '1', 'project_owner_company_id' => self::ENL])
            ->assertSessionHasErrors('project_owner_company_id');

        $this->assertNull($this->idProject('73105'));
    }

    // ---- kunci baris Project Detail ----

    public function test_company_dan_project_baris_detail_tidak_bisa_diganti(): void
    {
        $this->login();
        $id = $this->project('21317', 'EPSCC Meindo');
        $this->detail($id, self::ME, '21317', 'EPSCC Meindo');
        $meb = $this->detail($id, self::MEB, '73029', 'Sisi Nubia AOI');
        $lain = $this->project('21318');

        $this->post(route('master.project-detail.store'), ['id' => $meb, 'project_id' => $id, 'company_id' => self::ENL, 'project_code_client' => '73029', 'project_name_client' => 'Sisi Nubia AOI'])
            ->assertSessionHasErrors('company_id');
        $this->post(route('master.project-detail.store'), ['id' => $meb, 'project_id' => $lain, 'company_id' => self::MEB, 'project_code_client' => '73029', 'project_name_client' => 'Sisi Nubia AOI'])
            ->assertSessionHasErrors('project_id');

        $this->assertSame(['project_id' => $id, 'company_id' => self::MEB], (array) DB::table('master_project_detail')->where('id', $meb)->first(['project_id', 'company_id']));
    }

    public function test_baris_bukan_pemilik_tetap_bisa_diganti_nama(): void
    {
        $this->login();
        $id = $this->project('21317', 'EPSCC Meindo');
        $this->detail($id, self::ME, '21317', 'EPSCC Meindo');
        $meb = $this->detail($id, self::MEB, '73029', 'Sisi Nubia AOI');

        $this->post(route('master.project-detail.store'), ['id' => $meb, 'project_id' => $id, 'company_id' => self::MEB, 'project_code_client' => '73029', 'project_name_client' => 'Sisi Nubia AOI Paket A'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Sisi Nubia AOI Paket A', DB::table('master_project_detail')->where('id', $meb)->value('project_name_client'));
    }

    public function test_baris_pemilik_tidak_bisa_diubah_nama_atau_dihapus_dari_project_detail(): void
    {
        $this->login();
        $id = $this->project('73101', 'Proyek Bintan', self::MEB);
        $pemilik = $this->detail($id, self::MEB, '73101', 'Proyek Bintan');

        $this->post(route('master.project-detail.store'), ['id' => $pemilik, 'project_id' => $id, 'company_id' => self::MEB, 'project_code_client' => '73101', 'project_name_client' => 'Nama Lain'])
            ->assertSessionHasErrors('project_code_client');
        $this->delete(route('master.project-detail.destroy', $pemilik))
            ->assertRedirect(route('master.project-detail.index'))->assertSessionHas('error_message');

        $this->assertSame([['company_id' => self::MEB, 'project_code_client' => '73101', 'project_name_client' => 'Proyek Bintan']], $this->barisDetail($id));
    }

    public function test_baris_bukan_pemilik_bisa_dihapus_dan_baris_pemilik_project_terhapus_tidak_dikunci(): void
    {
        $this->login();
        $id = $this->project('21317');
        $this->detail($id, self::ME, '21317');
        $meb = $this->detail($id, self::MEB, '73029');

        $this->delete(route('master.project-detail.destroy', $meb))->assertSessionMissing('error_message');
        $this->assertSame([self::ME], array_column($this->barisDetail($id), 'company_id'));

        $tutup = $this->project('21319', terhapus: true);
        $this->assertNull(app(ProjectOwnerDetail::class)->detailDeleteDenial(DB::table('master_project_detail')->where('id', $this->detail($tutup, self::ME, '21319'))->first()));
    }

    public function test_form_edit_detail_mengunci_company_dan_baris_pemilik(): void
    {
        $this->login();
        $id = $this->project('73101', 'Proyek Bintan', self::MEB);
        $pemilik = $this->detail($id, self::MEB, '73101', 'Proyek Bintan');

        $html = $this->get(route('master.project-detail.edit', $pemilik))->assertOk()->getContent();

        $this->assertStringContainsString('data-owner-row-note', $html);
        $this->assertMatchesRegularExpression('/<select\s+disabled[^>]*name="company_id"/', $html);
        $this->assertMatchesRegularExpression('/readonly\s+type="text" name="project_name_client"/', $html);
    }

    public function test_import_project_detail_tidak_mengubah_nama_baris_pemilik(): void
    {
        $this->login();
        $id = $this->project('73101', 'Proyek Bintan', self::MEB);
        $this->detail($id, self::MEB, '73101', 'Proyek Bintan');

        $token = $this->upload('project-detail', "Project Code,Project Code Client,Project Name Client,Company Code\n73101,73101,Nama Lain,MEB\n");

        $this->assertStringContainsString('baris pemilik project', (string) $this->errorPreview('project-detail', $token)[0]);
    }

    // ---- kartu Buat Detail, cermin ----

    public function test_ringkasan_project_non_meindo_tanpa_baris_meindo_bukan_kekurangan(): void
    {
        $meindo = $this->project('21000');
        $this->detail($meindo, self::ME, '21000');
        $meb = $this->project('73101', pemilik: self::MEB);
        $this->detail($meb, self::MEB, '73101');
        $mebTanpaBaris = $this->project('73102', pemilik: self::MEB);
        $this->detail($mebTanpaBaris, self::ME, '73102'); // baris Meindo bukan baris pemilik project ini

        $service = app(ProjectOwnerDetail::class);
        $s = $service->summary();

        $this->assertSame(1, $s['missing']);
        $this->assertSame(['73102'], $s['missing_sample']);
        $this->assertSame(2, $s['other_owner']);
        $this->assertSame(2, collect($s['companies'])->firstWhere('code', 'MEB')['owned']);
        $this->assertSame([$meindo, $meb], $service->projectIdsWithDetail([$meindo, $meb, $mebTanpaBaris]));

        $html = view('master::partials.project-owner-summary', ['data' => ['page' => ['owner_summary' => $s]]])->render();
        $this->assertStringContainsString('1 project aktif belum punya Project Detail company pemiliknya', $html);
        $this->assertStringContainsString('2 project aktif milik company selain ME', $html);
    }

    public function test_buat_detail_membuat_baris_company_pemilik_project(): void
    {
        $this->login();
        $id = $this->project('73101', 'Proyek Bintan', self::MEB);

        $this->post(route('master.project.owner-detail', $id))->assertSessionHas('success_message');

        $this->assertSame([['company_id' => self::MEB, 'project_code_client' => '73101', 'project_name_client' => 'Proyek Bintan']], $this->barisDetail($id));
    }

    public function test_ubah_nama_project_milik_meb_mencerminkan_baris_meb_saja(): void
    {
        $this->login();
        $id = $this->project('73101', 'Nama Lama', self::MEB);
        $this->detail($id, self::MEB, '73101', 'Nama Lama');
        $this->detail($id, self::ME, '21420', 'Nama Meindo');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => '73101', 'project_name' => 'Nama Baru'])->assertSessionHasNoErrors();

        $this->assertSame([
            ['company_id' => self::MEB, 'project_code_client' => '73101', 'project_name_client' => 'Nama Baru'],
            ['company_id' => self::ME, 'project_code_client' => '21420', 'project_name_client' => 'Nama Meindo'],
        ], $this->barisDetail($id));
        $this->assertSame(self::MEB, $this->pemilikKolom($id), 'edit project tidak memindah pemilik');
    }

    // ---- import Project: Owner Company Code ----

    public function test_import_project_baru_dengan_owner_company_code(): void
    {
        $this->login();

        $token = $this->upload('project', "Project Code,Project Name,Project Type,Project Start Date,Project Complete Date,Project Remarks,Owner Company Code\n"
            ."73201,Proyek Import MEB,Internal,,,,MEB\n21600,Proyek Import Meindo,Internal,,,,\n");
        $this->post(route('master.import.commit', ['project', $token]));

        $meb = $this->idProject('73201');
        $this->assertSame(self::MEB, $this->pemilikKolom($meb));
        $this->assertSame([self::MEB], array_column($this->barisDetail($meb), 'company_id'));
        $meindo = $this->idProject('21600');
        $this->assertSame(self::ME, $this->pemilikKolom($meindo));
        $this->assertSame([self::ME], array_column($this->barisDetail($meindo), 'company_id'));
    }

    public function test_import_project_tidak_memindah_pemilik_dan_menolak_company_tidak_dikenal(): void
    {
        $this->login();
        $id = $this->project('21317', 'EPSCC Meindo');
        $this->detail($id, self::ME, '21317', 'EPSCC Meindo');

        $token = $this->upload('project', "Project Code,Project Name,Project Type,Project Start Date,Project Complete Date,Project Remarks,Owner Company Code\n"
            ."21317,EPSCC Meindo,Internal,,,,MEB\n73202,Proyek Asing,Internal,,,,XYZ\n");
        $errors = $this->errorPreview('project', $token);

        $this->assertStringContainsString('Pindah pemilik', (string) $errors[0]);
        $this->assertStringContainsString("Owner Company Code 'XYZ' tidak ditemukan", (string) $errors[1]);
    }

    public function test_import_owner_company_code_bukan_admin_ditolak(): void
    {
        $this->loginBukanAdmin(['create_project', 'read_project']);

        $token = $this->upload('project', "Project Code,Project Name,Project Type,Project Start Date,Project Complete Date,Project Remarks,Owner Company Code\n73203,Proyek Staf,Internal,,,,MEB\n");

        $this->assertStringContainsString('tidak bisa dipilih sebagai pemilik project', (string) $this->errorPreview('project', $token)[0]);
    }

    // ---- Pindah pemilik ----

    public function test_pindah_pemilik_ke_company_yang_sudah_punya_baris(): void
    {
        $this->login();
        $id = $this->project('21317', 'EPSCC Meindo');
        $this->detail($id, self::ME, '21317', 'EPSCC Meindo');
        $this->detail($id, self::MEB, '73029', 'Sisi Nubia AOI');

        $this->post(route('master.project.transfer-owner', $id), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])
            ->assertRedirect(route('master.project.edit', $id))->assertSessionHas('success_message');

        $project = DB::table('master_project')->where('id', $id)->first();
        $this->assertSame([self::MEB, '73029', 'Sisi Nubia AOI'], [(int) $project->owner_company_id, $project->project_code, $project->project_name]);
        $this->assertSame([self::ME, self::MEB], array_column($this->barisDetail($id), 'company_id'), 'baris pemilik lama tetap ada');
        $this->assertSame('21317', $this->barisDetail($id)[0]['project_code_client']);
        $log = ActivityLog::where('model_type', 'like', '%Project')->where('action', 'updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('owner_company_id', json_encode($log->toArray()));
    }

    public function test_pindah_pemilik_ke_company_tanpa_baris_membuat_baris_dan_baris_terhapus_dipulihkan(): void
    {
        $this->login();
        $a = $this->project('21400', 'Proyek A');
        $this->detail($a, self::ME, '21400', 'Proyek A');
        $b = $this->project('21401', 'Proyek B');
        $this->detail($b, self::ME, '21401', 'Proyek B');
        $terhapus = $this->detail($b, self::ENL, '90001', 'Proyek B ENL', terhapus: true);

        $this->post(route('master.project.transfer-owner', $a), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])->assertSessionHasNoErrors();
        $this->post(route('master.project.transfer-owner', $b), ['to_company_id' => self::ENL, 'confirm_transfer' => '1'])->assertSessionHasNoErrors();

        $this->assertSame([self::ME, self::MEB], array_column($this->barisDetail($a), 'company_id'));
        $this->assertSame(['21400', 'Proyek A'], [DB::table('master_project')->where('id', $a)->value('project_code'), DB::table('master_project')->where('id', $a)->value('project_name')]);
        $this->assertNull(DB::table('master_project_detail')->where('id', $terhapus)->value('deleted_at'));
        $this->assertSame('90001', DB::table('master_project')->where('id', $b)->value('project_code'));
        $this->assertSame(self::ENL, $this->pemilikKolom($b));
    }

    public function test_pindah_pemilik_ditolak_bila_kode_bentrok_tanpa_konfirmasi_atau_bukan_admin(): void
    {
        $this->login();
        $id = $this->project('21317', 'EPSCC Meindo');
        $this->detail($id, self::ME, '21317', 'EPSCC Meindo');
        $this->detail($id, self::MEB, '21318', 'Kode Bentrok');
        $this->project('21318', 'Project Lain');

        $this->post(route('master.project.transfer-owner', $id), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])->assertSessionHasErrors('to_company_id');
        $this->post(route('master.project.transfer-owner', $id), ['to_company_id' => self::MEB])->assertSessionHasErrors('confirm_transfer');
        $this->assertSame(self::ME, app(ProjectOwnerDetail::class)->ownerCompanyIdOf($id));

        $this->loginBukanAdmin(['update_project']);
        $this->post(route('master.project.transfer-owner', $id), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])->assertForbidden();
    }

    public function test_form_edit_menampilkan_pemilik_dan_kartu_pindah_pemilik_untuk_admin(): void
    {
        $this->login();
        $id = $this->project('73101', 'Proyek Bintan', self::MEB);
        $this->detail($id, self::MEB, '73101', 'Proyek Bintan');

        $html = $this->get(route('master.project.edit', $id))->assertOk()->getContent();

        $this->assertStringContainsString('MEB - PT Meitech Eka Bintan', $html);
        $this->assertStringContainsString('bukan ME', $html);
        $this->assertStringContainsString('id="project-owner-transfer"', $html);
        $this->assertStringContainsString(route('master.project.transfer-owner', $id), $html);
    }

    // ---- daftar Project ----

    public function test_daftar_project_menampilkan_kolom_pemilik(): void
    {
        $this->login();
        $this->project('21000');
        $this->project('73101', pemilik: self::MEB);

        $json = $this->getJson('/master/project', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();

        $this->assertContains('pemilik', array_column($json['columns'], 'data'));
        $pemilik = collect($json['data'])->pluck('pemilik', 'project_code')->all();
        $this->assertSame(['21000' => 'ME', '73101' => 'MEB'], array_intersect_key($pemilik, ['21000' => 1, '73101' => 1]));
    }
}
