<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
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
 * Satu kode satu project (v3.21.0, diperketat dari v3.20.0 atas permintaan user 2026-11-25): Project Code Client X
 * hanya boleh menunjuk SATU project untuk SEMUA company. Beberapa company boleh memakai X di project yang sama; X yang
 * sudah dipakai baris detail aktif company mana pun (atau Project Code project aktif) di project lain ditolak. Kode
 * induk ikut aturan ini karena baris pemilik = cermin master_project.project_code: Create Project, Import Project,
 * Edit kode project, Pindah pemilik, dan Restore project juga dicek. Tetap dari v3.20.0: baris terhapus tidak dihitung,
 * ubah dicek hanya bila kode berubah, import mengecek antar baris file yang sama. Pindah company tidak dicek lagi:
 * kode dan project baris tidak berubah. Tanpa aturan ini kode menunjuk dua project: dropdown dokumen SPB ambigu dan
 * urutan nomor SPB per kode (`21329-SPB-...`) tercampur.
 */
class ProjectCodeOneProjectTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private const ENL = 648;

    private const PHM = 116; // company klien, sudah dihapus

    private const ATURAN = ': satu kode hanya boleh menunjuk satu project (nomor dokumen akan bentrok).';

    private const PESAN_21329 = 'Kode 21329 sudah dipakai sebagai Project Code Client project 21329 (company ME)'.self::ATURAN;

    /** project Meindo 21329 (baris pemilik ME 21329) */
    private int $projectMe;

    /** project milik MEB 73029 (baris pemilik MEB 73029) */
    private int $projectMeb;

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
            ['id' => self::PHM, 'company_code' => 'PHM', 'company_name' => 'Pertamina Hulu Mahakam', 'deleted_at' => '2026-10-01 08:00:00'],
        ]);

        $this->projectMe = $this->project('21329');
        $this->detail($this->projectMe, self::ME, '21329');
        $this->projectMeb = $this->project('73029', self::MEB);
        $this->detail($this->projectMeb, self::MEB, '73029');

        $this->login();
    }

    /** admin Master Data di hub (is_admin) */
    private function login(): void
    {
        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function project(string $kode, ?int $pemilik = null, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => 'Project '.$kode, 'internal_external' => 'E', 'owner_company_id' => $pemilik,
            'deleted_at' => $terhapus ? '2026-11-01 08:00:00' : null,
        ]);
    }

    private function detail(int $projectId, int $company, string $kode, bool $terhapus = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode,
            'project_name_client' => 'Klien '.$kode, 'deleted_at' => $terhapus ? '2026-11-01 08:00:00' : null,
        ]);
    }

    private function tambah(int $projectId, int $company, string $kode)
    {
        return $this->post(route('master.project-detail.store'), [
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Klien '.$kode,
        ]);
    }

    /** @param  array<string, mixed>  $ubah */
    private function simpan(int $detailId, array $ubah)
    {
        $row = DB::table('master_project_detail')->where('id', $detailId)->first();

        return $this->post(route('master.project-detail.store'), $ubah + [
            'id' => $detailId, 'project_id' => $row->project_id, 'company_id' => $row->company_id,
            'project_code_client' => $row->project_code_client, 'project_name_client' => $row->project_name_client,
        ]);
    }

    /** @return list<int> project yang memakai kode itu di baris detail aktif */
    private function projectBerkode(string $kode): array
    {
        return DB::table('master_project_detail')->where('project_code_client', $kode)->whereNull('deleted_at')
            ->distinct()->orderBy('project_id')->pluck('project_id')->map(fn ($id) => (int) $id)->all();
    }

    private function upload(string $entity, string $csv): string
    {
        $response = $this->post(route('master.import.preview', $entity), ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)]);
        $response->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    /** @return list<array<string, mixed>> */
    private function preview(string $entity, string $token): array
    {
        return $this->get(route('master.import.show', [$entity, $token]))->viewData('analysis')['rows'];
    }

    // ---- Project Detail: tambah baris ----

    public function test_tambah_baris_dengan_kode_project_lain_ditolak_untuk_company_mana_pun(): void
    {
        // company yang sama (aturan v3.20.0) dan company lain (diperketat v3.21.0)
        $this->tambah($this->projectMeb, self::ME, '21329')->assertSessionHasErrors(['project_code_client' => self::PESAN_21329]);
        $this->tambah($this->projectMeb, self::ENL, ' 21329 ')->assertSessionHasErrors(['project_code_client' => self::PESAN_21329]);

        $this->assertSame([$this->projectMe], $this->projectBerkode('21329'));
        $this->assertSame(1, DB::table('master_project_detail')->where('project_id', $this->projectMeb)->count());
    }

    public function test_beberapa_company_boleh_memakai_kode_yang_sama_di_project_yang_sama(): void
    {
        $this->tambah($this->projectMe, self::MEB, '21329')->assertSessionHasNoErrors();
        $this->tambah($this->projectMeb, self::ENL, '73029')->assertSessionHasNoErrors();
        // kode baru yang belum dipakai project mana pun
        $this->tambah($this->projectMeb, self::ME, '21400')->assertSessionHasNoErrors();

        $this->assertSame([$this->projectMe], $this->projectBerkode('21329'));
        $this->assertSame([$this->projectMeb], $this->projectBerkode('73029'));
        $this->assertSame([$this->projectMeb], $this->projectBerkode('21400'));
    }

    public function test_project_code_project_aktif_lain_ikut_dihitung_walau_tanpa_baris_detail(): void
    {
        $this->project('21700'); // project tanpa baris pemilik

        $this->tambah($this->projectMeb, self::ENL, '21700')
            ->assertSessionHasErrors(['project_code_client' => 'Kode 21700 sudah dipakai sebagai Project Code project 21700'.self::ATURAN]);
    }

    public function test_baris_dan_project_terhapus_tidak_dihitung(): void
    {
        $lain = $this->project('21500');
        $this->detail($lain, self::ENL, '21400', terhapus: true);
        $this->project('21600', terhapus: true);

        $this->tambah($this->projectMeb, self::ME, '21400')->assertSessionHasNoErrors();
        $this->tambah($this->projectMeb, self::ENL, '21600')->assertSessionHasNoErrors();
    }

    // ---- Project Detail: ubah kode ----

    public function test_ubah_kode_ke_kode_project_lain_ditolak_dan_kode_tetap_tidak_dicek(): void
    {
        $baris = $this->detail($this->projectMeb, self::ENL, '21400');

        // admin mengonfirmasi ubah kunci unik (MasterUniqueKeyGuard), lalu cek satu kode satu project
        $this->simpan($baris, ['project_code_client' => '21329', 'confirm_key_change' => '1'])->assertSessionHasErrors(['project_code_client' => self::PESAN_21329]);
        $this->assertSame('21400', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));

        $this->simpan($baris, ['project_code_client' => '21401', 'confirm_key_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('21401', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));

        // data lama yang terlanjur dobel tetap bisa diubah namanya
        $dobel = $this->detail($this->projectMeb, self::PHM, '21329');
        $this->simpan($dobel, ['project_name_client' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', DB::table('master_project_detail')->where('id', $dobel)->value('project_name_client'));
    }

    // ---- Project Detail: import ----

    public function test_import_project_detail_menolak_kode_project_lain_di_db_dan_di_baris_lain_file(): void
    {
        $proyekC = $this->project('21500');

        $token = $this->upload('project-detail', "Project Code,Project Code Client,Project Name Client,Company Code\n"
            ."73029,21329,ENL di project MEB,ENL\n"     // 21329 = project 21329
            ."21329,21329,MEB di project Meindo,MEB\n"  // project yang sama: boleh
            ."73029,21600,ME di project MEB,ME\n"       // kode baru
            ."21500,21600,ENL di project 21500,ENL\n"   // kode yang sama, project lain, file yang sama
            ."73029,21600,ENL di project MEB,ENL\n");   // kode yang sama, project yang sama, company lain: boleh

        $rows = $this->preview('project-detail', $token);
        $this->assertSame(['error', 'new', 'new', 'error', 'new'], array_column($rows, 'status'));
        $this->assertSame(self::PESAN_21329, $rows[0]['message']);
        $this->assertSame('Kode 21600 sudah dipakai sebagai Project Code Client project 73029 di baris lain file ini'.self::ATURAN, $rows[3]['message']);

        $this->post(route('master.import.commit', ['project-detail', $token]))->assertSessionHasNoErrors();

        $this->assertSame([$this->projectMe], $this->projectBerkode('21329'));
        $this->assertSame([$this->projectMeb], $this->projectBerkode('21600'));
        $this->assertSame(0, DB::table('master_project_detail')->where('project_id', $proyekC)->count());
    }

    // ---- Project Detail: Pindah company & Restore ----

    public function test_pindah_company_tidak_mengubah_kode_dan_project_jadi_tidak_dicek(): void
    {
        $phm = $this->detail($this->projectMeb, self::PHM, '21800');

        $this->post(route('master.project-detail.move-company', $phm), ['move_company_id' => self::ENL, 'confirm_move_company' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::ENL, (int) DB::table('master_project_detail')->where('id', $phm)->value('company_id'));
    }

    public function test_restore_baris_yang_kodenya_kini_dipakai_project_lain_ditolak(): void
    {
        $terhapus = $this->detail($this->projectMeb, self::ENL, '21329', terhapus: true);

        $this->post(route('master.project-detail.restore', $terhapus))
            ->assertSessionHas('error_message', 'Project Detail 21329 tidak dapat dipulihkan: '.self::PESAN_21329);
        $this->assertNotNull(DB::table('master_project_detail')->where('id', $terhapus)->value('deleted_at'));

        $samaProject = $this->detail($this->projectMeb, self::ENL, '73029', terhapus: true);
        $this->post(route('master.project-detail.restore', $samaProject))
            ->assertSessionHas('success_message', fn ($pesan) => str_contains($pesan, 'berhasil dipulihkan'));
        $this->assertNull(DB::table('master_project_detail')->where('id', $samaProject)->value('deleted_at'));
    }

    // ---- Project: Create, Import, Edit kode, Pindah pemilik, Restore ----

    public function test_create_project_dengan_kode_yang_dipakai_baris_detail_project_lain_ditolak(): void
    {
        $this->detail($this->projectMeb, self::ENL, 'X100');
        $pesan = 'Kode X100 sudah dipakai sebagai Project Code Client project 73029 (company ENL)'.self::ATURAN;

        // pemilik bawaan (dulu tidak dicek) dan pemilik company lain
        $this->post(route('master.project.store'), ['project_code' => 'X100', 'project_name' => 'Proyek X'])
            ->assertSessionHasErrors(['project_code' => $pesan]);
        $this->post(route('master.project.store'), ['project_code' => 'X100', 'project_name' => 'Proyek X', 'project_owner_other' => '1', 'project_owner_company_id' => self::ENL])
            ->assertSessionHasErrors(['project_code' => $pesan]);
        $this->assertFalse(DB::table('master_project')->where('project_code', 'X100')->exists());

        $this->post(route('master.project.store'), ['project_code' => 'X101', 'project_name' => 'Proyek X'])->assertSessionHasNoErrors();
        $this->assertTrue(DB::table('master_project')->where('project_code', 'X101')->exists());
    }

    public function test_import_project_baru_dengan_kode_yang_dipakai_baris_detail_project_lain_ditolak(): void
    {
        $this->detail($this->projectMeb, self::ENL, 'X100');

        $token = $this->upload('project', "Project Code,Project Name,Project Type\nX100,Proyek X,E\nX101,Proyek Y,E\n");

        $rows = $this->preview('project', $token);
        $this->assertSame(['error', 'new'], array_column($rows, 'status'));
        $this->assertSame('Kode X100 sudah dipakai sebagai Project Code Client project 73029 (company ENL)'.self::ATURAN, $rows[0]['message']);
    }

    public function test_edit_kode_project_ke_kode_yang_dipakai_baris_detail_project_lain_ditolak(): void
    {
        $this->detail($this->projectMeb, self::ENL, 'X100');

        $this->post(route('master.project.store'), ['id' => $this->projectMe, 'project_code' => 'X100', 'project_name' => 'Project 21329', 'confirm_key_change' => '1'])
            ->assertSessionHasErrors(['project_code' => 'Kode X100 sudah dipakai sebagai Project Code Client project 73029 (company ENL)'.self::ATURAN]);
        $this->assertSame('21329', DB::table('master_project')->where('id', $this->projectMe)->value('project_code'));
        $this->assertSame([$this->projectMe], $this->projectBerkode('21329'), 'baris pemilik (cermin) tidak ikut berubah');

        // kode tetap: ubah nama saja tidak dicek
        $this->post(route('master.project.store'), ['id' => $this->projectMe, 'project_code' => '21329', 'project_name' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', DB::table('master_project')->where('id', $this->projectMe)->value('project_name'));
    }

    public function test_pindah_pemilik_ditolak_bila_kode_baru_project_dipakai_baris_detail_project_lain(): void
    {
        // data lama: ENL memakai kode 21330 di project MEB, lalu project Meindo punya baris ENL berkode sama
        $this->detail($this->projectMeb, self::ENL, '21330');
        $this->detail($this->projectMe, self::ENL, '21330');

        $this->post(route('master.project.transfer-owner', $this->projectMe), ['to_company_id' => self::ENL, 'confirm_transfer' => '1'])
            ->assertSessionHasErrors(['to_company_id' => 'Kode 21330 sudah dipakai sebagai Project Code Client project 73029 (company ENL)'.self::ATURAN]);
        $this->assertSame('21329', DB::table('master_project')->where('id', $this->projectMe)->value('project_code'));
        $this->assertNull(DB::table('master_project')->where('id', $this->projectMe)->value('owner_company_id'));
    }

    public function test_restore_project_yang_kodenya_kini_dipakai_baris_detail_project_lain_ditolak(): void
    {
        $terhapus = $this->project('X200', terhapus: true);
        $this->detail($this->projectMeb, self::ENL, 'X200');

        $this->post(route('master.project.restore', $terhapus))
            ->assertSessionHas('error_message', fn ($pesan) => str_contains($pesan, 'tidak dapat dipulihkan: Kode X200 sudah dipakai sebagai Project Code Client project 73029 (company ENL)'));
        $this->assertNotNull(DB::table('master_project')->where('id', $terhapus)->value('deleted_at'));
    }
}
