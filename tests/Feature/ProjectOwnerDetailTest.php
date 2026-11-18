<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman Project di hub (master-data):
 *  - saklar "Tampilkan data terhapus" (admin): show_trashed=0 menyembunyikan baris terhapus, tanpa parameter perilaku lama;
 *  - master_project milik utama company pemilik (id 1) sehingga 1 project = 1 detail: tombol "Buat Detail" untuk project
 *    yang belum punya, dibuat/dipulihkan idempoten oleh POST master/project/{id}/owner-detail;
 *  - card ringkasan company vs Project Code Client (ProjectOwnerDetail::summary).
 */
class ProjectOwnerDetailTest extends TestCase
{
    use CreatesMasterTables;

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
        Schema::table('master_company', fn (Blueprint $table) => $table->string('company_name')->nullable());

        DB::table('master_company')->insert([
            ['id' => 1, 'company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah', 'deleted_at' => null],
            ['id' => 2, 'company_code' => 'MEB', 'company_name' => 'PT Meitech Eka Bintan', 'deleted_at' => null],
            ['id' => 3, 'company_code' => 'OLD', 'company_name' => 'Company Lama', 'deleted_at' => now()],
        ]);
    }

    private function login(bool $admin = true, array $izin = []): void
    {
        config(['test.denied_permissions' => $admin ? [] : ['is_admin'], 'test.allowed_permissions' => $izin ?: null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function project(string $kode, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => 'Project '.$kode, 'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    private function detail(int $projectId, int $company, string $kode, bool $terhapus = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Client '.$kode,
            'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function daftar(array $query = []): array
    {
        return $this->getJson('/master/project?'.http_build_query($query), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    private function baris(string $kode, array $query = []): ?array
    {
        return collect($this->daftar($query))->firstWhere('project_code', $kode);
    }

    // ---- saklar data terhapus ----

    public function test_tanpa_parameter_admin_tetap_melihat_data_terhapus(): void
    {
        $this->login();
        $this->project('21000');
        $this->project('21001', terhapus: true);

        $this->assertNotNull($this->baris('21001'));
        $this->assertStringContainsString('Terhapus', $this->baris('21001')['action']);
    }

    public function test_saklar_off_menyembunyikan_data_terhapus_dan_total_ikut(): void
    {
        $this->login();
        $this->project('21000');
        $this->project('21001', terhapus: true);

        $json = $this->getJson('/master/project?show_trashed=0', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();

        $this->assertSame(['21000'], collect($json['data'])->pluck('project_code')->all());
        $this->assertSame(1, $json['recordsTotal']);
    }

    public function test_saklar_on_menampilkan_data_terhapus(): void
    {
        $this->login();
        $this->project('21001', terhapus: true);

        $this->assertNotNull($this->baris('21001', ['show_trashed' => 1]));
    }

    public function test_non_admin_tidak_pernah_melihat_data_terhapus_walau_saklar_on(): void
    {
        $this->login(admin: false, izin: ['read_project']);
        $this->project('21001', terhapus: true);

        $this->assertNull($this->baris('21001', ['show_trashed' => 1]));
    }

    public function test_hideTrashedRequested_hanya_bila_parameter_nol(): void
    {
        $trash = app(MasterTrash::class);

        $this->get('/login');
        $this->assertFalse($trash->hideTrashedRequested());
        $this->app['request']->merge(['show_trashed' => '0']);
        $this->assertTrue($trash->hideTrashedRequested());
        $this->app['request']->merge(['show_trashed' => '1']);
        $this->assertFalse($trash->hideTrashedRequested());
    }

    // ---- tombol Buat Detail ----

    public function test_project_tanpa_detail_company_pemilik_diberi_tombol_buat_detail(): void
    {
        $this->login();
        $ada = $this->project('21000');
        $this->detail($ada, 1, '21000');
        $belum = $this->project('21001');

        $this->assertStringNotContainsString('Buat Detail', $this->baris('21000')['action']);
        $this->assertStringContainsString('Buat Detail', $this->baris('21001')['action']);
        $this->assertStringContainsString("project/$belum/owner-detail", $this->baris('21001')['action']);
    }

    public function test_detail_company_lain_tidak_dihitung_sebagai_detail_pemilik(): void
    {
        $this->login();
        $id = $this->project('21001');
        $this->detail($id, 2, '21001'); // MEB, bukan pemilik

        $this->assertStringContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_detail_pemilik_yang_terhapus_masih_dianggap_belum_ada(): void
    {
        $this->login();
        $id = $this->project('21001');
        $this->detail($id, 1, '21001', terhapus: true);

        $this->assertStringContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_baris_project_terhapus_tidak_punya_tombol_buat_detail(): void
    {
        $this->login();
        $this->project('21001', terhapus: true);

        $this->assertStringNotContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_tombol_tidak_muncul_tanpa_izin_tambah_project_detail(): void
    {
        $this->login(admin: false, izin: ['read_project']);
        $this->project('21001');

        $this->assertStringNotContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_tombol_tidak_muncul_di_aplikasi_klien_bukan_hub(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => false]);
        $this->login();
        $this->project('21001');

        $this->assertStringNotContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_post_membuat_detail_company_pemilik_dari_kode_dan_nama_project(): void
    {
        $this->login();
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id))->assertRedirect(route('master.project.index'))
            ->assertSessionHas('success_message', 'Project 21001: Project Detail company pemilik berhasil dibuat.');

        $this->assertDatabaseHas('master_project_detail', [
            'project_id' => $id, 'company_id' => 1, 'project_code_client' => '21001', 'project_name_client' => 'Project 21001', 'deleted_at' => null,
        ]);
        $this->assertStringNotContainsString('Buat Detail', $this->baris('21001')['action']);
    }

    public function test_post_dua_kali_tidak_menggandakan_detail(): void
    {
        $this->login();
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id));
        $this->post(route('master.project.owner-detail', $id))
            ->assertSessionHas('success_message', 'Project 21001 sudah punya Project Detail company pemilik, tidak ada yang dibuat.');

        $this->assertSame(1, DB::table('master_project_detail')->where('project_id', $id)->count());
    }

    public function test_post_memulihkan_detail_pemilik_yang_terhapus_bukan_membuat_baru(): void
    {
        $this->login();
        $id = $this->project('21001');
        $detail = $this->detail($id, 1, '21001', terhapus: true);

        $this->post(route('master.project.owner-detail', $id))
            ->assertSessionHas('success_message', 'Project 21001: Project Detail company pemilik yang terhapus dipulihkan.');

        $this->assertDatabaseHas('master_project_detail', ['id' => $detail, 'deleted_at' => null]);
        $this->assertSame(1, DB::table('master_project_detail')->where('project_id', $id)->count());
    }

    public function test_post_ditolak_untuk_project_terhapus(): void
    {
        $this->login();
        $id = $this->project('21001', terhapus: true);

        $this->post(route('master.project.owner-detail', $id))
            ->assertSessionHas('error_message');

        $this->assertSame(0, DB::table('master_project_detail')->count());
    }

    public function test_post_ditolak_bila_kode_client_dipakai_project_lain_di_company_pemilik(): void
    {
        $this->login();
        $lain = $this->project('21009');
        $this->detail($lain, 1, '21001'); // kode client 21001 sudah dipakai project lain
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id))->assertSessionHas('error_message');

        $this->assertDatabaseMissing('master_project_detail', ['project_id' => $id]);
    }

    public function test_post_tanpa_izin_ditolak_403(): void
    {
        $this->login(admin: false, izin: ['read_project']);
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id))->assertForbidden();

        $this->assertSame(0, DB::table('master_project_detail')->count());
    }

    public function test_post_di_aplikasi_klien_404(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => false]);
        $this->login();
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id))->assertNotFound();
    }

    public function test_company_pemilik_bisa_diatur_lewat_config(): void
    {
        config(['MasterCrudConfig.PROJECT_OWNER_COMPANY_ID' => 2]);
        $this->login();
        $id = $this->project('21001');

        $this->post(route('master.project.owner-detail', $id));

        $this->assertDatabaseHas('master_project_detail', ['project_id' => $id, 'company_id' => 2]);
    }

    // ---- card ringkasan ----

    public function test_ringkasan_menghitung_per_company_aktif_dan_menandai_kekurangan(): void
    {
        $a = $this->project('21000');
        $b = $this->project('21001');
        $this->project('21002');               // belum punya detail pemilik
        $c = $this->project('21003', terhapus: true);
        $this->detail($a, 1, '21000');
        $this->detail($b, 1, '21001');
        $this->detail($b, 2, 'CLIENT1');       // MEB
        $this->detail($a, 3, 'CLIENT2');       // company terhapus -> yatim
        $this->detail($c, 1, '21003');         // project terhapus -> yatim

        $s = app(ProjectOwnerDetail::class)->summary();

        $this->assertSame(2, $s['company_total']);
        $this->assertSame(1, $s['company_deleted']);
        $this->assertSame(3, $s['project_total']);
        $this->assertSame(1, $s['project_deleted']);
        $this->assertSame(['ME' => 2, 'MEB' => 1], collect($s['companies'])->pluck('detail', 'code')->all());
        $this->assertTrue($s['companies'][0]['owner']);
        $this->assertSame(1, $s['missing']);
        $this->assertSame(['21002'], $s['missing_sample']);
        $this->assertSame(1, $s['orphan_company']);
        $this->assertSame(1, $s['orphan_project']);
        $this->assertFalse($s['consistent']);
    }

    public function test_ringkasan_sesuai_bila_setiap_project_punya_tepat_satu_detail_pemilik(): void
    {
        $a = $this->project('21000');
        $b = $this->project('21001');
        $this->detail($a, 1, '21000');
        $this->detail($b, 1, '21001');

        $s = app(ProjectOwnerDetail::class)->summary();

        $this->assertTrue($s['consistent']);
        $this->assertSame(0, $s['missing']);
        $this->assertSame(0, $s['duplicate']);
    }

    public function test_ringkasan_tidak_sesuai_bila_ada_detail_pemilik_ganda(): void
    {
        $a = $this->project('21000');
        $this->detail($a, 1, '21000');
        $this->detail($a, 1, '21000');

        $s = app(ProjectOwnerDetail::class)->summary();

        $this->assertSame(1, $s['duplicate']);
        $this->assertFalse($s['consistent']);
    }

    public function test_card_ringkasan_dirender_dengan_angka_dan_status(): void
    {
        $a = $this->project('21000');
        $this->project('21001');
        $this->detail($a, 1, '21000');
        $summary = app(ProjectOwnerDetail::class)->summary();

        $html = view('master::partials.project-owner-summary', ['data' => ['page' => ['owner_summary' => $summary]]])->render();

        $this->assertStringContainsString('Company aktif', $html);
        $this->assertStringContainsString('Belum sesuai', $html);
        $this->assertStringContainsString('1 project aktif belum punya Project Detail ME - PT Meindo Elang Indah', $html);
        $this->assertStringContainsString('21001', $html);
        $this->assertStringContainsString('pemilik utama', $html);
    }

    public function test_card_ringkasan_mengescape_nama_company(): void
    {
        DB::table('master_company')->where('id', 2)->update(['company_name' => '<script>alert(1)</script>']);
        $summary = app(ProjectOwnerDetail::class)->summary();

        $html = view('master::partials.project-owner-summary', ['data' => ['page' => ['owner_summary' => $summary]]])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
