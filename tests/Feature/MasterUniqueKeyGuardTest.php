<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MasterUniqueKeyGuardTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    private function asNonAdmin(): void
    {
        config(['test.denied_permissions' => ['is_admin']]);
    }

    private function category(): int
    {
        return DB::table('master_category')->insertGetId(['category_code' => 'A01', 'category_name' => 'Lama', 'app_code' => 'APP27']);
    }

    public function test_kode_baru_disimpan_huruf_besar(): void
    {
        $this->post(route('master.category.store'), ['category_code' => ' a99 ', 'category_name' => 'Baru'])
            ->assertRedirect(route('master.category.index'));

        $this->assertDatabaseHas('master_category', ['category_code' => 'A99', 'category_name' => 'Baru']);
    }

    public function test_non_admin_tidak_bisa_mengubah_kunci_unik(): void
    {
        $this->asNonAdmin();
        $id = $this->category();

        $this->from(route('master.category.edit', $id))
            ->post(route('master.category.store'), ['id' => $id, 'category_code' => 'A02', 'category_name' => 'Ganti', 'confirm_key_change' => '1'])
            ->assertRedirect(route('master.category.edit', $id))
            ->assertSessionHasErrors(['category_code' => 'Category Code adalah kunci unik dan tidak boleh diubah. Hubungi admin bila perlu koreksi.']);

        $this->assertDatabaseHas('master_category', ['id' => $id, 'category_code' => 'A01', 'category_name' => 'Lama']);
    }

    public function test_non_admin_tetap_bisa_mengubah_kolom_lain(): void
    {
        $this->asNonAdmin();
        $id = $this->category();

        // huruf kecil dianggap kode yang sama
        $this->post(route('master.category.store'), ['id' => $id, 'category_code' => 'a01', 'category_name' => 'Nama Baru'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'category_code' => 'A01', 'category_name' => 'Nama Baru']);
    }

    public function test_admin_wajib_konfirmasi_saat_mengubah_kunci_unik(): void
    {
        $id = $this->category();

        $this->post(route('master.category.store'), ['id' => $id, 'category_code' => 'a02', 'category_name' => 'Lama'])
            ->assertSessionHasErrors(['category_code' => 'Perubahan kunci unik (Category Code) harus dikonfirmasi sebelum disimpan.']);
        $this->assertSame('A01', DB::table('master_category')->where('id', $id)->value('category_code'));

        $this->post(route('master.category.store'), ['id' => $id, 'category_code' => 'a02', 'category_name' => 'Lama', 'confirm_key_change' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('A02', DB::table('master_category')->where('id', $id)->value('category_code'));
    }

    public function test_kunci_gabungan_location_group_type_dikunci(): void
    {
        $this->asNonAdmin();
        $id = DB::table('master_location')->insertGetId(['loc_code' => 'JKT', 'loc_name' => 'Jakarta', 'group_type' => 'office']);

        $this->post(route('master.location.store'), ['id' => $id, 'loc_code' => 'jkt', 'loc_name' => 'Jakarta', 'group_type' => 'warehouse'])
            ->assertSessionHasErrors('group_type');

        $this->assertSame('office', DB::table('master_location')->where('id', $id)->value('group_type'));
    }

    public function test_select_kunci_yang_disabled_memakai_nilai_lama(): void
    {
        $this->asNonAdmin();
        DB::table('master_company')->insert(['id' => 7, 'company_code' => 'ME']);
        DB::table('master_project')->insert(['id' => 3, 'project_code' => 'P001', 'project_name' => 'Proyek']);
        $id = DB::table('master_project_detail')->insertGetId(['project_id' => 3, 'project_code_client' => 'C-1', 'project_name_client' => 'Lama', 'company_id' => 7]);

        // project_id & company_id tidak terkirim (select disabled di form)
        $this->post(route('master.project-detail.store'), ['id' => $id, 'project_code_client' => 'c-1', 'project_name_client' => 'Nama Client Baru'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_project_detail', ['id' => $id, 'project_id' => 3, 'company_id' => 7, 'project_code_client' => 'C-1', 'project_name_client' => 'Nama Client Baru']);
    }

    public function test_form_edit_memuat_konfigurasi_penjaga_kunci(): void
    {
        $id = $this->category();

        $admin = $this->get(route('master.category.edit', $id))->assertOk()->getContent();
        $this->assertStringContainsString('"locked":false,"confirm":true', $admin);

        $this->asNonAdmin();
        $user = $this->get(route('master.category.edit', $id))->assertOk()->getContent();
        $this->assertStringContainsString('"locked":true,"confirm":false', $user);
        $this->assertStringContainsString('"codes":["category_code"]', $user);
    }
}
