<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\ProjectDetailNumberReset;
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
 * Reset nomor tahunan per baris Project Detail (project × company), v3.16.0, permintaan user 2026-11-20:
 * master_project_detail.number_reset_yearly (bawaan 0), centang di form Project Detail + kolom daftar + kolom import
 * Yearly Reset. Hanya admin Master Data yang bisa mengubah; baris pemilik project tetap bisa diubah (kuncinya hanya
 * kode/nama), dan cermin project tidak menyentuh kolom ini. Dipakai SPB untuk nomor SPB (Meindo 21000/23000 reset
 * tahunan, MEB 73029 tidak, di project yang sama).
 */
class ProjectDetailNumberResetTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

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
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => [
            'read_project_detail', 'create_project_detail', 'update_project_detail', 'read_project',
        ]]);
        $this->actingAs(User::create(['name' => 'Staf Master', 'email' => 'staf'.uniqid().'@meindo.test']));
    }

    private function project(string $kode): int
    {
        return DB::table('master_project')->insertGetId(['project_code' => $kode, 'project_name' => 'Project '.$kode, 'internal_external' => 'I']);
    }

    private function detail(int $projectId, int $company, string $kode, bool $reset = false): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode,
            'project_name_client' => 'Klien '.$kode, 'number_reset_yearly' => $reset ? 1 : 0,
        ]);
    }

    private function reset(int $detailId): int
    {
        return (int) DB::table('master_project_detail')->where('id', $detailId)->value('number_reset_yearly');
    }

    /** @param  array<string, mixed>  $tambahan */
    private function simpan(int $detailId, array $tambahan = [])
    {
        $row = DB::table('master_project_detail')->where('id', $detailId)->first();

        return $this->post(route('master.project-detail.store'), $tambahan + [
            'id' => $detailId, 'project_id' => $row->project_id, 'company_id' => $row->company_id,
            'project_code_client' => $row->project_code_client, 'project_name_client' => $row->project_name_client,
        ]);
    }

    private function upload(string $csv): string
    {
        $response = $this->post(route('master.import.preview', 'project-detail'), ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)]);
        $response->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    /** @return list<array<string, mixed>> baris preview import */
    private function preview(string $token): array
    {
        return $this->get(route('master.import.show', ['project-detail', $token]))->viewData('analysis')['rows'];
    }

    // ---- skema ----

    public function test_upgrader_menambah_kolom_bawaan_0_di_tabel_lama_dan_aman_dipanggil_ulang(): void
    {
        Schema::drop('master_project_detail');
        Schema::create('master_project_detail', function (Blueprint $table) {
            $table->id();
            $table->string('project_code_client', 10)->nullable();
            $table->string('project_name_client', 100)->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('project_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        $lama = DB::table('master_project_detail')->insertGetId(['project_id' => 1, 'company_id' => self::ME, 'project_code_client' => '21000']);
        MasterSchemaUpgrader::flush();
        SchemaColumns::flush();

        $this->assertSame(['tambah kolom number_reset_yearly'], app(MasterSchemaUpgrader::class)->upgradeProjectDetailNumberReset());
        $this->assertSame([], app(MasterSchemaUpgrader::class)->upgradeProjectDetailNumberReset(), 'kolom sudah ada = tidak ada perubahan');
        $this->assertSame(0, $this->reset($lama), 'baris lama = tidak reset');
        $this->assertTrue(app(ProjectDetailNumberReset::class)->available());
    }

    // ---- form ----

    public function test_admin_mencentang_reset_tahunan_termasuk_di_baris_pemilik_yang_kode_namanya_terkunci(): void
    {
        $this->login();
        $project = $this->project('21000');
        $pemilik = $this->detail($project, self::ME, '21000');
        $meb = $this->detail($project, self::MEB, '71000');

        $this->get(route('master.project-detail.edit', $pemilik))->assertOk()
            ->assertSee('data-owner-row-note', false)
            ->assertSee('name="number_reset_yearly"', false);

        $this->simpan($pemilik, ['number_reset_yearly' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->reset($pemilik), 'baris pemilik: flag boleh diubah');
        $this->assertSame(0, $this->reset($meb), 'baris company lain di project yang sama tidak ikut');

        $this->simpan($pemilik, ['number_reset_yearly' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->reset($pemilik));

        $this->simpan($pemilik, ['number_reset_yearly' => '1', 'project_name_client' => 'Nama Lain'])->assertSessionHasErrors('project_code_client');
        $this->assertSame(0, $this->reset($pemilik), 'nama baris pemilik tetap terkunci: simpan ditolak seluruhnya');
    }

    public function test_admin_membuat_baris_baru_bertanda_reset_tahunan(): void
    {
        $this->login();
        $project = $this->project('21000');
        $this->detail($project, self::ME, '21000');

        $this->post(route('master.project-detail.store'), [
            'project_id' => $project, 'company_id' => self::MEB, 'project_code_client' => '71001',
            'project_name_client' => 'MEB umum', 'number_reset_yearly' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) DB::table('master_project_detail')->where('company_id', self::MEB)->value('number_reset_yearly'));
    }

    public function test_bukan_admin_melihat_nilai_tetapi_tidak_bisa_mengubah(): void
    {
        $project = $this->project('21000');
        $pemilik = $this->detail($project, self::ME, '21000', true);
        $meb = $this->detail($project, self::MEB, '73029');
        $this->loginBukanAdmin();

        $this->get(route('master.project-detail.edit', $meb))->assertOk()
            ->assertDontSee('name="number_reset_yearly"', false)
            ->assertSee('Hanya admin Master Data yang bisa mengubah.');

        $this->simpan($meb, ['number_reset_yearly' => '1', 'project_name_client' => 'Nama baru'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->reset($meb), 'request langsung dari bukan admin diabaikan');
        $this->assertSame('Nama baru', DB::table('master_project_detail')->where('id', $meb)->value('project_name_client'), 'field lain tetap tersimpan');

        $this->simpan($pemilik, ['number_reset_yearly' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->reset($pemilik));
    }

    public function test_cermin_project_tidak_mengubah_flag_baris_pemilik(): void
    {
        $this->login();
        $project = $this->project('21000');
        $pemilik = $this->detail($project, self::ME, '21000', true);

        $this->post(route('master.project.store'), ['id' => $project, 'project_code' => '21000', 'project_name' => 'Nama Baru'])->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', DB::table('master_project_detail')->where('id', $pemilik)->value('project_name_client'), 'cermin jalan');
        $this->assertSame(1, $this->reset($pemilik), 'flag tetap');
    }

    // ---- daftar ----

    public function test_daftar_menampilkan_kolom_yearly_reset_dan_bisa_dicari(): void
    {
        $this->login();
        $project = $this->project('21000');
        $this->detail($project, self::ME, '21000', true);
        $this->detail($project, self::MEB, '73029');

        $response = $this->getJson('/master/project-detail', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertContains('Yearly Reset', array_column($response->json('columns'), 'name'));
        $this->assertSame(['21000' => 'Ya', '73029' => 'Tidak'], collect($response->json('data'))
            ->mapWithKeys(fn ($row) => [$row['project_code_client'] => $row['yearly_reset']])->sortKeys()->all());

        $columns = collect($response->json('columns'))->map(fn ($c, $i) => [
            'data' => $c['data'], 'searchable' => 'true', 'search' => ['value' => $c['data'] === 'yearly_reset' ? 'Ya' : ''],
        ])->all();
        $found = $this->getJson('/master/project-detail?'.http_build_query(['columns' => $columns]), ['X-Requested-With' => 'XMLHttpRequest'])->json('data');
        $this->assertSame(['21000'], array_column($found, 'project_code_client'));
    }

    // ---- import ----

    public function test_import_admin_mengisi_yearly_reset_kosong_tidak_mengubah_dan_isi_salah_ditolak(): void
    {
        $this->login();
        $a = $this->project('21000');
        $b = $this->project('23000');
        $c = $this->project('21317');
        $baris21000 = $this->detail($a, self::ME, '21000');
        $baris23000 = $this->detail($b, self::ME, '23000', true);

        $token = $this->upload("Project Code,Project Code Client,Project Name Client,Company Code,Yearly Reset\n"
            ."21000,21000,Klien 21000,ME,Y\n"
            ."23000,23000,Klien 23000,ME,\n"
            ."21317,73029,Sisi Nubia,MEB,Ya\n"
            ."21317,73030,Sisi Nubia B,MEB,X\n");

        $rows = $this->preview($token);
        $this->assertSame(['update', 'same', 'new', 'error'], array_column($rows, 'status'));
        $this->assertSame(['old' => 'Tidak', 'new' => 'Ya'], array_intersect_key($rows[0]['changes']['number_reset_yearly'], ['old' => 1, 'new' => 1]));
        $this->assertStringContainsString("Yearly Reset 'X' tidak dikenali", $rows[3]['message']);

        $this->post(route('master.import.commit', ['project-detail', $token]), ['allow_update' => '1', 'update_reason' => 'isi penanda reset tahunan dari daftar project'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->reset($baris21000));
        $this->assertSame(1, $this->reset($baris23000), 'kosong = tidak diubah');
        $this->assertSame(1, (int) DB::table('master_project_detail')->where('project_id', $c)->where('project_code_client', '73029')->value('number_reset_yearly'));
    }

    public function test_import_bukan_admin_mengabaikan_yearly_reset_dengan_catatan(): void
    {
        $project = $this->project('21000');
        $baris = $this->detail($project, self::ME, '21000');
        $this->loginBukanAdmin();

        $token = $this->upload("Project Code,Project Code Client,Project Name Client,Company Code,Yearly Reset\n"
            ."21000,21000,Klien 21000,ME,Y\n");

        $rows = $this->preview($token);
        $this->assertSame('same', $rows[0]['status']);
        $this->assertSame(['Yearly Reset diabaikan: hanya admin Master Data yang bisa mengubah.'], $rows[0]['notes']);
        $this->assertSame(0, $this->reset($baris));
    }
}
