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
 * Satu company satu Project Code Client per project (v3.20.0, dari diskusi SPB 2026-11-25 "ME ikut project MEB"):
 * kode client yang dipakai baris aktif company C di project lain ditolak di semua jalan halaman Project Detail
 * (tambah, edit kode, import, Pindah company, Restore), dengan pesan yang sama seperti Create Project / Pindah pemilik
 * (ProjectOwnerDetail::assertCodeFreeForCompany). Tanpa cek ini ME bisa punya kode 21329 di dua project: dropdown
 * dokumen SPB ambigu dan urutan nomor SPB (`21329-SPB-...`) tercampur antar project. Kode yang sama di company lain
 * tetap boleh, baris terhapus tidak dihitung, dan baris lama yang kodenya tidak diubah tetap bisa disimpan.
 */
class ProjectDetailCodePerCompanyTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private const ENL = 648;

    private const PHM = 116; // company klien, sudah dihapus

    private const PESAN_21329 = 'Kode 21329 sudah dipakai company ME sebagai Project Code Client project 21329: nomor dokumen company itu akan bentrok.';

    /** project Meindo 21329 (baris pemilik ME 21329) */
    private int $projectMe;

    /** project milik MEB 73029 (baris pemilik MEB 73029) yang ingin diikuti ME */
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

    private function project(string $kode, ?int $pemilik = null): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => 'Project '.$kode, 'internal_external' => 'E', 'owner_company_id' => $pemilik,
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

    private function jumlahBaris(int $company, string $kode): int
    {
        return DB::table('master_project_detail')->where('company_id', $company)->where('project_code_client', $kode)->whereNull('deleted_at')->count();
    }

    // ---- tambah baris ----

    public function test_tambah_baris_dengan_kode_yang_sudah_dipakai_company_itu_di_project_lain_ditolak(): void
    {
        $this->tambah($this->projectMeb, self::ME, '21329')->assertSessionHasErrors(['project_code_client' => self::PESAN_21329]);
        $this->assertSame(1, $this->jumlahBaris(self::ME, '21329'), 'baris ME di project MEB tidak dibuat');

        $this->tambah($this->projectMeb, self::ME, ' 21329 ')->assertSessionHasErrors('project_code_client');
        $this->assertSame(0, DB::table('master_project_detail')->where('project_id', $this->projectMeb)->where('company_id', self::ME)->count());
    }

    public function test_tambah_baris_kode_yang_sama_di_company_lain_tetap_boleh(): void
    {
        // ME memakai 21329 di project Meindo; MEB / ENL boleh memakai kode yang sama
        $this->tambah($this->projectMe, self::MEB, '21329')->assertSessionHasNoErrors();
        $this->tambah($this->projectMeb, self::ENL, '21329')->assertSessionHasNoErrors();
        // ME ikut project MEB dengan kode yang belum dipakai ME
        $this->tambah($this->projectMeb, self::ME, '21400')->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jumlahBaris(self::MEB, '21329'));
        $this->assertSame(1, $this->jumlahBaris(self::ENL, '21329'));
        $this->assertSame(1, $this->jumlahBaris(self::ME, '21400'));
    }

    public function test_baris_terhapus_di_project_lain_tidak_dihitung(): void
    {
        $lama = $this->project('21400');
        $this->detail($lama, self::ME, '21400', terhapus: true);

        $this->tambah($this->projectMeb, self::ME, '21400')->assertSessionHasNoErrors();
        $this->assertSame(1, $this->jumlahBaris(self::ME, '21400'));
    }

    // ---- edit kode ----

    public function test_edit_kode_ke_kode_yang_dipakai_company_itu_di_project_lain_ditolak(): void
    {
        $baris = $this->detail($this->projectMeb, self::ME, '21400');

        // admin mengonfirmasi ubah kunci unik (MasterUniqueKeyGuard), lalu cek kode per company
        $this->simpan($baris, ['project_code_client' => '21329', 'confirm_key_change' => '1'])->assertSessionHasErrors(['project_code_client' => self::PESAN_21329]);
        $this->assertSame('21400', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));

        $this->simpan($baris, ['project_code_client' => '21401', 'confirm_key_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('21401', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));
    }

    public function test_baris_lama_yang_terlanjur_dobel_tetap_bisa_diubah_namanya_selama_kode_tidak_berubah(): void
    {
        $dobel = $this->detail($this->projectMeb, self::ME, '21329'); // data lama sebelum cek ini ada

        $this->simpan($dobel, ['project_name_client' => 'Sisi ME project MEB', 'project_code_client' => '21329'])->assertSessionHasNoErrors();
        $this->assertSame('Sisi ME project MEB', DB::table('master_project_detail')->where('id', $dobel)->value('project_name_client'));
    }

    // ---- import ----

    public function test_import_menolak_kode_yang_sudah_dipakai_company_itu_di_db_atau_di_baris_lain_file(): void
    {
        $proyekC = $this->project('21500');

        $response = $this->post(route('master.import.preview', 'project-detail'), ['file' => UploadedFile::fake()->createWithContent('import.csv',
            "Project Code,Project Code Client,Project Name Client,Company Code\n"
            ."73029,21329,ME di project MEB,ME\n"      // ME sudah memakai 21329 di project 21329
            ."21329,21329,MEB di project Meindo,MEB\n" // company lain: boleh
            ."73029,21600,ME di project MEB,ME\n"      // kode baru ME
            ."21500,21600,ME di project 21500,ME\n"    // kode baru yang sama, project lain, file yang sama
        )]);
        $response->assertRedirect();
        $token = basename($response->headers->get('Location'));

        $rows = $this->get(route('master.import.show', ['project-detail', $token]))->viewData('analysis')['rows'];
        $this->assertSame(['error', 'new', 'new', 'error'], array_column($rows, 'status'));
        $this->assertSame(self::PESAN_21329, $rows[0]['message']);
        $this->assertSame('Kode 21600 sudah dipakai company ME sebagai Project Code Client project 73029 di baris lain file ini: nomor dokumen company itu akan bentrok.', $rows[3]['message']);

        $this->post(route('master.import.commit', ['project-detail', $token]))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jumlahBaris(self::ME, '21329'));
        $this->assertSame(1, $this->jumlahBaris(self::MEB, '21329'));
        $this->assertSame([$this->projectMeb], DB::table('master_project_detail')->where('company_id', self::ME)->where('project_code_client', '21600')->pluck('project_id')->all());
        $this->assertSame(0, DB::table('master_project_detail')->where('project_id', $proyekC)->count());
    }

    // ---- Pindah company & Restore ----

    public function test_pindah_company_ke_company_yang_sudah_memakai_kode_itu_di_project_lain_ditolak(): void
    {
        $phm = $this->detail($this->projectMeb, self::PHM, '21329');

        $this->post(route('master.project-detail.move-company', $phm), ['move_company_id' => self::ME, 'confirm_move_company' => '1'])
            ->assertSessionHasErrors(['move_company_id' => self::PESAN_21329]);
        $this->assertSame(self::PHM, (int) DB::table('master_project_detail')->where('id', $phm)->value('company_id'));

        $this->post(route('master.project-detail.move-company', $phm), ['move_company_id' => self::ENL, 'confirm_move_company' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::ENL, (int) DB::table('master_project_detail')->where('id', $phm)->value('company_id'));
    }

    public function test_restore_baris_yang_kodenya_kini_dipakai_company_itu_di_project_lain_ditolak(): void
    {
        $terhapus = $this->detail($this->projectMeb, self::ME, '21329', terhapus: true);

        $this->post(route('master.project-detail.restore', $terhapus))
            ->assertSessionHas('error_message', 'Project Detail 21329 tidak dapat dipulihkan: '.self::PESAN_21329);
        $this->assertNotNull(DB::table('master_project_detail')->where('id', $terhapus)->value('deleted_at'));

        $bebas = $this->detail($this->projectMeb, self::ENL, '21329', terhapus: true);
        $this->post(route('master.project-detail.restore', $bebas))
            ->assertSessionHas('success_message', fn ($pesan) => str_contains($pesan, 'berhasil dipulihkan'));
        $this->assertNull(DB::table('master_project_detail')->where('id', $bebas)->value('deleted_at'));
    }
}
