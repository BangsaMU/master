<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterAccess;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Support\MasterModules;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Pemilik data master app_code + company_id (MasterScope) dan aturan izin per baris (MasterAccess):
 * aplikasi klien hanya mengubah baris milik aplikasi + company user; di hub tim master boleh semua, user delegasi
 * hanya milik aplikasi/company asal.
 */
class MasterScopeAccessTest extends TestCase
{
    use CreatesMasterTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        // DashboardSettings membuat tabelnya sekali per proses, sedangkan DB in-memory dibuat ulang tiap test
        $this->createDashboardSettings();
        Artisan::call('master:scope-upgrade'); // tabel ber-app_code (category, uom, pca, item group, item code) + company_id

        $this->user = User::create(['name' => 'Admin SPB', 'email' => 'admin.spb@meindo.test']);
        DB::table('user_details')->insert(['user_email' => $this->user->email, 'field_key' => 'company_id', 'field_value' => '647']);
        $this->actingAs($this->user);
    }

    /** @param list<string> $permissions */
    private function permissions(array $permissions): void
    {
        config(['test.allowed_permissions' => $permissions]);
    }

    private function category(string $code, string $appCode, int $companyId): int
    {
        return DB::table('master_category')->insertGetId([
            'category_code' => $code, 'category_name' => 'Kategori '.$code.' '.$appCode.' '.$companyId,
            'app_code' => $appCode, 'company_id' => $companyId,
        ]);
    }

    private function hub(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => true, 'SsoConfig.main.APP_CODE' => 'APP03']);
    }

    /** @return array<string, mixed> */
    private function delegation(array $mods = ['master_category' => ['create', 'update']], bool $allCompanies = false): array
    {
        return [MasterScope::SESSION_KEY => [
            'app_code' => 'APP32', 'app_name' => 'SPB V2', 'company_id' => 647, 'company_code' => 'MEB',
            'all_co' => $allCompanies, 'mods' => $mods, 'back' => null, 'exp' => time() + 600,
        ]];
    }

    public function test_nama_permission_per_tabel_dan_alias_lama(): void
    {
        $this->assertSame('update_item_code', MasterModules::permission('item-code', 'update'));
        $this->assertSame(['read_item_group', 'read_itemgroup'], MasterModules::permissionNames('item-group', 'read'));
        $this->assertSame('create_item_code', MasterModules::legacyAliases()['create_item']);
        $this->assertContains('delete_project_detail', MasterModules::allPermissions());

        $this->permissions(['update_item']); // nama lama tetap diterima
        $this->assertTrue(app(MasterAccess::class)->can('item-code', 'update'));
        $this->assertFalse(app(MasterAccess::class)->can('item-code', 'create'));
    }

    public function test_klien_data_baru_diberi_pemilik_aplikasi_dan_company_user(): void
    {
        $this->post(route('master.category.store'), ['category_code' => 'c01', 'category_name' => 'Baru'])
            ->assertRedirect(route('master.category.index'));

        $this->assertDatabaseHas('master_category', ['category_code' => 'C01', 'app_code' => 'APP27', 'company_id' => 647]);
    }

    public function test_masa_transisi_kode_tetap_unik_global(): void
    {
        // UNIQUE_PER_OWNER kosong (default): kode sama di aplikasi lain ditolak supaya salinan lokal lama aman
        $this->category('C01', 'APP03', 0);
        $this->post(route('master.category.store'), ['category_code' => 'C01', 'category_name' => 'Milik APP27'])
            ->assertSessionHasErrors('category_code');
        $this->assertSame(0, DB::table('master_category')->where('app_code', 'APP27')->count());
    }

    public function test_masa_transisi_department_tetap_unik_per_aplikasi_seperti_v2_4(): void
    {
        $this->migrateDepartmentAppCode();
        Artisan::call('master:scope-upgrade', ['--table' => ['master_department']]);
        DB::table('master_department')->insert(['department_code' => 'HRD', 'department_name' => 'HRD Bersama', 'app_code' => 'APP03', 'company_id' => 0]);
        DB::table('master_department')->insert(['department_code' => 'IT', 'department_name' => 'IT MEB', 'app_code' => 'APP27', 'company_id' => 648]);

        // kode sama di aplikasi lain tetap boleh (sudah begitu sejak v2.4)
        $this->post(route('master.department.store'), ['department_code' => 'HRD', 'department_name' => 'HRD APP27'])
            ->assertSessionHasNoErrors();
        // company lain di aplikasi yang sama: ditolak selama transisi
        $this->post(route('master.department.store'), ['department_code' => 'IT', 'department_name' => 'IT 647'])
            ->assertSessionHasErrors('department_code');
    }

    public function test_kode_unik_berpasangan_dengan_pemilik(): void
    {
        config(['MasterCrudConfig.UNIQUE_PER_OWNER' => ['master_category']]);
        $this->category('C01', 'APP03', 0); // data bersama: kode sama boleh di aplikasi lain
        $this->post(route('master.category.store'), ['category_code' => 'C01', 'category_name' => 'Milik APP27'])
            ->assertSessionHasNoErrors();

        // aplikasi + company sama: ditolak
        $this->post(route('master.category.store'), ['category_code' => 'C01', 'category_name' => 'Dobel'])
            ->assertSessionHasErrors('category_code');
        $this->assertSame(1, DB::table('master_category')->where('app_code', 'APP27')->where('category_code', 'C01')->count());
    }

    public function test_kode_company_0_dan_company_tertentu_tidak_boleh_bersamaan(): void
    {
        config(['MasterCrudConfig.UNIQUE_PER_OWNER' => ['*']]);
        $this->category('C02', 'APP27', 0);

        $this->post(route('master.category.store'), ['category_code' => 'C02', 'category_name' => 'Khusus MEB'])
            ->assertSessionHasErrors(['category_code' => 'Kode C02 sudah dipakai untuk semua company di aplikasi APP27.']);
    }

    public function test_klien_hanya_mengubah_baris_milik_aplikasi_dan_company_user(): void
    {
        $this->permissions(['update_category']);
        $own = $this->category('C10', 'APP27', 647);
        $otherCompany = $this->category('C11', 'APP27', 648);
        $allCompanies = $this->category('C12', 'APP27', 0);
        $shared = $this->category('C13', 'APP03', 0);

        $this->post(route('master.category.store'), ['id' => $own, 'category_code' => 'C10', 'category_name' => 'Diubah'])
            ->assertRedirect(route('master.category.index'));
        $this->assertSame('Diubah', DB::table('master_category')->where('id', $own)->value('category_name'));

        foreach ([$otherCompany, $allCompanies, $shared] as $id) {
            $code = DB::table('master_category')->where('id', $id)->value('category_code');
            $this->post(route('master.category.store'), ['id' => $id, 'category_code' => $code, 'category_name' => 'Diubah'])
                ->assertForbidden();
        }
        $this->assertSame(0, DB::table('master_category')->where('id', '!=', $own)->where('category_name', 'Diubah')->count());
    }

    public function test_admin_aplikasi_mengelola_company_0_tetapi_bukan_data_aplikasi_lain(): void
    {
        $this->permissions(['is_admin']);
        $access = app(MasterAccess::class);

        $this->assertTrue($access->allows('category', 'update', $this->category('C20', 'APP27', 0)));
        $this->assertTrue($access->allows('category', 'update', $this->category('C21', 'APP27', 648)));
        $this->assertFalse($access->allows('category', 'update', $this->category('C22', 'APP03', 0)));
        $this->assertFalse($access->allows('category', 'delete', $this->category('C23', 'APP09', 647)));
    }

    public function test_hapus_baris_aplikasi_lain_ditolak(): void
    {
        $this->permissions(['delete_category']);
        $other = $this->category('C30', 'APP09', 647);

        $this->delete(route('master.category.destroy', $other))->assertForbidden();
        $this->assertNull(DB::table('master_category')->where('id', $other)->value('deleted_at'));
    }

    public function test_hub_tim_master_boleh_semua_pemilik(): void
    {
        $this->hub();
        $this->permissions(['update_category']);
        $id = $this->category('C40', 'APP32', 648);

        $this->post(route('master.category.store'), ['id' => $id, 'category_code' => 'C40', 'category_name' => 'Oleh tim master'])
            ->assertRedirect(route('master.category.index'));
        $this->assertSame('Oleh tim master', DB::table('master_category')->where('id', $id)->value('category_name'));
    }

    public function test_hub_tim_master_memilih_pemilik_data_baru(): void
    {
        $this->hub();
        $this->permissions(['create_category']);

        $this->post(route('master.category.store'), ['category_code' => 'C41', 'category_name' => 'Untuk SPB ENL', 'owner_app_code' => 'app32', 'owner_company_id' => 648])
            ->assertSessionHasNoErrors();
        $this->post(route('master.category.store'), ['category_code' => 'C42', 'category_name' => 'Bersama'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['category_code' => 'C41', 'app_code' => 'APP32', 'company_id' => 648]);
        $this->assertDatabaseHas('master_category', ['category_code' => 'C42', 'app_code' => 'APP03', 'company_id' => 0]);
    }

    public function test_hub_delegasi_hanya_milik_aplikasi_dan_company_asal(): void
    {
        $this->hub();
        $this->permissions([]); // tanpa permission master-data sama sekali
        $own = $this->category('C50', 'APP32', 647);
        $otherCompany = $this->category('C51', 'APP32', 648);
        $allCompanies = $this->category('C52', 'APP32', 0);

        $this->withSession($this->delegation())
            ->post(route('master.category.store'), ['id' => $own, 'category_code' => 'C50', 'category_name' => 'Lewat delegasi'])
            ->assertRedirect(route('master.category.index'));
        $this->assertSame('Lewat delegasi', DB::table('master_category')->where('id', $own)->value('category_name'));

        foreach ([[$otherCompany, 'C51'], [$allCompanies, 'C52']] as [$id, $code]) {
            $this->withSession($this->delegation())
                ->post(route('master.category.store'), ['id' => $id, 'category_code' => $code, 'category_name' => 'X'])
                ->assertForbidden();
        }

        // aksi yang tidak didelegasikan (delete) dan modul lain ditolak
        $this->withSession($this->delegation())->delete(route('master.category.destroy', $own))->assertForbidden();
        $this->withSession($this->delegation())
            ->post(route('master.uom.store'), ['uom_code' => 'KG', 'uom_name' => 'Kilogram'])
            ->assertForbidden();
    }

    public function test_hub_delegasi_all_co_boleh_company_0(): void
    {
        $this->hub();
        $this->permissions([]);
        $allCompanies = $this->category('C53', 'APP32', 0);

        $this->withSession($this->delegation(allCompanies: true))
            ->post(route('master.category.store'), ['id' => $allCompanies, 'category_code' => 'C53', 'category_name' => 'Semua company'])
            ->assertRedirect(route('master.category.index'));
    }

    public function test_hub_delegasi_data_baru_diberi_pemilik_asal_bukan_pilihan_form(): void
    {
        $this->hub();
        $this->permissions([]);

        $this->withSession($this->delegation())
            ->post(route('master.category.store'), ['category_code' => 'C60', 'category_name' => 'Baru', 'owner_app_code' => 'APP03', 'owner_company_id' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['category_code' => 'C60', 'app_code' => 'APP32', 'company_id' => 647]);
    }

    public function test_hub_delegasi_daftar_hanya_data_bersama_dan_milik_asal(): void
    {
        $this->hub();
        $this->permissions([]);
        $this->category('S01', 'APP03', 0);
        $this->category('O01', 'APP32', 647);
        $this->category('O02', 'APP32', 0);
        $this->category('X01', 'APP32', 648);
        $this->category('X02', 'APP09', 647);

        $codes = collect($this->withSession($this->delegation())
            ->getJson(route('master.category.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('data'))->pluck('category_code')->sort()->values()->all();

        $this->assertSame(['O01', 'O02', 'S01'], $codes);
    }

    public function test_delegasi_kedaluwarsa_tidak_berlaku(): void
    {
        $this->hub();
        $this->permissions([]);
        $own = $this->category('C70', 'APP32', 647);
        $session = $this->delegation();
        $session[MasterScope::SESSION_KEY]['exp'] = time() - 1;

        $this->withSession($session)
            ->post(route('master.category.store'), ['id' => $own, 'category_code' => 'C70', 'category_name' => 'X'])
            ->assertForbidden();
    }
}
