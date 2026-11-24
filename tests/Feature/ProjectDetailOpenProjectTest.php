<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Support\ProjectListFilter;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Project Code di daftar Project Detail = tautan tab baru ke daftar Project yang hanya berisi project itu (permintaan
 * user 2026-11-24, kasus "Project bermasalah": project terhapus dipulihkan dari daftar Project). Project terhapus:
 * tautan membawa show_trashed=1 sehingga saklar "Tampilkan data terhapus" menyala dan admin melihat tombol Restore.
 */
class ProjectDetailOpenProjectTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private int $aktif;

    private int $terhapus;

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
            ['id' => self::ME, 'company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah'],
            ['id' => self::MEB, 'company_code' => 'MEB', 'company_name' => 'PT Meitech Eka Bintan'],
        ]);

        $this->aktif = $this->project('21007', 'General IT');
        $this->terhapus = $this->project('21099', 'Proyek <Lama>', terhapus: true);
        $this->project('210071', 'Mirip kodenya');

        $this->detail($this->aktif, self::ME, '21007');
        $this->detail($this->terhapus, self::ME, '21099');
        $this->detail($this->terhapus, self::MEB, '73099');
        $this->detail(999, self::ME, 'YATIM'); // project tidak ada di master
    }

    private function project(string $kode, string $nama, bool $terhapus = false): int
    {
        return DB::table('master_project')->insertGetId([
            'project_code' => $kode, 'project_name' => $nama, 'internal_external' => 'I', 'deleted_at' => $terhapus ? now() : null,
        ]);
    }

    private function detail(int $projectId, int $company, string $kode): void
    {
        DB::table('master_project_detail')->insert([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Klien '.$kode,
        ]);
    }

    private function loginAdmin(): void
    {
        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function loginBukanAdmin(array $izin): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => $izin]);
        $this->actingAs(User::create(['name' => 'Staf Master', 'email' => 'staf'.uniqid().'@meindo.test']));
    }

    /** @return array<string, mixed> respon DataTables */
    private function daftar(string $slug, array $params = []): array
    {
        $url = '/master/'.$slug.($params ? '?'.http_build_query($params) : '');

        return $this->getJson($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
    }

    // ---- daftar Project Detail: tautan Project Code ----

    public function test_baris_project_detail_membawa_url_project_terhapus_dengan_saklar_menyala(): void
    {
        $this->loginAdmin();

        $tautan = collect($this->daftar('project-detail')['data'])->pluck('project_link', 'project_code_client');

        $this->assertSame(e(route('master.project.index', ['project_id' => $this->aktif])), $tautan['21007']);
        $this->assertSame(e(route('master.project.index', ['project_id' => $this->terhapus, 'show_trashed' => 1])), $tautan['21099']);
        $this->assertSame($tautan['21099'], $tautan['73099']);
        $this->assertNull($tautan['YATIM']); // project tidak ada di master: tanpa tautan
    }

    public function test_tautan_project_hanya_untuk_user_yang_boleh_membaca_project(): void
    {
        $this->loginBukanAdmin(['read_project_detail']);
        $this->assertSame([null], collect($this->daftar('project-detail')['data'])->pluck('project_link')->unique()->values()->all());
        $this->assertStringNotContainsString('"project_code":{"field":"project_link"', $this->get('/master/project-detail')->assertOk()->getContent());

        $this->loginBukanAdmin(['read_project_detail', 'read_project']);
        $this->assertNotNull(collect($this->daftar('project-detail')['data'])->firstWhere('project_code_client', '21007')['project_link']);
        $this->assertStringContainsString('"project_code":{"field":"project_link","target":"_blank"', $this->get('/master/project-detail')->assertOk()->getContent());
    }

    // ---- daftar Project: ?project_id= ----

    public function test_daftar_project_hanya_project_terpilih_cocok_persis(): void
    {
        $this->loginBukanAdmin(['read_project']);

        $json = $this->daftar('project', ['project_id' => $this->aktif]);
        $this->assertSame(['21007'], array_column($json['data'], 'project_code')); // 210071 tidak ikut (bukan LIKE)
        $this->assertSame(1, $json['recordsTotal']);

        // project terhapus tidak tampil untuk bukan admin walau show_trashed=1
        $this->assertSame([], $this->daftar('project', ['project_id' => $this->terhapus, 'show_trashed' => 1])['data']);

        foreach (['abc', '0', '99999'] as $nilai) {
            $this->assertCount(2, $this->daftar('project', ['project_id' => $nilai])['data'], "project_id=$nilai");
        }
    }

    public function test_admin_melihat_project_terhapus_dengan_tombol_restore(): void
    {
        $this->loginAdmin();

        $baris = $this->daftar('project', ['project_id' => $this->terhapus, 'show_trashed' => 1])['data'];
        $this->assertCount(1, $baris);
        $this->assertSame('21099', $baris[0]['project_code']);
        $this->assertStringContainsString('Restore', $baris[0]['action']);

        $this->assertSame([], $this->daftar('project', ['project_id' => $this->terhapus, 'show_trashed' => 0])['data']);
    }

    public function test_halaman_project_dengan_project_id_menampilkan_bar_dan_menyalakan_saklar(): void
    {
        $this->loginAdmin();

        $html = $this->get('/master/project?project_id='.$this->terhapus.'&show_trashed=1')->assertOk()->getContent();
        $this->assertStringContainsString('data-fixed-filter-bar', $html);
        $this->assertStringContainsString('<strong data-fixed-filter-label>21099 - Proyek &lt;Lama&gt;</strong>', $html);
        $this->assertStringContainsString('project sudah dihapus', $html);
        $this->assertStringContainsString('lalu klik Restore di kolom Action', $html);
        $this->assertStringContainsString('$.extend(d, {"project_id":'.$this->terhapus.'});', $html);
        $this->assertStringContainsString('var fromUrl = true;', $html); // saklar menyala dari URL
        $this->assertStringContainsString('href="'.route('master.project.index').'" class="btn btn-sm btn-outline-secondary', $html);

        $html = $this->get('/master/project?project_id='.$this->aktif)->assertOk()->getContent();
        $this->assertStringContainsString('21007 - General IT', $html);
        $this->assertStringNotContainsString('data-fixed-filter-badge', $html);
        $this->assertStringContainsString('var fromUrl = null;', $html);

        $html = $this->get('/master/project?project_id=99999')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-fixed-filter-bar', $html);
    }

    public function test_bukan_admin_mendapat_petunjuk_minta_admin_untuk_project_terhapus(): void
    {
        $this->loginBukanAdmin(['read_project']);

        $html = $this->get('/master/project?project_id='.$this->terhapus.'&show_trashed=1')->assertOk()->getContent();
        $this->assertStringContainsString('minta admin memulihkannya', $html);
        $this->assertStringNotContainsString('role="switch" id="master-trash-toggle-', $html);
    }

    public function test_url_project(): void
    {
        $this->assertSame(route('master.project.index', ['project_id' => 5]), ProjectListFilter::url(5, false));
        $this->assertSame(route('master.project.index', ['project_id' => 5, 'show_trashed' => 1]), ProjectListFilter::url(5, true));
    }
}
