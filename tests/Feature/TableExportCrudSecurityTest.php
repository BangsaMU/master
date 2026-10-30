<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Exports\DataExport;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * master/get{table}/export dulu membaca tabel apa pun dari db_master (termasuk password/api_token master_user) untuk user
 * login mana pun; crud/{table} membaca/menulis tabel apa pun (insert model_has_roles = jadi admin) dan constructor-nya
 * menjalankan query information_schema dengan nama tabel dari URL sebelum middleware auth (injeksi SQL tanpa login).
 */
class TableExportCrudSecurityTest extends TestCase
{
    use CreatesMasterTables {
        defineEnvironment as baseEnvironment;
    }

    private string $masterDb;

    /** @var list<string> izin user yang sedang login */
    private array $abilities = [];

    protected function defineEnvironment($app)
    {
        $this->baseEnvironment($app);

        $this->masterDb = sys_get_temp_dir().'/bangsamu-master-export-db-'.getmypid().'.sqlite';
        File::put($this->masterDb, '');
        $app['config']->set('database.connections.db_master', [
            'driver' => 'sqlite',
            'database' => $this->masterDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn ($user, string $ability) => in_array($ability, $this->abilities, true) ? true : null);
        Schema::create('dashboard_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::connection('db_master')->create('master_user', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('api_token')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::connection('db_master')->create('master_uom', function (Blueprint $table) {
            $table->id();
            $table->string('uom_code');
            $table->string('uom_name');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::connection('db_master')->table('master_user')->insert([
            'name' => 'Budi', 'email' => 'budi@meindo.test', 'password' => 'hash-rahasia', 'api_token' => 'token-rahasia', 'remember_token' => 'ingat-rahasia',
        ]);
        DB::connection('db_master')->table('master_uom')->insert(['uom_code' => 'PCS', 'uom_name' => 'Pieces']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::delete($this->masterDb);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function actingWith(array $abilities): void
    {
        $this->abilities = $abilities;
        $this->actingAs(User::create(['name' => 'Penguji', 'email' => uniqid('export', true).'@meindo.test']));
    }

    public function test_tabel_di_luar_whitelist_404_termasuk_admin(): void
    {
        Excel::fake();

        $this->get('/master/getmaster_user/export')->assertRedirect('/login');

        $this->actingWith(['is_admin']);
        $this->get('/master/getmaster_user/export')->assertNotFound();
        $this->get('/master/getGO-master_user/export')->assertNotFound();
        $this->get('/master/get'.rawurlencode("master_uom' OR '1'='1").'/export')->assertNotFound();
    }

    public function test_tabel_whitelist_wajib_admin_atau_izin_baca(): void
    {
        Excel::fake();

        $this->actingWith(['read_category']);
        $this->get('/master/getmaster_uom/export')->assertForbidden();

        $this->actingWith(['read_uom']);
        $this->get('/master/getmaster_uom/export')->assertOk();

        $this->actingWith(['is_admin']);
        $this->get('/master/getmaster_uom/export')->assertOk();

        config(['MasterCrudConfig.EXPORT_TABLES' => ['master_uom' => ['read_master_data']]]);
        $this->actingWith(['read_master_data']);
        $this->get('/master/getmaster_uom/export')->assertOk();
        Excel::assertDownloaded('Master Uom.xlsx');
    }

    public function test_kolom_rahasia_tidak_ikut_walau_tabel_dibuka_app(): void
    {
        Excel::fake();
        config(['MasterCrudConfig.EXPORT_TABLES' => ['master_user' => ['read_user']], 'MasterCrudConfig.SELECT2_HIDDEN_COLUMNS' => ['email']]);

        $this->actingWith(['read_user']);
        $this->get('/master/getmaster_user/export')->assertOk();

        Excel::assertDownloaded('Master User.xlsx', function (DataExport $export): bool {
            $this->assertSame(['name'], $export->headings());
            $this->assertSame([['name' => 'Budi']], $export->collection()->map(fn ($row) => (array) $row)->all());

            return true;
        });
    }

    public function test_crud_tamu_tidak_menjalankan_query_dari_nama_tabel(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->get('/crud/'.rawurlencode("zzprobe' OR '1'='1"))->assertRedirect('/login');

        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'zzprobe'))));
    }

    public function test_crud_mati_bila_tabel_tidak_di_whitelist_dan_hanya_admin(): void
    {
        $this->actingWith(['is_admin']);
        $this->get('/crud/users')->assertNotFound();
        $this->get('/crud/users/1')->assertNotFound();
        $this->post('/crud/users', ['name' => 'Penyusup', 'email' => 'penyusup@meindo.test'])->assertNotFound();
        $this->assertFalse(DB::table('users')->where('email', 'penyusup@meindo.test')->exists());

        config(['MasterCrudConfig.CRUD_TABLES' => ['master_uom'], 'test.denied_permissions' => ['is_admin']]);
        $this->post('/crud/master_uom', ['uom_code' => 'ZZ', 'uom_name' => 'Penyusup'])->assertForbidden();
        $this->assertFalse(DB::table('master_uom')->where('uom_code', 'ZZ')->exists());
    }
}
