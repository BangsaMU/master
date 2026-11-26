<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
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
 * Salah file di import Project / Projects Detail (v3.26.0, kejadian prod 2026-11-26): file Projects Detail
 * (`import-project-detail-MEB-....xlsx`) diunggah ke import Project, semua baris error "Project Name wajib diisi" tanpa
 * petunjuk. Kini seluruh file ditolak sebelum preview dengan petunjuk menu yang benar (MasterImportDefinition::fileError),
 * begitu juga sebaliknya.
 */
class ProjectImportWrongFileTest extends TestCase
{
    use CreatesMasterTables;

    private const PESAN_DETAIL = 'File ini sepertinya file import Projects Detail (ada kolom Project Code Client / Company Code, tidak ada kolom '
        .'Project Name): unggah lewat menu Projects Detail → Import. Untuk import Project pakai template import Project.';

    private const PESAN_PROJECT = 'File ini sepertinya file import Project (ada kolom Project Name, tidak ada kolom Project Code Client): unggah '
        .'lewat menu Project → Import. Untuk import Projects Detail pakai template import Projects Detail.';

    private const FILE_DETAIL = "Project Code,Project Code Client,Project Name Client,Company Code\n21329,73034,Klien MEB,MEB\n";

    private const FILE_PROJECT = "Project Code,Project Name,Project Type\n21400,Proyek Baru,E\n";

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
        $app['config']->set('MasterCrudConfig.MASTER_HUB', true);
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
            ['id' => 1, 'company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah', 'deleted_at' => null],
            ['id' => 647, 'company_code' => 'MEB', 'company_name' => 'PT Meitech Eka Bintan', 'deleted_at' => null],
        ]);
        $project = DB::table('master_project')->insertGetId(['project_code' => '21329', 'project_name' => 'Project 21329', 'internal_external' => 'E']);
        DB::table('master_project_detail')->insert(['project_id' => $project, 'company_id' => 1, 'project_code_client' => '21329', 'project_name_client' => 'Project 21329']);

        config(['test.denied_permissions' => [], 'test.allowed_permissions' => null]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private function upload(string $route, string $csv)
    {
        return $this->post($route, ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)]);
    }

    public function test_judul_kolom_dinormalisasi(): void
    {
        $this->assertSame('projectcodeclient', MasterImportDefinition::normalizeHeading('Project Code Client'));
        $this->assertSame('projectcodeclient', MasterImportDefinition::normalizeHeading('project_code_client'));
        $this->assertSame('companycode', MasterImportDefinition::normalizeHeading(' Company-Code '));
    }

    public function test_file_projects_detail_di_import_project_ditolak_dengan_petunjuk(): void
    {
        // route preview langsung dan route lama master/project/import
        foreach ([route('master.import.preview', 'project'), route('master.project.import')] as $route) {
            $this->upload($route, self::FILE_DETAIL)
                ->assertRedirect(route('master.project.index'))
                ->assertSessionHas('error_message', [self::PESAN_DETAIL]);
        }
        $this->assertSame(1, DB::table('master_project')->count());
    }

    public function test_file_project_di_import_projects_detail_ditolak_dengan_petunjuk(): void
    {
        foreach ([route('master.import.preview', 'project-detail'), route('master.project-detail.import')] as $route) {
            $this->upload($route, self::FILE_PROJECT)
                ->assertRedirect(route('master.project-detail.index'))
                ->assertSessionHas('error_message', [self::PESAN_PROJECT]);
        }
    }

    public function test_file_yang_benar_tetap_masuk_preview(): void
    {
        $this->upload(route('master.import.preview', 'project-detail'), self::FILE_DETAIL)
            ->assertRedirect()->assertSessionMissing('error_message');
        $this->upload(route('master.import.preview', 'project'), self::FILE_PROJECT)
            ->assertRedirect()->assertSessionMissing('error_message');
        // template import Project lama dengan kolom tambahan Company Code tetap diterima selama ada Project Name
        $this->upload(route('master.import.preview', 'project'), "Project Code,Project Name,Project Type,Company Code\n21401,Proyek Lain,E,ME\n")
            ->assertRedirect()->assertSessionMissing('error_message');
    }
}
