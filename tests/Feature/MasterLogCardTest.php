<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\ActivityLogService;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Kartu Log di halaman edit/view modul master (seperti "Log Karyawan"). Form & simpan memakai Models\{Studly}, hapus dan
 * restore memakai Models\Master{Studly}; dulu kartu hanya membaca satu nama model sehingga hapus tidak tampil di 9
 * modul, dan Company/Priority tidak punya kartu Log. Kini kartu membaca semua nama model (ActivityLogService::forSlug).
 */
class MasterLogCardTest extends TestCase
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
        View::addNamespace('adminlte', __DIR__.'/../stubs/adminlte');
        $this->createDashboardSettings();
        DB::connection('db_master')->setPdo(DB::connection()->getPdo());
        app(MasterSchemaUpgrader::class)->ensure('master_department');

        // Gallery::booted() membuat tabel hanya sekali per proses, sedangkan DB in-memory baru tiap test
        if (! Schema::hasTable('master_gallery')) {
            Schema::create('master_gallery', function (Blueprint $table) {
                $table->id();
                $table->text('url')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    /** baris aktif milik aplikasi ini (APP27 di TestCase) bila tabel ber-app_code */
    private function insert(string $tabel, array $baris): int
    {
        if (Schema::hasColumn($tabel, 'app_code')) {
            $baris += ['app_code' => 'APP27'];
        }

        return DB::table($tabel)->insertGetId($baris);
    }

    /**
     * Modul yang halaman show-nya bisa dirender di skema SQLite test paket. Company, Employee, Location, Priority dan
     * Project Detail butuh kolom/tabel/SHOW COLUMNS MySQL: diuji di aplikasi master-data (MariaDB, ke-13 modul).
     */
    public static function modulProvider(): array
    {
        return [
            'category' => ['category', 'master_category', 'Log Category', 'Category', ['category_code' => 'TCAT01', 'category_name' => 'Kategori Uji']],
            'department' => ['department', 'master_department', 'Log Department', 'Department', ['department_code' => 'TDEP01', 'department_name' => 'Departemen Uji']],
            'item-code' => ['item-code', 'master_item_code', 'Log Item Code', 'ItemCode', ['item_code' => 'TITM01', 'item_name' => 'Barang Uji']],
            'item-group' => ['item-group', 'master_item_group', 'Log Item Group', 'ItemGroup', ['item_group_code' => 'TGRP01', 'item_group_name' => 'Grup Uji']],
            'pca' => ['pca', 'master_pca', 'Log PCA', 'Pca', ['pca_code' => 'TPCA01', 'pca_name' => 'PCA Uji']],
            'project' => ['project', 'master_project', 'Log Project', 'Project', ['project_code' => 'TPRJ01', 'project_name' => 'Proyek Uji']],
            'uom' => ['uom', 'master_uom', 'Log UOM', 'Uom', ['uom_code' => 'TUOM01', 'uom_name' => 'Satuan Uji']],
            'vendor' => ['vendor', 'master_vendor', 'Log Vendor', 'Vendor', ['vendor_code' => 'TVEN01', 'vendor_description' => 'Vendor Uji', 'loc_id' => 0]],
        ];
    }

    #[DataProvider('modulProvider')]
    public function test_hapus_tercatat_dan_tampil_di_kartu_log(string $slug, string $tabel, string $judul, string $modul, array $baris): void
    {
        $id = $this->insert($tabel, $baris);

        $this->delete("/master/$slug/$id")->assertRedirect(route("master.$slug.index"));

        $this->assertSoftDeleted($tabel, ['id' => $id]);
        $this->assertDatabaseHas('activity_logs', ['model_id' => $id, 'action' => 'deleted']);

        $response = $this->get(route("master.$slug.show", $id))->assertOk();
        $logs = collect($response->viewData('data')['page']['logs']->items());
        $hapus = $logs->firstWhere('action', 'deleted');
        $this->assertNotNull($hapus, "log hapus $slug tidak tampil di kartu Log");
        $this->assertSame('Gita Samudra', $hapus->nama_user);
        $this->assertSame($modul, $hapus->sort_description, 'nama modul tanpa awalan Master');
        $response->assertSee($judul);
        $response->assertSee('Data dihapus (soft delete)');
        $response->assertSee('bi-trash text-bg-danger', false);
    }

    public function test_restore_tercatat_sekali_di_kartu_log(): void
    {
        $id = $this->insert('master_project', ['project_code' => 'TPRJ01', 'project_name' => 'Proyek Uji']);
        $this->delete("/master/project/$id")->assertRedirect();

        $this->post(route('master.project.restore', $id))->assertRedirect(route('master.project.index'));

        $this->assertDatabaseHas('master_project', ['id' => $id, 'deleted_at' => null]);
        // dulu Loggable ikut mencatat "updated" deleted_at -> null atas nama MasterProject di samping "restored"
        $this->assertSame(['restored', 'deleted'], ActivityLog::whereIn('model_type', ActivityLogService::modelTypes('project'))
            ->where('model_id', $id)->orderByDesc('id')->pluck('action')->all());

        $response = $this->get(route('master.project.edit', $id))->assertOk();
        $this->assertSame(['restored', 'deleted'], collect($response->viewData('data')['page']['logs']->items())->pluck('action')->all());
        $response->assertSee('bi-arrow-counterclockwise text-bg-success', false);
    }

    public function test_ubah_dan_hapus_beda_nama_model_tampil_dalam_satu_kartu(): void
    {
        $id = $this->insert('master_category', ['category_code' => 'TCAT01', 'category_name' => 'Kategori Uji']);
        ActivityLog::create(['action' => 'updated', 'model_type' => 'Bangsamu\\Master\\Models\\Category', 'model_id' => $id,
            'description' => 'Gita Samudra updated Category', 'properties' => ['before' => ['category_name' => 'Lama'], 'after' => ['category_name' => 'Kategori Uji']]]);
        ActivityLog::create(['action' => 'deleted', 'model_type' => 'Bangsamu\\Master\\Models\\MasterCategory', 'model_id' => $id,
            'description' => 'Gita Samudra deleted MasterCategory', 'properties' => []]);
        // baris lain dengan id sama di tabel lain tidak ikut
        ActivityLog::create(['action' => 'updated', 'model_type' => 'Bangsamu\\Master\\Models\\MasterUom', 'model_id' => $id,
            'description' => 'Gita Samudra updated MasterUom', 'properties' => []]);

        $logs = collect(app(ActivityLogService::class)->forSlug('category', $id)->items());

        $this->assertSame(['deleted', 'updated'], $logs->pluck('action')->all());
        $this->assertSame(['Category', 'Category'], $logs->pluck('sort_description')->all());
    }

    public function test_model_types_semua_nama_model_modul(): void
    {
        $this->assertSame([
            'Bangsamu\\Master\\Models\\ItemCode',
            'Bangsamu\\Master\\Models\\MasterItemCode',
            'App\\Models\\ItemCode',
            'App\\Models\\MasterItemCode',
        ], ActivityLogService::modelTypes('item-code'));
    }

    public function test_decorate_hanya_membuang_awalan_master_nama_model(): void
    {
        $service = app(ActivityLogService::class);

        $this->assertSame('a new Uom (via import)', $service->decorate(new ActivityLog(['action' => 'created', 'description' => 'Bagas created a new MasterUom (via import)']))->sort_description);
        $this->assertSame('Category (via import: Master Data SAP)', $service->decorate(new ActivityLog(['action' => 'updated', 'description' => 'Bagas updated Category (via import: Master Data SAP)']))->sort_description);
    }
}
