<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class DepartmentImportTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    public function test_migration_menambah_app_code_dan_mengganti_kunci_unik(): void
    {
        DB::table('master_department')->insert(['department_code' => 'ACC', 'department_name' => 'Accounting']);

        $this->migrateDepartmentAppCode();
        $this->migrateDepartmentAppCode(); // aman dijalankan ulang

        $this->assertTrue(Schema::hasColumn('master_department', 'app_code'));
        $this->assertSame('APP03', DB::table('master_department')->where('department_code', 'ACC')->value('app_code'), 'data lama tetap ada dengan app_code default');

        // kode sama boleh untuk aplikasi lain
        DB::table('master_department')->insert(['department_code' => 'ACC', 'department_name' => 'Accounting APP27', 'app_code' => 'APP27']);
        $this->assertSame(2, DB::table('master_department')->where('department_code', 'ACC')->count());

        // kode sama di aplikasi yang sama ditolak
        $this->expectException(QueryException::class);
        DB::table('master_department')->insert(['department_code' => 'ACC', 'department_name' => 'Dobel', 'app_code' => 'APP27']);
    }

    public function test_migration_down_mengembalikan_unik_lama_tanpa_menghapus_kolom(): void
    {
        $this->migrateDepartmentAppCode();
        (require __DIR__.'/../../src/database/migrations/2026_10_22_000001_add_app_code_to_master_department_table.php')->down();

        $this->assertTrue(Schema::hasColumn('master_department', 'app_code'));
        DB::table('master_department')->insert(['department_code' => 'IT', 'department_name' => 'IT', 'app_code' => 'APP27']);
        $this->expectException(QueryException::class);
        DB::table('master_department')->insert(['department_code' => 'IT', 'department_name' => 'IT lain', 'app_code' => 'APP09']);
    }

    public function test_import_department_unik_per_aplikasi(): void
    {
        $this->migrateDepartmentAppCode();
        DB::table('master_department')->insert([
            ['department_code' => 'ACC', 'department_name' => 'Accounting Lama', 'app_code' => 'APP27'],
            ['department_code' => 'HR', 'department_name' => 'Human Resource', 'app_code' => 'APP09'],
        ]);

        $file = UploadedFile::fake()->createWithContent('dept.csv', "Department Code,Department Name\nacc,Accounting Department\nhr,Human Resource APP27\nIT,Information Technology\n");
        $location = $this->post(route('master.import.preview', 'department'), ['file' => $file])->headers->get('Location');
        $token = basename($location);

        $rows = $this->get(route('master.import.show', ['department', $token]))->assertOk()->viewData('analysis')['rows'];
        $this->assertSame(['update', 'new', 'new'], array_column($rows, 'status'));
        $this->assertSame(['department_name'], array_keys($rows[0]['changes']));
        $this->assertContains('Kode yang sama juga dipakai aplikasi APP09 (data terpisah per aplikasi).', $rows[1]['notes']);

        $this->post(route('master.import.commit', ['department', $token]), ['allow_update' => '1', 'update_reason' => 'nama resmi department'])
            ->assertSessionHas('success_message', fn ($m) => str_contains($m[0], '2 dibuat, 1 diupdate'));

        $this->assertSame('Accounting Department', DB::table('master_department')->where(['department_code' => 'ACC', 'app_code' => 'APP27'])->value('department_name'));
        $this->assertDatabaseHas('master_department', ['department_code' => 'HR', 'app_code' => 'APP27', 'department_name' => 'Human Resource APP27']);
        $this->assertDatabaseHas('master_department', ['department_code' => 'HR', 'app_code' => 'APP09', 'department_name' => 'Human Resource']);
        $this->assertSame(1, ActivityLog::where('description', 'Bagas Setyo updated Department (via import: nama resmi department)')->count());
        $this->assertSame(2, ActivityLog::where('description', 'Bagas Setyo created a new Department (via import)')->count());
    }

    public function test_import_tanpa_migration_memberi_pesan_jelas(): void
    {
        $file = UploadedFile::fake()->createWithContent('dept.csv', "Department Code,Department Name\nIT,Information Technology\n");
        $token = basename($this->post(route('master.import.preview', 'department'), ['file' => $file])->headers->get('Location'));

        $rows = $this->get(route('master.import.show', ['department', $token]))->viewData('analysis')['rows'];
        $this->assertSame('error', $rows[0]['status']);
        $this->assertStringContainsString('php artisan migrate', $rows[0]['message']);
    }

    public function test_form_create_menyimpan_app_code_dan_unik_per_aplikasi(): void
    {
        $this->migrateDepartmentAppCode();
        DB::table('master_department')->insert(['department_code' => 'ACC', 'department_name' => 'Accounting APP09', 'app_code' => 'APP09']);

        $this->post(route('master.department.store'), ['department_code' => 'acc', 'department_name' => 'Accounting'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_department', ['department_code' => 'ACC', 'department_name' => 'Accounting', 'app_code' => 'APP27']);

        $this->post(route('master.department.store'), ['department_code' => 'ACC', 'department_name' => 'Dobel'])
            ->assertSessionHasErrors('department_code');
    }

    public function test_department_milik_aplikasi_lain_tidak_bisa_diedit(): void
    {
        $this->migrateDepartmentAppCode();
        $id = DB::table('master_department')->insertGetId(['department_code' => 'HR', 'department_name' => 'Human Resource', 'app_code' => 'APP09']);

        $this->post(route('master.department.store'), ['id' => $id, 'department_code' => 'HR', 'department_name' => 'Diubah'])
            ->assertSessionHasErrors(['department_code' => 'Department ini milik aplikasi APP09, tidak dapat diubah dari aplikasi ini.']);

        $this->assertSame('Human Resource', DB::table('master_department')->where('id', $id)->value('department_name'));
    }

    public function test_dropdown_api_menandai_department_aplikasi_lain(): void
    {
        $this->migrateDepartmentAppCode();
        DB::table('master_department')->insert([
            ['department_code' => 'ACC', 'department_name' => 'A', 'app_code' => 'APP27'],
            ['department_code' => 'ACC', 'department_name' => 'B', 'app_code' => 'APP09'],
        ]);

        $texts = collect($this->getJson(route('getdepartmentbyparams'))->json())->pluck('text')->all();

        $this->assertEqualsCanonicalizing(['ACC', 'ACC (APP09)'], $texts);
    }
}
