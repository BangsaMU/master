<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bug paket yang ditemukan test master-data (inventaris C2, C3, C6, C11, D1, D4): hapus company 500 (route
 * company.index tidak ada), department & item-group terhapus permanen, project-detail tanpa destroy, class UoM
 * salah huruf (Uom.php), dan api/master-sync/* terbuka tanpa login.
 */
class MasterHapusDataTest extends TestCase
{
    use CreatesMasterTables;

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        // Gallery::booted() membuat tabel hanya sekali per proses, sedangkan DB in-memory baru tiap test
        if (! Schema::hasTable('master_gallery')) {
            Schema::create('master_gallery', function (Blueprint $table) {
                $table->id();
                $table->text('url')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function login(): void
    {
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    public static function modulProvider(): array
    {
        return [
            'company' => ['company', 'master_company', ['company_code' => 'TCOM01']],
            'department' => ['department', 'master_department', ['department_code' => 'TDEP01', 'department_name' => 'Departemen Uji']],
            'item-group' => ['item-group', 'master_item_group', ['item_group_code' => 'TGRP01', 'item_group_name' => 'Grup Uji']],
            'project-detail' => ['project-detail', 'master_project_detail', ['project_code_client' => 'TCLI01', 'project_name_client' => 'Klien Uji']],
        ];
    }

    #[DataProvider('modulProvider')]
    public function test_hapus_data_master_soft_delete_dan_kembali_ke_daftar(string $slug, string $tabel, array $baris): void
    {
        $this->login();
        // baris milik aplikasi ini (APP27 di TestCase): sejak v3.0 baris aplikasi lain tidak bisa dihapus dari klien
        $id = DB::table($tabel)->insertGetId(Schema::hasColumn($tabel, 'app_code') ? $baris + ['app_code' => 'APP27'] : $baris);

        $this->delete("/master/$slug/$id")->assertRedirect(route("master.$slug.index"));

        $this->assertSoftDeleted($tabel, ['id' => $id]);
    }

    #[DataProvider('modulProvider')]
    public function test_hapus_id_yang_tidak_ada_404(string $slug): void
    {
        $this->login();

        $this->delete("/master/$slug/999")->assertNotFound();
    }

    public function test_lookup_uom_tidak_error(): void
    {
        $this->login();
        DB::table('master_uom')->insert(['uom_code' => 'PCS', 'uom_name' => 'Pieces']);

        $this->getJson('/api/getuombyparams')->assertOk()->assertSee('PCS');
    }

    /**
     * Linux peka huruf besar/kecil: `use ...\Models\UoM` gagal dimuat bila filenya Uom.php. Di test fungsional bisa
     * lolos palsu bila class sudah dimuat test lain, jadi semua import kelas paket dicek ke nama file.
     */
    public function test_semua_import_kelas_paket_menunjuk_file_yang_ada(): void
    {
        $src = realpath(__DIR__.'/../../src');
        $salah = [];

        foreach (File::allFiles($src) as $file) {
            preg_match_all('/^use (Bangsamu\\\\Master\\\\[A-Za-z0-9_\\\\]+)( as \w+)?;/m', $file->getContents(), $cocok);
            foreach ($cocok[1] as $kelas) {
                $path = $src.'/'.str_replace('\\', '/', substr($kelas, strlen('Bangsamu\\Master\\'))).'.php';
                if (! in_array($path, array_map('strval', File::files(dirname($path))), true) && ! is_dir(substr($path, 0, -4))) {
                    $salah[] = $file->getRelativePathname().': '.$kelas;
                }
            }
        }

        $this->assertSame([], $salah);
    }

    public function test_api_master_sync_wajib_login(): void
    {
        $this->getJson('/api/master-sync/allowed-tables')->assertUnauthorized();
        $this->getJson('/api/master-sync/catch-up')->assertUnauthorized();
        $this->postJson('/api/master-sync/sync', ['table' => 'master_company', 'id' => 1, 'action' => 'deleted'])->assertUnauthorized();
        $this->postJson('/api/master-sync/sync-items', ['table' => 'master_item_code', 'id' => 1])->assertUnauthorized();
    }

    public function test_api_master_sync_tetap_bisa_dipakai_user_login(): void
    {
        $this->login();

        $this->getJson('/api/master-sync/allowed-tables')->assertOk()->assertJsonPath('success', true);
    }
}
