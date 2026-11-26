<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\ProjectCodePrefix;
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
 * Awalan kode project per company (v3.22.0, permintaan user 2026-11-26): MasterCrudConfig.PROJECT_CODE_PREFIX
 * `ME:1,2,3,8,9;MEB:7` = kode company ME harus berawalan 1/2/3/8/9 (73034 untuk ME ditolak), kode company MEB
 * berawalan 7, company lain bebas. Berlaku untuk Project Code (company pemilik project: Create, Edit kode, Import
 * Project baru, Pindah pemilik) dan Project Code Client (company baris: tambah, ubah kode, Import baris baru, Pindah
 * company). Kode yang tidak berubah tidak dicek (data lama), config kosong = tanpa aturan.
 *
 * v3.23.0 (permintaan user 2026-11-26): MasterCrudConfig.PROJECT_CODE_MAX_LENGTH = panjang maksimal kode yang diketik,
 * semua company (Create, ubah kode, Import baru). Pindah pemilik / Pindah company tidak mengecek panjang (kode yang sudah
 * ada). Bawaan kosong = tanpa batas tambahan.
 */
class ProjectCodePrefixTest extends TestCase
{
    use CreatesMasterTables;

    private const ME = 1;

    private const MEB = 647;

    private const ENL = 648;

    private const PHM = 116; // company klien, sudah dihapus

    private const PESAN_ME_73034 = 'Project Code 73034 tidak sesuai untuk company ME: kode company ME harus berawalan 1, 2, 3, 8, atau 9. Awalan 7 milik company MEB.';

    private const PESAN_MEB_21999 = 'Project Code 21999 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.';

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
        $app['config']->set('MasterCrudConfig.PROJECT_CODE_PREFIX', 'ME:1,2,3,8,9;MEB:7');
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

    private function detail(int $projectId, int $company, string $kode): int
    {
        return DB::table('master_project_detail')->insertGetId([
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Klien '.$kode,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function buatProject(string $kode, array $extra = [])
    {
        return $this->post(route('master.project.store'), ['project_code' => $kode, 'project_name' => 'Proyek '.$kode, 'internal_external' => 'E'] + $extra);
    }

    private function tambahDetail(int $projectId, int $company, string $kode)
    {
        return $this->post(route('master.project-detail.store'), [
            'project_id' => $projectId, 'company_id' => $company, 'project_code_client' => $kode, 'project_name_client' => 'Klien '.$kode,
        ]);
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

    // ---- aturan (config) ----

    public function test_config_string_dan_array_dibaca_sama_tanpa_beda_huruf_besar_kecil(): void
    {
        $service = app(ProjectCodePrefix::class);
        $this->assertSame(['ME' => ['1', '2', '3', '8', '9'], 'MEB' => ['7']], $service->rules());

        config(['MasterCrudConfig.PROJECT_CODE_PREFIX' => ['me' => ['1', ' 2 '], 'Meb' => '7, 71', 'ENL' => '']]);
        $this->assertSame(['ME' => ['1', '2'], 'MEB' => ['7', '71']], $service->rules(), 'company tanpa awalan diabaikan');

        config(['MasterCrudConfig.PROJECT_CODE_PREFIX' => ' me : 1 ; ; salah ; meb:7 ']);
        $this->assertSame(['ME' => ['1'], 'MEB' => ['7']], $service->rules());
    }

    public function test_pesan_per_company(): void
    {
        $service = app(ProjectCodePrefix::class);

        $this->assertSame(self::PESAN_ME_73034, $service->message(self::ME, '73034'));
        $this->assertSame(self::PESAN_MEB_21999, $service->message(self::MEB, ' 21999 '));
        $this->assertNull($service->message(self::ME, '21999'));
        $this->assertNull($service->message(self::ME, '89000'));
        $this->assertNull($service->message(self::MEB, '73034'));
        // company lain dan company tidak dikenal bebas, juga memakai awalan ME/MEB
        $this->assertNull($service->message(self::ENL, '73034'));
        $this->assertNull($service->message(self::ENL, 'EH1101'));
        $this->assertNull($service->message(9999, '73034'));
        // awalan bukan milik company mana pun: tanpa keterangan pemilik awalan
        $this->assertSame('Project Code Client 45000 tidak sesuai untuk company ME: kode company ME harus berawalan 1, 2, 3, 8, atau 9.',
            $service->message(self::ME, '45000', 'Project Code Client'));
        $this->assertSame('Awalan kode: ME 1, 2, 3, 8, 9 · MEB 7 · company lain bebas', $service->hint());
    }

    public function test_config_kosong_tanpa_aturan(): void
    {
        config(['MasterCrudConfig.PROJECT_CODE_PREFIX' => '']);

        $this->assertSame([], app(ProjectCodePrefix::class)->rules());
        $this->assertNull(app(ProjectCodePrefix::class)->hint());
        $this->buatProject('73034')->assertSessionHasNoErrors();
        $this->assertTrue(DB::table('master_project')->where('project_code', '73034')->where('owner_company_id', self::ME)->exists());
    }

    // ---- Project: Create ----

    public function test_create_project_meindo_dengan_awalan_7_ditolak(): void
    {
        $this->buatProject('73034')->assertSessionHasErrors(['project_code' => self::PESAN_ME_73034]);
        $this->assertFalse(DB::table('master_project')->where('project_code', '73034')->exists());

        foreach (['10001', '21999', '30001', '80001', '90001'] as $kode) {
            $this->buatProject($kode)->assertSessionHasNoErrors();
        }
        $id = (int) DB::table('master_project')->where('project_code', '21999')->value('id');
        $this->assertSame([self::ME], DB::table('master_project_detail')->where('project_id', $id)->pluck('company_id')->map(fn ($c) => (int) $c)->all());
    }

    public function test_create_project_milik_meb_harus_berawalan_7_dan_company_lain_bebas(): void
    {
        $meb = ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB];
        $this->buatProject('21999', $meb)->assertSessionHasErrors(['project_code' => self::PESAN_MEB_21999]);
        $this->buatProject('73034', $meb)->assertSessionHasNoErrors();
        $this->assertSame(self::MEB, (int) DB::table('master_project')->where('project_code', '73034')->value('owner_company_id'));

        $enl = ['project_owner_other' => '1', 'project_owner_company_id' => self::ENL];
        $this->buatProject('EH1101', $enl)->assertSessionHasNoErrors();
        $this->buatProject('73035', $enl)->assertSessionHasNoErrors();
        $this->buatProject('21998', $enl)->assertSessionHasNoErrors();
    }

    public function test_create_project_meindo_lewat_delegasi_meb_ikut_dicek_untuk_baris_meb(): void
    {
        config(['test.denied_permissions' => ['is_admin'], 'test.allowed_permissions' => ['create_project', 'update_project']]);
        $this->actingAs(User::create(['name' => 'Admin MEB', 'email' => 'meb'.uniqid().'@meindo.test']));
        $this->withSession([MasterScope::SESSION_KEY => [
            'app_code' => 'APP32', 'app_name' => 'SPB V2', 'company_id' => self::MEB, 'company_code' => 'MEB',
            'all_co' => false, 'mods' => ['master_project' => ['create', 'update']], 'back' => null, 'exp' => time() + 600,
        ]]);

        // pemilik bawaan ME + baris company pembuat MEB berkode sama: tidak bisa memenuhi dua awalan sekaligus
        $this->buatProject('21999', ['project_owner_other' => '0'])->assertSessionHasErrors([
            'project_code' => self::PESAN_MEB_21999.' Project baru yang dibuat lewat session company MEB juga mendapat baris Project Detail company itu dengan kode yang sama.',
        ]);
        $this->buatProject('73034', ['project_owner_other' => '0'])->assertSessionHasErrors(['project_code' => self::PESAN_ME_73034]);

        // project milik MEB (centang bawaan delegasi) cukup awalan MEB
        $this->buatProject('73036', ['project_owner_other' => '1', 'project_owner_company_id' => self::MEB])->assertSessionHasNoErrors();
    }

    public function test_form_create_menampilkan_awalan_kode(): void
    {
        $this->get(route('master.project.create'))->assertOk()
            ->assertSee('Awalan kode: ME 1, 2, 3, 8, 9 · MEB 7 · company lain bebas', false);
        $this->get(route('master.project-detail.create'))->assertOk()
            ->assertSee('Awalan kode: ME 1, 2, 3, 8, 9 · MEB 7 · company lain bebas', false);
    }

    // ---- Project: Edit kode, Import, Pindah pemilik ----

    public function test_edit_kode_project_dicek_menurut_pemilik_dan_kode_tetap_tidak_dicek(): void
    {
        $this->post(route('master.project.store'), ['id' => $this->projectMe, 'project_code' => '73034', 'project_name' => 'Project 21329', 'confirm_key_change' => '1'])
            ->assertSessionHasErrors(['project_code' => self::PESAN_ME_73034]);
        $this->assertSame('21329', DB::table('master_project')->where('id', $this->projectMe)->value('project_code'));

        $this->post(route('master.project.store'), ['id' => $this->projectMeb, 'project_code' => '21999', 'project_name' => 'Project 73029', 'confirm_key_change' => '1'])
            ->assertSessionHasErrors(['project_code' => self::PESAN_MEB_21999]);

        // data lama di luar aturan (project ME berkode 7xxxx sebelum aturan ada) tetap bisa diubah namanya
        $lama = $this->project('73022');
        $this->post(route('master.project.store'), ['id' => $lama, 'project_code' => '73022', 'project_name' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', DB::table('master_project')->where('id', $lama)->value('project_name'));
    }

    public function test_import_project_baru_dicek_menurut_owner_company_code(): void
    {
        $token = $this->upload('project', "Project Code,Project Name,Project Type,Owner Company Code\n"
            ."73034,Proyek A,E,\n"         // pemilik bawaan ME
            ."21999,Proyek B,E,MEB\n"      // milik MEB
            ."21998,Proyek C,E,\n"
            ."73035,Proyek D,E,MEB\n"
            ."73036,Proyek E,E,ENL\n"      // company lain bebas
            ."21329,Project 21329,E,\n");  // project lama: kode tidak berubah, tidak dicek

        $rows = $this->preview('project', $token);
        $this->assertSame(['error', 'error', 'new', 'new', 'new'], array_slice(array_column($rows, 'status'), 0, 5));
        $this->assertNotSame('error', $rows[5]['status']);
        $this->assertSame(self::PESAN_ME_73034, $rows[0]['message']);
        $this->assertSame(self::PESAN_MEB_21999, $rows[1]['message']);
    }

    public function test_pindah_pemilik_dicek_menurut_kode_yang_akan_dipakai_project(): void
    {
        // project Meindo tanpa baris MEB: kode project tetap 21500 → tidak sesuai MEB
        $proyek = $this->project('21500');
        $this->detail($proyek, self::ME, '21500');
        $this->post(route('master.project.transfer-owner', $proyek), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])
            ->assertSessionHasErrors(['to_company_id' => 'Project Code 21500 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.'
                .' Ubah dulu Project Code project ini, atau tambahkan baris Project Detail company itu dengan kode yang sesuai, lalu pindahkan pemiliknya.']);
        $this->assertNull(DB::table('master_project')->where('id', $proyek)->value('owner_company_id'));
        $this->assertSame(1, DB::table('master_project_detail')->where('project_id', $proyek)->count(), 'baris MEB yang sempat dibuat ikut batal');

        // project Meindo dengan baris MEB 73028: kode project jadi 73028 → sesuai MEB
        $this->detail($proyek, self::MEB, '73028');
        $this->post(route('master.project.transfer-owner', $proyek), ['to_company_id' => self::MEB, 'confirm_transfer' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('73028', DB::table('master_project')->where('id', $proyek)->value('project_code'));

        // project MEB → ME: kode 73029 tidak sesuai ME
        $this->post(route('master.project.transfer-owner', $this->projectMeb), ['to_company_id' => self::ME, 'confirm_transfer' => '1'])
            ->assertSessionHasErrors('to_company_id');
        $this->assertSame(self::MEB, (int) DB::table('master_project')->where('id', $this->projectMeb)->value('owner_company_id'));

        // company lain bebas
        $this->post(route('master.project.transfer-owner', $this->projectMeb), ['to_company_id' => self::ENL, 'confirm_transfer' => '1'])->assertSessionHasNoErrors();
    }

    // ---- Project Detail ----

    public function test_tambah_baris_project_detail_dicek_menurut_company_baris(): void
    {
        $this->tambahDetail($this->projectMe, self::MEB, '21329')
            ->assertSessionHasErrors(['project_code_client' => 'Project Code Client 21329 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.']);
        $this->tambahDetail($this->projectMeb, self::ME, '73040')
            ->assertSessionHasErrors(['project_code_client' => 'Project Code Client 73040 tidak sesuai untuk company ME: kode company ME harus berawalan 1, 2, 3, 8, atau 9. Awalan 7 milik company MEB.']);

        $this->tambahDetail($this->projectMe, self::MEB, '73028')->assertSessionHasNoErrors();
        $this->tambahDetail($this->projectMe, self::ENL, '21329')->assertSessionHasNoErrors();
        $this->assertSame([self::ME, self::MEB, self::ENL], DB::table('master_project_detail')->where('project_id', $this->projectMe)
            ->orderBy('id')->pluck('company_id')->map(fn ($c) => (int) $c)->all());
    }

    public function test_ubah_kode_baris_project_detail_dicek_dan_kode_tetap_tidak_dicek(): void
    {
        $baris = $this->detail($this->projectMe, self::MEB, '73028');
        $simpan = fn (array $ubah) => $this->post(route('master.project-detail.store'), $ubah + [
            'id' => $baris, 'project_id' => $this->projectMe, 'company_id' => self::MEB,
            'project_code_client' => '73028', 'project_name_client' => 'Klien 73028',
        ]);

        $simpan(['project_code_client' => '21400', 'confirm_key_change' => '1'])
            ->assertSessionHasErrors(['project_code_client' => 'Project Code Client 21400 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.']);
        $this->assertSame('73028', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));

        // data lama: baris MEB berkode 2xxxx (sebelum aturan ada) tetap bisa diubah namanya
        $proyek = $this->project('21401');
        $this->detail($proyek, self::ME, '21401');
        $lama = $this->detail($proyek, self::MEB, '21401');
        $this->post(route('master.project-detail.store'), [
            'id' => $lama, 'project_id' => $proyek, 'company_id' => self::MEB, 'project_code_client' => '21401', 'project_name_client' => 'Nama Baru',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', DB::table('master_project_detail')->where('id', $lama)->value('project_name_client'));
    }

    public function test_import_project_detail_baris_baru_dicek_menurut_company(): void
    {
        $token = $this->upload('project-detail', "Project Code,Project Code Client,Project Name Client,Company Code\n"
            ."21329,21329,MEB di project Meindo,MEB\n"
            ."21329,73028,MEB di project Meindo,MEB\n"
            ."73029,73029,ME di project MEB,ME\n"
            ."73029,73029,ENL di project MEB,ENL\n");

        $rows = $this->preview('project-detail', $token);
        $this->assertSame(['error', 'new', 'error', 'new'], array_column($rows, 'status'));
        $this->assertSame('Project Code Client 21329 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.', $rows[0]['message']);
    }

    public function test_pindah_company_baris_bermasalah_dicek_menurut_company_tujuan(): void
    {
        $phm = $this->detail($this->projectMe, self::PHM, '73031');

        $this->post(route('master.project-detail.move-company', $phm), ['move_company_id' => self::ENL, 'confirm_move_company' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::ENL, (int) DB::table('master_project_detail')->where('id', $phm)->value('company_id'));

        $phm2 = $this->detail($this->projectMeb, self::PHM, '21800');
        $this->post(route('master.project-detail.move-company', $phm2), ['move_company_id' => self::ME, 'confirm_move_company' => '1'])
            ->assertSessionHasNoErrors();

        $phm3 = $this->detail($this->projectMe, self::PHM, '21801');
        $this->post(route('master.project-detail.move-company', $phm3), ['move_company_id' => self::MEB, 'confirm_move_company' => '1'])
            ->assertSessionHasErrors(['move_company_id' => 'Project Code Client 21801 tidak sesuai untuk company MEB: kode company MEB harus berawalan 7. Awalan 2 milik company ME.'
                .' Pindah company tidak mengubah kode baris: pilih company lain, atau hapus baris ini lalu tambah baris baru dengan kode yang sesuai.']);
        $this->assertSame(self::PHM, (int) DB::table('master_project_detail')->where('id', $phm3)->value('company_id'));
    }

    // ---- panjang maksimal (v3.23.0) ----

    public function test_panjang_maksimal_kode_project_semua_company(): void
    {
        config(['MasterCrudConfig.PROJECT_CODE_MAX_LENGTH' => 5]);

        $this->assertSame(5, app(ProjectCodePrefix::class)->maxLength());
        $this->buatProject('213290')->assertSessionHasErrors(['project_code' => 'Project Code 213290 melebihi 5 karakter (6 karakter).']);
        // company lain (bebas awalan) tetap kena batas panjang
        $this->buatProject('EH1101', ['project_owner_other' => '1', 'project_owner_company_id' => self::ENL])
            ->assertSessionHasErrors(['project_code' => 'Project Code EH1101 melebihi 5 karakter (6 karakter).']);
        $this->assertFalse(DB::table('master_project')->whereIn('project_code', ['213290', 'EH1101'])->exists());

        $this->buatProject('21330')->assertSessionHasNoErrors();
        $this->buatProject('ENLGN', ['project_owner_other' => '1', 'project_owner_company_id' => self::ENL])->assertSessionHasNoErrors();

        // ubah kode jadi 6 karakter ditolak; kode lama 6 karakter (sebelum aturan) tetap bisa diubah namanya
        $this->post(route('master.project.store'), ['id' => $this->projectMe, 'project_code' => '213291', 'project_name' => 'Project 21329', 'confirm_key_change' => '1'])
            ->assertSessionHasErrors(['project_code' => 'Project Code 213291 melebihi 5 karakter (6 karakter).']);
        $lama = $this->project('21302M');
        $this->post(route('master.project.store'), ['id' => $lama, 'project_code' => '21302M', 'project_name' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', DB::table('master_project')->where('id', $lama)->value('project_name'));
    }

    public function test_panjang_maksimal_project_code_client(): void
    {
        config(['MasterCrudConfig.PROJECT_CODE_MAX_LENGTH' => 5]);

        $this->tambahDetail($this->projectMe, self::MEB, '730281')
            ->assertSessionHasErrors(['project_code_client' => 'Project Code Client 730281 melebihi 5 karakter (6 karakter).']);
        $this->tambahDetail($this->projectMe, self::ENL, 'ENL001')
            ->assertSessionHasErrors(['project_code_client' => 'Project Code Client ENL001 melebihi 5 karakter (6 karakter).']);
        $this->tambahDetail($this->projectMe, self::MEB, '73028')->assertSessionHasNoErrors();

        // ubah kode baris jadi 6 karakter ditolak
        $baris = (int) DB::table('master_project_detail')->where('project_id', $this->projectMe)->where('company_id', self::MEB)->value('id');
        $this->post(route('master.project-detail.store'), [
            'id' => $baris, 'project_id' => $this->projectMe, 'company_id' => self::MEB, 'project_code_client' => '730289',
            'project_name_client' => 'Klien 73028', 'confirm_key_change' => '1',
        ])->assertSessionHasErrors(['project_code_client' => 'Project Code Client 730289 melebihi 5 karakter (6 karakter).']);
        $this->assertSame('73028', DB::table('master_project_detail')->where('id', $baris)->value('project_code_client'));
    }

    public function test_panjang_maksimal_di_import_dan_tidak_dicek_saat_pindah_company(): void
    {
        config(['MasterCrudConfig.PROJECT_CODE_MAX_LENGTH' => 5]);

        $token = $this->upload('project', "Project Code,Project Name,Project Type\n213290,Proyek Panjang,E\n21331,Proyek Pas,E\n");
        $rows = $this->preview('project', $token);
        $this->assertSame(['error', 'new'], array_column($rows, 'status'));
        $this->assertSame('Project Code 213290 melebihi 5 karakter (6 karakter).', $rows[0]['message']);

        $token = $this->upload('project-detail', "Project Code,Project Code Client,Project Name Client,Company Code\n"
            ."21329,730281,MEB panjang,MEB\n21329,73028,MEB pas,MEB\n");
        $rows = $this->preview('project-detail', $token);
        $this->assertSame(['error', 'new'], array_column($rows, 'status'));
        $this->assertSame('Project Code Client 730281 melebihi 5 karakter (6 karakter).', $rows[0]['message']);

        // baris lama 6 karakter yang company-nya terhapus: kode tidak diketik, jadi hanya awalan yang dicek
        $phm = $this->detail($this->projectMeb, self::PHM, '21801X');
        $this->post(route('master.project-detail.move-company', $phm), ['move_company_id' => self::ME, 'confirm_move_company' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::ME, (int) DB::table('master_project_detail')->where('id', $phm)->value('company_id'));
    }

    public function test_form_menampilkan_batas_panjang(): void
    {
        config(['MasterCrudConfig.PROJECT_CODE_MAX_LENGTH' => 5]);
        $hint = 'Maksimal 5 karakter · Awalan kode: ME 1, 2, 3, 8, 9 · MEB 7 · company lain bebas';

        $this->assertSame($hint, app(ProjectCodePrefix::class)->hint());
        $this->get(route('master.project.create'))->assertOk()->assertSee('maxlength="5"', false)->assertSee($hint, false);
        $this->get(route('master.project-detail.create'))->assertOk()->assertSee('maxlength="5"', false)->assertSee($hint, false);

        config(['MasterCrudConfig.PROJECT_CODE_MAX_LENGTH' => null, 'MasterCrudConfig.PROJECT_CODE_PREFIX' => '']);
        $this->assertNull(app(ProjectCodePrefix::class)->hint());
        $this->get(route('master.project.create'))->assertOk()->assertDontSee('maxlength=', false);
    }
}
