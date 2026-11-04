<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;

/**
 * Memindahkan pemilik data (app_code + company_id) baris lama: hanya is_admin, ke aplikasi/company yang terdaftar, dan
 * wajib dikonfirmasi (MasterScope::CONFIRM_OWNER_FIELD). Data bersama APP03 yang dipindah ke satu aplikasi hilang dari
 * pilihan aplikasi lain, jadi tidak boleh terjadi diam-diam.
 */
class MasterOwnerChangeGuardTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->createDashboardSettings();
        Artisan::call('master:scope-upgrade');
        config(['MasterCrudConfig.MASTER_HUB' => true, 'SsoConfig.main.APP_CODE' => 'APP03', 'test.allowed_permissions' => ['is_admin']]);

        Schema::create('master_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('app_code');
            $table->softDeletes();
        });
        DB::table('master_apps')->insert([
            ['name' => 'MASTER DATA', 'app_code' => 'APP03', 'deleted_at' => null],
            ['name' => 'WAREHOUSE', 'app_code' => 'APP23', 'deleted_at' => null],
            ['name' => 'SPB V2', 'app_code' => 'APP32', 'deleted_at' => null],
            ['name' => 'APLIKASI LAMA', 'app_code' => 'APP44', 'deleted_at' => now()],
        ]);
        Schema::table('master_company', fn (Blueprint $table) => $table->string('company_name')->nullable());
        DB::table('master_company')->insert([
            ['id' => 647, 'company_code' => 'MEB', 'company_name' => 'Meindo Elang Indah', 'deleted_at' => null],
            ['id' => 648, 'company_code' => 'ENL', 'company_name' => 'Elnusa', 'deleted_at' => null],
            ['id' => 649, 'company_code' => 'OLD', 'company_name' => 'Company Lama', 'deleted_at' => now()],
        ]);

        $user = User::create(['name' => 'Tim Master', 'email' => 'tim.master@meindo.test']);
        DB::table('user_details')->insert(['user_email' => $user->email, 'field_key' => 'company_id', 'field_value' => '647']);
        $this->actingAs($user);
    }

    private function category(string $appCode, int $companyId): int
    {
        return DB::table('master_category')->insertGetId([
            'category_code' => 'C01', 'category_name' => 'Kategori lama', 'app_code' => $appCode, 'company_id' => $companyId,
        ]);
    }

    /** @param array<string, mixed> $owner */
    private function save(int $id, array $owner, string $name = 'Kategori baru')
    {
        return $this->from(route('master.category.edit', $id))
            ->post(route('master.category.store'), ['id' => $id, 'category_code' => 'C01', 'category_name' => $name] + $owner);
    }

    public function test_pindah_data_bersama_ke_aplikasi_lain_tanpa_konfirmasi_ditolak(): void
    {
        $id = $this->category('APP03', 0);

        $this->save($id, ['owner_app_code' => 'APP23', 'owner_company_id' => 0])
            ->assertRedirect(route('master.category.edit', $id))
            ->assertSessionHasErrors([MasterScope::OWNER_APP_FIELD => 'Pemindahan pemilik data dari APP03 · Semua company ke APP23 · Semua company harus dikonfirmasi sebelum disimpan.']);

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP03', 'company_id' => 0, 'category_name' => 'Kategori lama']);
    }

    public function test_pindah_pemilik_dengan_konfirmasi_tersimpan_dan_tercatat_di_log(): void
    {
        config(['libraryclay.activity_log.console' => true]); // test berjalan di console: Loggable hanya mencatat bila aktif
        $id = $this->category('APP03', 0);

        $this->save($id, ['owner_app_code' => 'app23', 'owner_company_id' => 648, MasterScope::CONFIRM_OWNER_FIELD => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP23', 'company_id' => 648, 'category_name' => 'Kategori baru']);
        $log = json_decode((string) DB::table('activity_logs')->where('model_id', $id)->where('action', 'updated')->value('properties'), true);
        $this->assertSame(['app_code' => 'APP03', 'company_id' => 0], array_intersect_key($log['before'], ['app_code' => 1, 'company_id' => 1]));
        $this->assertSame(['app_code' => 'APP23', 'company_id' => 648], array_intersect_key($log['after'], ['app_code' => 1, 'company_id' => 1]));
    }

    public function test_pindah_ke_aplikasi_atau_company_tidak_terdaftar_ditolak_walau_dikonfirmasi(): void
    {
        $id = $this->category('APP03', 0);

        $this->save($id, ['owner_app_code' => 'APP99', 'owner_company_id' => 649, MasterScope::CONFIRM_OWNER_FIELD => '1'])
            ->assertSessionHasErrors([
                MasterScope::OWNER_APP_FIELD => 'Aplikasi APP99 tidak terdaftar aktif di master_apps, tidak bisa menjadi pemilik data.',
                MasterScope::OWNER_COMPANY_FIELD => 'Company id 649 tidak terdaftar aktif di master_company, tidak bisa menjadi pemilik data.',
            ]);
        // aplikasi yang sudah dihapus dari master_apps juga ditolak
        $this->save($id, ['owner_app_code' => 'APP44', 'owner_company_id' => 0, MasterScope::CONFIRM_OWNER_FIELD => '1'])
            ->assertSessionHasErrors(MasterScope::OWNER_APP_FIELD);

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP03', 'company_id' => 0, 'category_name' => 'Kategori lama']);
    }

    public function test_ganti_company_saja_juga_wajib_konfirmasi(): void
    {
        $id = $this->category('APP32', 647);

        $this->save($id, ['owner_app_code' => 'APP32', 'owner_company_id' => 648])
            ->assertSessionHasErrors(MasterScope::OWNER_COMPANY_FIELD)
            ->assertSessionDoesntHaveErrors(MasterScope::OWNER_APP_FIELD);

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP32', 'company_id' => 647]);
    }

    public function test_ubah_isi_tanpa_memindahkan_pemilik_tidak_perlu_konfirmasi(): void
    {
        $id = $this->category('APP03', 0);

        // form mengirim ulang pemilik yang sama (huruf kecil dari input lama tetap dianggap sama)
        $this->save($id, ['owner_app_code' => 'app03', 'owner_company_id' => 0])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP03', 'company_id' => 0, 'category_name' => 'Kategori baru']);
    }

    public function test_baris_ber_pemilik_tidak_terdaftar_tetap_bisa_diubah_isinya(): void
    {
        $id = $this->category('APP77', 650);

        $this->save($id, ['owner_app_code' => 'APP77', 'owner_company_id' => 650])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP77', 'company_id' => 650, 'category_name' => 'Kategori baru']);
    }

    public function test_bukan_admin_tidak_bisa_memindahkan_pemilik(): void
    {
        config(['test.allowed_permissions' => ['update_category']]);
        $id = $this->category('APP03', 0);

        $this->save($id, ['owner_app_code' => 'APP23', 'owner_company_id' => 648, MasterScope::CONFIRM_OWNER_FIELD => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'app_code' => 'APP03', 'company_id' => 0, 'category_name' => 'Kategori baru']);
    }

    public function test_data_baru_di_hub_wajib_pemilik_terdaftar(): void
    {
        $this->post(route('master.category.store'), ['category_code' => 'C02', 'category_name' => 'Baru', 'owner_app_code' => 'APP99', 'owner_company_id' => 0])
            ->assertSessionHasErrors(MasterScope::OWNER_APP_FIELD);
        $this->assertDatabaseMissing('master_category', ['category_code' => 'C02']);

        $this->post(route('master.category.store'), ['category_code' => 'C02', 'category_name' => 'Baru', 'owner_app_code' => 'APP23', 'owner_company_id' => 648])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_category', ['category_code' => 'C02', 'app_code' => 'APP23', 'company_id' => 648]);
    }

    public function test_form_edit_admin_memuat_konfirmasi_dan_menjaga_pemilik_tidak_terdaftar(): void
    {
        $row = DB::table('master_category')->find($this->category('APP77', 650));
        $render = fn () => view('master::partials.master-owner', ['table' => 'master_category', 'param' => $row, 'readonly' => false])
            ->with('errors', new ViewErrorBag)
            ->render();

        $admin = $render();
        $this->assertStringContainsString('name="'.MasterScope::CONFIRM_OWNER_FIELD.'" value="0"', $admin);
        $this->assertStringContainsString('window.masterConfirmDialog', $admin);
        // pemilik lama yang tidak terdaftar tetap terpilih, bukan opsi pertama daftar
        $this->assertMatchesRegularExpression('/<option value="APP77" selected>APP77 · \(tidak terdaftar di master_apps\)<\/option>/', $admin);
        $this->assertMatchesRegularExpression('/<option value="650" selected>Company 650 · \(tidak terdaftar di master_company\)<\/option>/', $admin);

        config(['test.allowed_permissions' => ['update_category']]);
        $user = $render();
        $this->assertStringNotContainsString(MasterScope::CONFIRM_OWNER_FIELD, $user);
        $this->assertStringContainsString('APP77 · Company 650', $user);
    }
}
