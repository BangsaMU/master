<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Imports\Master\ProjectImport;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * Baris Project Detail company pemilik (ProjectOwnerDetail, sejak v3.12.0):
 *  - project BARU (form, import preview, import lama): baris pemilik untuk MasterCrudConfig.PROJECT_OWNER_COMPANY_ID,
 *    bukan lagi company berkode ME; lewat session delegasi company lain ditambah baris company pembuat;
 *  - project DIUBAH (form, import): baris pemilik yang cermin kode lama ikut kode/nama baru, baris company lain dan
 *    baris klien tidak disentuh;
 *  - perintah master:project-detail-mirror merapikan data lama (--dry-run hanya menampilkan).
 */
class ProjectOwnerMirrorTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const PEMILIK_LAIN = 2; // bukan berkode ME: membuktikan config yang dipakai, bukan kode company

    private const MEB = 647;

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
        $this->createDashboardSettings();
        DB::connection('db_master')->setPdo(DB::connection()->getPdo());

        DB::table('master_company')->insert([
            ['id' => self::ME, 'company_code' => 'ME'],
            ['id' => self::PEMILIK_LAIN, 'company_code' => 'HLD'],
            ['id' => self::KLIEN, 'company_code' => 'PHM'],
            ['id' => self::MEB, 'company_code' => 'MEB'],
        ]);
    }

    /** tim master-data di hub (is_admin) */
    private function login(): User
    {
        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $user = User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']);
        $this->actingAs($user);

        return $user;
    }

    /** user aplikasi lain (SPB, company $company) yang membuka hub lewat launch token: izin hanya dari delegasi */
    private function loginDelegasi(int $company, array $izin = []): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => $izin]);
        $this->actingAs(User::create(['name' => 'Admin MEB', 'email' => 'meb'.uniqid().'@meindo.test']));
        $this->withSession([MasterScope::SESSION_KEY => [
            'app_code' => 'APP32', 'app_name' => 'SPB V2', 'company_id' => $company, 'company_code' => 'MEB',
            'all_co' => false, 'mods' => ['master_project' => ['create', 'update']], 'back' => null, 'exp' => time() + 600,
        ]]);
    }

    private function project(string $kode, ?string $nama = null, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => $nama ?? 'Project '.$kode, 'deleted_at' => $terhapus ? now() : null,
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

    private function simpanBaru(string $kode, string $nama): int
    {
        $this->post(route('master.project.store'), ['project_code' => $kode, 'project_name' => $nama])
            ->assertRedirect(route('master.project.index'))->assertSessionHasNoErrors();

        return (int) DB::table('master_project')->where('project_code', strtoupper($kode))->value('id');
    }

    /** Upload file import lalu kembalikan token preview. */
    private function upload(string $csv): string
    {
        $response = $this->post(route('master.import.preview', 'project'), ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)]);
        $response->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    private const HEADER_CSV = "Project Code,Project Name,Project Type,Project Start Date,Project Complete Date,Project Remarks\n";

    // ---- project baru: baris pemilik dari PROJECT_OWNER_COMPANY_ID ----

    public function test_form_project_baru_membuat_baris_pemilik_dari_config_bukan_company_berkode_me(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => self::PEMILIK_LAIN]);
        $this->login();

        $id = $this->simpanBaru('p100', 'Proyek Seratus');

        $this->assertSame([
            ['company_id' => self::PEMILIK_LAIN, 'project_code_client' => 'P100', 'project_name_client' => 'Proyek Seratus'],
        ], $this->barisDetail($id));
        // tetap lewat model: Loggable mencatat pembuatannya
        $this->assertSame(1, ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'created')->count());
    }

    public function test_form_project_baru_bawaan_company_pemilik_id_1(): void
    {
        $this->login();

        $id = $this->simpanBaru('P101', 'Proyek Bawaan');

        $this->assertSame([self::ME], array_column($this->barisDetail($id), 'company_id'));
    }

    public function test_company_pemilik_tidak_terdaftar_memakai_aturan_lama_kode_me_lalu_company_pertama(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => 999]);
        $this->login();

        $this->assertSame([self::ME], array_column($this->barisDetail($this->simpanBaru('P102', 'Proyek Aturan Lama')), 'company_id'));

        // company pemilik yang sudah dihapus tidak dipakai
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => self::PEMILIK_LAIN]);
        DB::table('master_company')->where('id', self::PEMILIK_LAIN)->update(['deleted_at' => now()]);
        $this->assertSame([self::ME], array_column($this->barisDetail($this->simpanBaru('P103', 'Proyek Pemilik Terhapus')), 'company_id'));

        DB::table('master_company')->where('company_code', 'ME')->delete();
        $this->assertSame([self::KLIEN], array_column($this->barisDetail($this->simpanBaru('P104', 'Proyek Company Pertama')), 'company_id'));
    }

    public function test_tanpa_company_sama_sekali_project_tetap_tersimpan_tanpa_detail(): void
    {
        DB::table('master_company')->delete();
        $this->login();

        $id = $this->simpanBaru('P105', 'Proyek Tanpa Company');

        $this->assertGreaterThan(0, $id);
        $this->assertSame([], $this->barisDetail($id));
    }

    // ---- project baru lewat delegasi: baris company pembuat ----

    public function test_delegasi_company_lain_menambah_baris_company_pembuat_setelah_baris_pemilik(): void
    {
        $this->loginDelegasi(self::MEB);

        $id = $this->simpanBaru('P200', 'Proyek MEB');

        $this->assertSame([
            ['company_id' => self::ME, 'project_code_client' => 'P200', 'project_name_client' => 'Proyek MEB'],
            ['company_id' => self::MEB, 'project_code_client' => 'P200', 'project_name_client' => 'Proyek MEB'],
        ], $this->barisDetail($id));
        $this->assertSame(2, ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'created')->count());
    }

    public function test_delegasi_company_pemilik_atau_tanpa_company_hanya_baris_pemilik(): void
    {
        $this->loginDelegasi(self::ME);
        $this->assertSame([self::ME], array_column($this->barisDetail($this->simpanBaru('P201', 'Proyek ME')), 'company_id'));

        $this->loginDelegasi(0);
        $this->assertSame([self::ME], array_column($this->barisDetail($this->simpanBaru('P202', 'Proyek Semua Company')), 'company_id'));
    }

    public function test_delegasi_kedaluwarsa_tidak_menambah_baris_company_pembuat(): void
    {
        $this->login();
        $this->withSession([MasterScope::SESSION_KEY => [
            'app_code' => 'APP32', 'app_name' => 'SPB V2', 'company_id' => self::MEB, 'company_code' => 'MEB',
            'all_co' => false, 'mods' => ['master_project' => ['create']], 'back' => null, 'exp' => time() - 1,
        ]]);

        $this->assertSame([self::ME], array_column($this->barisDetail($this->simpanBaru('P203', 'Proyek Tim Master')), 'company_id'));
    }

    // ---- project diubah: cermin baris pemilik ----

    public function test_ubah_nama_project_mengubah_baris_cermin_pemilik_saja(): void
    {
        $this->login();
        $id = $this->project('P300', 'Nama Lama');
        $this->detail($id, self::ME, 'P300', 'Nama Lama');
        $this->detail($id, self::MEB, 'P300', 'Nama Lama');      // company lain, kodenya sama dengan project
        $this->detail($id, self::KLIEN, '73001', 'Kontrak PHM'); // baris klien
        $lain = $this->project('P301', 'Project Lain');
        $this->detail($lain, self::ME, 'P301', 'Project Lain');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'P300', 'project_name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['company_id' => self::ME, 'project_code_client' => 'P300', 'project_name_client' => 'Nama Baru'],
            ['company_id' => self::MEB, 'project_code_client' => 'P300', 'project_name_client' => 'Nama Lama'],
            ['company_id' => self::KLIEN, 'project_code_client' => '73001', 'project_name_client' => 'Kontrak PHM'],
        ], $this->barisDetail($id));
        $this->assertSame('Project Lain', $this->barisDetail($lain)[0]['project_name_client']);

        // seperti edit manual Project Detail: tercatat lewat Loggable, sebelum/sesudah
        $log = ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'updated')->sole();
        $this->assertSame('Nama Lama', $log->properties['before']['project_name_client']);
        $this->assertSame('Nama Baru', $log->properties['after']['project_name_client']);
    }

    public function test_ubah_kode_project_mengubah_kode_dan_nama_baris_cermin(): void
    {
        $this->login();
        $id = $this->project('P310', 'Nama Project');
        $cermin = $this->detail($id, self::ME, 'P310', 'Nama Client Yang Berbeda'); // cermin ditentukan kode, nama ikut dirapikan
        $this->detail($id, self::MEB, 'P310');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'p311', 'project_name' => 'Nama Project', 'confirm_key_change' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_project', ['id' => $id, 'project_code' => 'P311']);
        $this->assertDatabaseHas('master_project_detail', ['id' => $cermin, 'project_code_client' => 'P311', 'project_name_client' => 'Nama Project']);
        $this->assertSame('P310', $this->barisDetail($id)[1]['project_code_client']); // baris MEB tetap
    }

    public function test_baris_pemilik_berkode_sendiri_bukan_cermin_tidak_diubah(): void
    {
        $this->login();
        $id = $this->project('P320', 'Nama Lama');
        $this->detail($id, self::ME, 'KONTRAK-1', 'Nama Kontrak');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'P320', 'project_name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->assertSame([['company_id' => self::ME, 'project_code_client' => 'KONTRAK-1', 'project_name_client' => 'Nama Kontrak']], $this->barisDetail($id));
        $this->assertSame(0, ActivityLog::where('model_type', 'like', '%ProjectDetail')->count());
    }

    public function test_ubah_kolom_lain_tidak_menyentuh_baris_detail(): void
    {
        $this->login();
        $id = $this->project('P330', 'Nama Project');
        // nama client sengaja berbeda: hanya dirapikan saat kode/nama project berubah (atau lewat perintah)
        $this->detail($id, self::ME, 'P330', 'Nama Client Lama');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'P330', 'project_name' => 'Nama Project', 'project_remarks' => 'catatan baru'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_project', ['id' => $id, 'project_remarks' => 'catatan baru']);
        $this->assertSame('Nama Client Lama', $this->barisDetail($id)[0]['project_name_client']);
    }

    public function test_cermin_mengikuti_company_pemilik_dari_config(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => self::PEMILIK_LAIN]);
        $this->login();
        $id = $this->project('P340', 'Nama Lama');
        $this->detail($id, self::ME, 'P340', 'Nama Lama');
        $this->detail($id, self::PEMILIK_LAIN, 'P340', 'Nama Lama');

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'P340', 'project_name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Nama Lama', 'Nama Baru'], array_column($this->barisDetail($id), 'project_name_client'));
    }

    public function test_cermin_dilewati_bila_kode_baru_sudah_dipakai_baris_lain_project_itu(): void
    {
        $this->login();
        $id = $this->project('P350', 'Nama Project');
        $this->detail($id, self::ME, 'P350');
        $this->detail($id, self::ME, 'P351', terhapus: true); // index unik (kode client, project, company) ikut baris terhapus

        $this->post(route('master.project.store'), ['id' => $id, 'project_code' => 'P351', 'project_name' => 'Nama Project', 'confirm_key_change' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_project', ['id' => $id, 'project_code' => 'P351']);
        $this->assertSame('P350', $this->barisDetail($id)[0]['project_code_client']);
    }

    // ---- import preview ----

    public function test_import_project_baru_memakai_company_pemilik_dari_config(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => self::PEMILIK_LAIN]);
        $this->login();

        $token = $this->upload(self::HEADER_CSV."P400,Proyek Import,Internal,,,\nP401,Proyek Import Dua,External,,,\n");
        $this->post(route('master.import.commit', ['project', $token]));

        foreach (['P400' => 'Proyek Import', 'P401' => 'Proyek Import Dua'] as $kode => $nama) {
            $id = (int) DB::table('master_project')->where('project_code', $kode)->value('id');
            $this->assertSame([['company_id' => self::PEMILIK_LAIN, 'project_code_client' => $kode, 'project_name_client' => $nama]], $this->barisDetail($id));
        }
        $this->assertSame(2, ActivityLog::where('model_type', 'like', '%ProjectDetail')->where('action', 'created')->count());
    }

    public function test_import_lewat_delegasi_company_lain_membuat_dua_baris(): void
    {
        $this->loginDelegasi(self::MEB, ['create_project']);

        $token = $this->upload(self::HEADER_CSV."P410,Proyek Import MEB,Internal,,,\n");
        $this->post(route('master.import.commit', ['project', $token]));

        $id = (int) DB::table('master_project')->where('project_code', 'P410')->value('id');
        $this->assertSame([self::ME, self::MEB], array_column($this->barisDetail($id), 'company_id'));
    }

    public function test_import_update_nama_project_mengubah_baris_cermin_dan_mencatat_alasannya(): void
    {
        $user = $this->login();
        $id = $this->project('P420', 'Nama Lama');
        $cermin = $this->detail($id, self::ME, 'P420', 'Nama Lama');
        $this->detail($id, self::MEB, 'P420', 'Nama Lama');
        $this->detail($id, self::KLIEN, '73002', 'Kontrak PHM');

        $token = $this->upload(self::HEADER_CSV."P420,Nama Baru,,,,\n");
        $this->post(route('master.import.commit', ['project', $token]), ['allow_update' => '1', 'update_reason' => 'koreksi nama kontrak']);

        $this->assertSame('Nama Baru', DB::table('master_project')->where('id', $id)->value('project_name'));
        $this->assertSame(['Nama Baru', 'Nama Lama', 'Kontrak PHM'], array_column($this->barisDetail($id), 'project_name_client'));

        $log = ActivityLog::where('model_type', 'like', '%ProjectDetail')->sole();
        $this->assertSame('updated', $log->action);
        $this->assertSame($cermin, (int) $log->model_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Gita Samudra updated ProjectDetail (via import: koreksi nama kontrak)', $log->description);
        $this->assertSame('Nama Lama', $log->properties['before']['project_name_client']);
        $this->assertSame('Nama Baru', $log->properties['after']['project_name_client']);
    }

    /**
     * @return list<array{table: string, action: string, id: int}> payload broadcast yang terkirim ke Senada
     */
    private function broadcastTerkirim(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->data()['payload'] ?? [])
            ->map(fn ($payload) => ['table' => $payload['table'] ?? '', 'action' => $payload['action'] ?? '', 'id' => (int) ($payload['id'] ?? 0)])
            ->values()->all();
    }

    public function test_cermin_dari_form_ikut_dibroadcast_seperti_edit_manual_project_detail(): void
    {
        config(['MasterConfig.senada.active' => true, 'MasterConfig.senada.url' => 'http://senada.test']);
        $this->login();
        // id khusus: MasterBroadcastService membuang broadcast kembar (tabel:id:aksi) dalam satu proses
        DB::table('master_project')->insert(['id' => 9101, 'project_code' => 'P600', 'project_name' => 'Nama Lama']);
        DB::table('master_project_detail')->insert(['id' => 9111, 'project_id' => 9101, 'company_id' => self::ME, 'project_code_client' => 'P600', 'project_name_client' => 'Nama Lama']);

        $this->post(route('master.project.store'), ['id' => 9101, 'project_code' => 'P600', 'project_name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->assertContains(['table' => 'master_project_detail', 'action' => 'updated', 'id' => 9111], $this->broadcastTerkirim());
    }

    public function test_cermin_dari_import_dibroadcast_per_baris_setelah_disimpan(): void
    {
        config(['MasterConfig.senada.active' => true, 'MasterConfig.senada.url' => 'http://senada.test']);
        $this->login();
        DB::table('master_project')->insert(['id' => 9201, 'project_code' => 'P610', 'project_name' => 'Nama Lama']);
        DB::table('master_project_detail')->insert(['id' => 9211, 'project_id' => 9201, 'company_id' => self::ME, 'project_code_client' => 'P610', 'project_name_client' => 'Nama Lama']);

        $token = $this->upload(self::HEADER_CSV."P610,Nama Baru,,,,\nP611,Proyek Baru,,,,\n");
        $this->post(route('master.import.commit', ['project', $token]), ['allow_update' => '1', 'update_reason' => 'koreksi nama kontrak']);

        $terkirim = $this->broadcastTerkirim();
        $this->assertContains(['table' => 'master_project_detail', 'action' => 'updated', 'id' => 9211], $terkirim);
        // baris detail project BARU tetap tanpa broadcast per baris, seperti sebelumnya
        $this->assertCount(1, array_filter($terkirim, fn ($payload) => $payload['table'] === 'master_project_detail'));
        $this->assertSame('Nama Baru', DB::table('master_project_detail')->where('id', 9211)->value('project_name_client'));
    }

    public function test_import_update_kolom_lain_tidak_menyentuh_baris_detail(): void
    {
        $this->login();
        $id = $this->project('P430', 'Nama Project');
        $this->detail($id, self::ME, 'P430', 'Nama Client Lama');

        $token = $this->upload(self::HEADER_CSV."P430,Nama Project,,,,catatan baru\n");
        $this->post(route('master.import.commit', ['project', $token]), ['allow_update' => '1', 'update_reason' => 'tambah catatan']);

        $this->assertSame('catatan baru', DB::table('master_project')->where('id', $id)->value('project_remarks'));
        $this->assertSame('Nama Client Lama', $this->barisDetail($id)[0]['project_name_client']);
        $this->assertSame(0, ActivityLog::where('model_type', 'like', '%ProjectDetail')->count());
    }

    public function test_import_lama_project_import_memakai_company_pemilik_dari_config(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => self::PEMILIK_LAIN]);
        $this->login();

        (new ProjectImport)->collection(collect([
            ['project_code' => 'p440', 'project_name' => 'Proyek Import Lama', 'project_type' => 'Internal'],
        ]));

        $id = (int) DB::table('master_project')->where('project_code', 'P440')->value('id');
        $this->assertSame([['company_id' => self::PEMILIK_LAIN, 'project_code_client' => 'P440', 'project_name_client' => 'Proyek Import Lama']], $this->barisDetail($id));
    }

    // ---- perintah master:project-detail-mirror ----

    /** @return array{sesuai: int, nama: int, kode: int, tanpa: int, terhapus: int, ganda: int} id project */
    private function dataPerapian(): array
    {
        $sesuai = $this->project('P500', 'Sudah Sesuai');
        $this->detail($sesuai, self::ME, 'P500', 'Sudah Sesuai');
        $this->detail($sesuai, self::MEB, 'P500', 'Nama Versi MEB');

        $nama = $this->project('P501', 'Nama Project');
        $this->detail($nama, self::ME, 'P501', 'Nama Client Lama');
        $this->detail($nama, self::KLIEN, '73003', 'Kontrak PHM');

        $kode = $this->project('P502', 'Kode Berbeda');
        $this->detail($kode, self::ME, 'P502-OLD', 'Kode Berbeda');

        $tanpa = $this->project('P503', 'Tanpa Detail');

        $terhapus = $this->project('P504', 'Detail Terhapus');
        $this->detail($terhapus, self::ME, 'P504', 'Nama Sebelum Dihapus', terhapus: true);

        $ganda = $this->project('P505', 'Detail Ganda');
        $this->detail($ganda, self::ME, 'P505', 'Detail Ganda Lama');
        $this->detail($ganda, self::ME, 'P505-B', 'Detail Ganda B');

        $projectTerhapus = $this->project('P506', 'Project Terhapus', terhapus: true);
        $this->detail($projectTerhapus, self::ME, 'P506', 'Nama Lain');

        return compact('sesuai', 'nama', 'kode', 'tanpa', 'terhapus', 'ganda');
    }

    public function test_perintah_dry_run_hanya_menampilkan_tanpa_menulis(): void
    {
        $this->dataPerapian();
        $sebelum = DB::table('master_project_detail')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->artisan('master:project-detail-mirror', ['--dry-run' => true])
            ->expectsOutputToContain('P501')
            ->expectsOutputToContain('[dry-run] 6 project aktif diperiksa, company pemilik id 1: 1 dibuat, 1 dipulihkan, 2 disamakan, 1 dilewati (ganda), 0 gagal.')
            ->expectsOutputToContain('Jalankan tanpa --dry-run')
            ->assertSuccessful();

        $this->assertSame($sebelum, DB::table('master_project_detail')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_perintah_merapikan_baris_pemilik_dan_tidak_menyentuh_baris_lain(): void
    {
        $id = $this->dataPerapian();

        $this->artisan('master:project-detail-mirror')
            ->expectsOutputToContain('6 project aktif diperiksa, company pemilik id 1: 1 dibuat, 1 dipulihkan, 2 disamakan, 1 dilewati (ganda), 0 gagal.')
            ->assertSuccessful();

        // sudah sesuai: tidak berubah, baris MEB tetap dengan namanya sendiri
        $this->assertSame(['Sudah Sesuai', 'Nama Versi MEB'], array_column($this->barisDetail($id['sesuai']), 'project_name_client'));
        // nama disamakan, baris klien tetap
        $this->assertSame([
            ['company_id' => self::ME, 'project_code_client' => 'P501', 'project_name_client' => 'Nama Project'],
            ['company_id' => self::KLIEN, 'project_code_client' => '73003', 'project_name_client' => 'Kontrak PHM'],
        ], $this->barisDetail($id['nama']));
        // kode disamakan
        $this->assertSame([['company_id' => self::ME, 'project_code_client' => 'P502', 'project_name_client' => 'Kode Berbeda']], $this->barisDetail($id['kode']));
        // belum punya: dibuat
        $this->assertSame([['company_id' => self::ME, 'project_code_client' => 'P503', 'project_name_client' => 'Tanpa Detail']], $this->barisDetail($id['tanpa']));
        // terhapus: dipulihkan (bukan baris baru) lalu namanya disamakan
        $this->assertSame([['company_id' => self::ME, 'project_code_client' => 'P504', 'project_name_client' => 'Detail Terhapus']], $this->barisDetail($id['terhapus']));
        $this->assertSame(1, DB::table('master_project_detail')->where('project_id', $id['terhapus'])->count());
        // ganda: dilewati
        $this->assertSame(['Detail Ganda Lama', 'Detail Ganda B'], array_column($this->barisDetail($id['ganda']), 'project_name_client'));
        // project terhapus tidak diperiksa
        $this->assertDatabaseHas('master_project_detail', ['project_code_client' => 'P506', 'project_name_client' => 'Nama Lain']);

        // jalan kedua: tidak ada lagi yang diubah (idempoten)
        $this->artisan('master:project-detail-mirror')
            ->expectsOutputToContain('0 dibuat, 0 dipulihkan, 0 disamakan, 1 dilewati (ganda), 0 gagal.')
            ->assertSuccessful();
    }

    public function test_perintah_gagal_per_project_tidak_menghentikan_project_lain(): void
    {
        $bentrok = $this->project('P510', 'Kode Dipakai Project Lain');
        $lain = $this->project('P511', 'Project Lain');
        $this->detail($lain, self::ME, 'P510', 'Salah Kode'); // kode client P510 dipakai project lain: ensure() menolak
        $ok = $this->project('P512', 'Tanpa Detail');

        $this->artisan('master:project-detail-mirror')
            ->expectsOutputToContain('1 gagal')
            ->assertFailed();

        $this->assertSame([], $this->barisDetail($bentrok));
        $this->assertSame('P511', $this->barisDetail($lain)[0]['project_code_client']); // baris project lain dirapikan
        $this->assertSame('P512', $this->barisDetail($ok)[0]['project_code_client']);
    }

    public function test_perintah_ditolak_di_aplikasi_klien(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => false]);
        $id = $this->project('P520', 'Nama Project');
        $this->detail($id, self::ME, 'P520', 'Nama Client Lama');

        $this->artisan('master:project-detail-mirror')->expectsOutputToContain('hanya untuk aplikasi Master Data')->assertFailed();

        $this->assertSame('Nama Client Lama', $this->barisDetail($id)[0]['project_name_client']);
    }

    public function test_reconcile_melaporkan_sebelum_dan_sesudah(): void
    {
        $id = $this->project('P530', 'Nama Project');
        $this->detail($id, self::ME, 'P530-OLD', 'Nama Client Lama');
        $project = DB::table('master_project')->where('id', $id)->first();

        $hasil = app(ProjectOwnerDetail::class)->reconcile($project, dryRun: true);

        $this->assertSame(['status' => 'updated', 'before' => 'P530-OLD | Nama Client Lama', 'after' => 'P530 | Nama Project', 'note' => null], $hasil);

        $baru = DB::table('master_project')->where('id', $this->project('P531'))->first();
        $this->assertSame(
            ['status' => 'created', 'before' => null, 'after' => 'P531 | Project P531', 'note' => null],
            app(ProjectOwnerDetail::class)->reconcile($baru)
        );
        $this->assertSame('ok', app(ProjectOwnerDetail::class)->reconcile($baru)['status']);
    }
}
