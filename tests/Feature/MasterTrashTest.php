<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Controllers\MasterRestoreController;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Data master terhapus (soft delete): kodenya tetap terpakai, jadi pesan "sudah dipakai" harus menjelaskan bahwa
 * datanya terhapus (nama, kapan, oleh siapa, cara memulihkan); admin melihat baris terhapus di daftar master
 * bertanda Terhapus dengan tombol Restore (MasterTrash, MasterRestoreController).
 * Temuan user: buat Project 20000 ditolak "The project code has already been taken" padahal daftar kosong.
 */
class MasterTrashTest extends TestCase
{
    use CreatesMasterTables;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        // model Master* memakai koneksi db_master: di test satu database dengan koneksi default (seperti hub)
        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('libraryclay.activity_log.console', true);
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
        // skema lama master_department dinaikkan (app_code) saat modul dibuka; naikkan dulu supaya baris uji milik APP27
        app(MasterSchemaUpgrader::class)->ensure('master_department');
    }

    private function login(bool $admin = true): void
    {
        config(['test.denied_permissions' => $admin ? [] : ['is_admin']]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    /** baris milik aplikasi ini (APP27 di TestCase) bila tabel ber-app_code */
    private function insert(string $tabel, array $baris, bool $terhapus = true): int
    {
        if (Schema::hasColumn($tabel, 'app_code')) {
            $baris += ['app_code' => 'APP27'];
        }

        return DB::table($tabel)->insertGetId($baris + ['deleted_at' => $terhapus ? '2025-05-05 16:17:53' : null]);
    }

    /** @return array<int, array<string, mixed>> */
    private function daftar(string $slug): array
    {
        return $this->getJson("/master/$slug", ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    private function barisDaftar(string $slug, string $kolom, string $kode): ?array
    {
        return collect($this->daftar($slug))->firstWhere($kolom, $kode);
    }

    private function simpanProject(array $data): ?string
    {
        $this->from('/master/project/create')->post('/master/project', $data)->assertRedirect('/master/project/create');

        return session('errors')?->first('project_code');
    }

    public function test_kode_project_milik_data_terhapus_dijelaskan_lengkap_untuk_admin(): void
    {
        $this->login();
        $id = $this->insert('master_project', ['project_code' => '20000', 'project_name' => 'Heavy Equipment'], false);
        $this->delete("/master/project/$id")->assertRedirect();
        $this->assertSoftDeleted('master_project', ['id' => $id]);

        $pesan = $this->simpanProject(['project_code' => '20000', 'project_name' => '']);

        $this->assertStringContainsString('Project Code 20000 sudah ada tetapi datanya sudah dihapus (Heavy Equipment, dihapus ', $pesan);
        $this->assertStringContainsString('oleh Gita Samudra', $pesan);
        $this->assertStringContainsString('tombol Restore di daftar Project', $pesan);
        $this->assertStringNotContainsString('already been taken', $pesan);
    }

    public function test_kode_terhapus_untuk_non_admin_diarahkan_ke_admin(): void
    {
        $this->login(admin: false);
        $this->insert('master_project', ['project_code' => '20000', 'project_name' => 'Heavy Equipment']);

        $pesan = $this->simpanProject(['project_code' => '20000', 'project_name' => 'Baru']);

        $this->assertStringContainsString('sudah dihapus (Heavy Equipment, dihapus 05-05-2025 16:17)', $pesan);
        $this->assertStringContainsString('Minta admin memulihkannya (Restore) dari daftar Project', $pesan);
        $this->assertSame(1, DB::table('master_project')->where('project_code', '20000')->count());
    }

    public function test_kode_milik_data_aktif_menyebut_nama_dan_ubah_kode_sendiri_tetap_lolos(): void
    {
        $this->login();
        $this->insert('master_project', ['project_code' => '20000', 'project_name' => 'Heavy Equipment'], false);
        $sendiri = $this->insert('master_project', ['project_code' => '20001', 'project_name' => 'Proyek Lain'], false);

        $this->assertSame('Project Code 20000 sudah dipakai: Heavy Equipment.', $this->simpanProject(['project_code' => '20000', 'project_name' => 'Baru']));

        $this->post('/master/project', ['id' => $sendiri, 'project_code' => '20001', 'project_name' => 'Proyek Lain Diubah'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_project', ['id' => $sendiri, 'project_name' => 'Proyek Lain Diubah']);
    }

    public function test_kode_tabel_ber_pemilik_menyebut_pemilik_data_aktif(): void
    {
        $this->login();
        DB::table('master_category')->insert(['category_code' => 'ELC', 'category_name' => 'Electrical', 'app_code' => 'APP03']);

        $this->from('/master/category/create')->post('/master/category', ['category_code' => 'elc', 'category_name' => 'Listrik'])
            ->assertSessionHasErrors(['category_code' => 'Category Code ELC sudah dipakai: Electrical, pemilik APP03 · Semua company.']);
    }

    public static function modulProvider(): array
    {
        return [
            'category' => ['category', 'master_category', 'category_code', ['category_code' => 'TCAT01', 'category_name' => 'Kategori Uji']],
            'uom' => ['uom', 'master_uom', 'uom_code', ['uom_code' => 'TUOM01', 'uom_name' => 'Satuan Uji']],
            'pca' => ['pca', 'master_pca', 'pca_code', ['pca_code' => 'TPCA01', 'pca_name' => 'PCA Uji']],
            'item-group' => ['item-group', 'master_item_group', 'item_group_code', ['item_group_code' => 'TGRP01', 'item_group_name' => 'Grup Uji']],
            'item-code' => ['item-code', 'master_item_code', 'item_code', ['item_code' => 'TITM01', 'item_name' => 'Barang Uji']],
            'project' => ['project', 'master_project', 'project_code', ['project_code' => 'TPRJ01', 'project_name' => 'Proyek Uji']],
            'department' => ['department', 'master_department', 'department_code', ['department_code' => 'TDEP01', 'department_name' => 'Departemen Uji']],
            'location' => ['location', 'master_location', 'loc_code', ['loc_code' => 'TLOC01', 'loc_name' => 'Lokasi Uji', 'group_type' => 'warehouse']],
            'vendor' => ['vendor', 'master_vendor', 'vendor_code', ['vendor_code' => 'TVEN01', 'vendor_description' => 'Vendor Uji', 'loc_id' => 0]],
        ];
    }

    #[DataProvider('modulProvider')]
    public function test_admin_melihat_baris_terhapus_dengan_tombol_restore(string $slug, string $tabel, string $kolom, array $baris): void
    {
        $this->login();
        $id = $this->insert($tabel, $baris);

        $row = $this->barisDaftar($slug, $kolom, $baris[$kolom]);

        $this->assertNotNull($row, "baris terhapus $slug tidak tampil untuk admin");
        $this->assertSame('master-row-trashed', $row['DT_RowClass']);
        $this->assertStringContainsString('Terhapus', $row['action']);
        $this->assertStringContainsString(route("master.$slug.restore", $id), $row['action']);
        $this->assertStringNotContainsString('>Delete<', $row['action']);
    }

    #[DataProvider('modulProvider')]
    public function test_non_admin_tidak_melihat_baris_terhapus(string $slug, string $tabel, string $kolom, array $baris): void
    {
        $this->login(admin: false);
        $this->insert($tabel, $baris);
        $aktif = $this->insert($tabel, [$kolom => $baris[$kolom].'A'] + $baris, false);

        $this->assertNull($this->barisDaftar($slug, $kolom, $baris[$kolom]));
        $this->assertSame('', $this->barisDaftar($slug, $kolom, $baris[$kolom].'A')['DT_RowClass'] ?? null, "baris aktif $slug id $aktif");
    }

    #[DataProvider('modulProvider')]
    public function test_admin_memulihkan_data_terhapus(string $slug, string $tabel, string $kolom, array $baris): void
    {
        $this->login();
        $id = $this->insert($tabel, $baris);

        $this->post(route("master.$slug.restore", $id))
            ->assertRedirect(route("master.$slug.index"))
            ->assertSessionHas('success_message', fn ($pesan) => str_contains($pesan, 'berhasil dipulihkan'));

        $this->assertDatabaseHas($tabel, ['id' => $id, 'deleted_at' => null]);
        // tercatat atas nama model kartu riwayat form (ActivityLogService::forModel(Models\{Studly}))
        $this->assertDatabaseHas('activity_logs', ['model_id' => $id, 'action' => 'restored', 'model_type' => 'Bangsamu\\Master\\Models\\'.Str::studly($slug)]);
        $this->assertSame('', $this->barisDaftar($slug, $kolom, $baris[$kolom])['DT_RowClass']);
    }

    public function test_non_admin_tidak_bisa_memulihkan(): void
    {
        $this->login(admin: false);
        $id = $this->insert('master_project', ['project_code' => '20000', 'project_name' => 'Heavy Equipment']);

        $this->post(route('master.project.restore', $id))->assertForbidden();

        $this->assertSoftDeleted('master_project', ['id' => $id]);
    }

    public function test_admin_aplikasi_klien_tidak_bisa_memulihkan_data_aplikasi_lain(): void
    {
        $this->login();
        $id = DB::table('master_category')->insertGetId(['category_code' => 'ELC', 'category_name' => 'Electrical', 'app_code' => 'APP03', 'deleted_at' => now()]);

        $row = $this->barisDaftar('category', 'category_code', 'ELC');
        $this->assertStringContainsString('Terhapus', $row['action']);
        $this->assertStringNotContainsString('/restore', $row['action']);

        $this->post(route('master.category.restore', $id))->assertForbidden();
        $this->assertSoftDeleted('master_category', ['id' => $id]);
    }

    public function test_admin_hub_memulihkan_data_aplikasi_mana_pun(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => true]);
        $this->login();
        $id = DB::table('master_category')->insertGetId(['category_code' => 'ELC', 'category_name' => 'Electrical', 'app_code' => 'APP32', 'deleted_at' => now()]);

        $this->post(route('master.category.restore', $id))->assertRedirect();

        $this->assertDatabaseHas('master_category', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_tidak_dipulihkan_bila_kode_sudah_dipakai_data_aktif(): void
    {
        $this->login();
        $id = $this->insert('master_category', ['category_code' => 'ELC', 'category_name' => 'Electrical Lama']);
        $aktif = $this->insert('master_category', ['category_code' => 'ELC', 'category_name' => 'Electrical'], false);

        $this->post(route('master.category.restore', $id))
            ->assertSessionHas('error_message', "Category ELC tidak dapat dipulihkan: kodenya sudah dipakai data aktif id $aktif.");

        $this->assertSoftDeleted('master_category', ['id' => $id]);
    }

    public function test_pulihkan_data_yang_tidak_terhapus_tidak_mengubah_apa_pun(): void
    {
        $this->login();
        $id = $this->insert('master_project', ['project_code' => '20000', 'project_name' => 'Heavy Equipment'], false);

        $this->post(route('master.project.restore', $id))
            ->assertSessionHas('success_message', 'Project 20000 tidak terhapus, tidak ada yang dipulihkan.');
        $this->assertDatabaseMissing('activity_logs', ['model_id' => $id, 'action' => 'restored']);
    }

    public function test_pulihkan_vendor_ikut_memulihkan_lokasi_vendor(): void
    {
        $this->login();
        $loc = DB::table('master_location')->insertGetId(['loc_code' => 'TVEN01', 'loc_name' => 'Vendor Uji', 'group_type' => 'vendor', 'deleted_at' => now()]);
        $id = $this->insert('master_vendor', ['vendor_code' => 'TVEN01', 'vendor_description' => 'Vendor Uji', 'loc_id' => $loc]);

        $this->post(route('master.vendor.restore', $id))->assertRedirect();

        $this->assertDatabaseHas('master_location', ['id' => $loc, 'deleted_at' => null]);
        $this->assertSame(1, DB::table('master_location')->where('loc_code', 'TVEN01')->count(), 'lokasi vendor tidak boleh kembar');
        $this->assertDatabaseHas('master_vendor', ['id' => $id, 'loc_id' => $loc, 'deleted_at' => null]);
    }

    public function test_route_restore_terdaftar_untuk_semua_modul_restore(): void
    {
        foreach (MasterRestoreController::MODULES as $slug) {
            $this->assertTrue(Route::has("master.$slug.restore"), "route master.$slug.restore");
            $this->assertTrue(Route::has("master.$slug.index"), "route master.$slug.index");
        }
    }
}
