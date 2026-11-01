<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterLaunchToken;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * master:scope-upgrade (pemilik app_code + company_id, index unik diperluas), master:permissions (nama per tabel +
 * gabung alias lama), master:scope-doctor (dibaca bin/master-scope-setup), dan filter setting baris scope dengan placeholder.
 */
class MasterScopeCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('master_category', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 55)->unique();
            $table->string('category_name', 110)->unique();
            $table->string('app_code', 10)->default('APP03');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('master_department', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 15);
            $table->string('department_name', 100);
            $table->string('app_code', 10)->default('APP03');
            $table->timestamps();
            $table->softDeletes();
            // index ganda seperti meindo_master
            $table->unique(['department_code', 'app_code'], 'uq_dept_code');
            $table->unique(['department_code', 'app_code'], 'uq_dept_code_app');
        });
        Schema::create('master_location', function (Blueprint $table) {
            $table->id();
            $table->string('loc_code', 15)->nullable();
            $table->string('loc_name', 50)->nullable();
            $table->string('group_type')->default('office');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['loc_code', 'group_type'], 'master_location_loc_code_group_type_deleted_unique');
        });
    }

    /** @return list<string> */
    private function uniqueSets(string $table): array
    {
        $sets = array_map(fn ($columns) => implode(',', $columns), app(MasterSchemaUpgrader::class)->uniqueIndexes($table));
        sort($sets);

        return $sets;
    }

    public function test_dry_run_tidak_mengubah_skema(): void
    {
        $this->artisan('master:scope-upgrade', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] master_category: tambah company_id')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('master_category', 'company_id'));
    }

    public function test_tabel_ber_app_code_diberi_company_id_dan_unik_diperluas(): void
    {
        $this->artisan('master:scope-upgrade')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('master_category', 'company_id'));
        $this->assertSame(['category_code,app_code,company_id', 'category_name,app_code,company_id'], $this->uniqueSets('master_category'));
        // index ganda department dirapikan jadi satu
        $this->assertSame(['department_code,app_code,company_id'], $this->uniqueSets('master_department'));
        // tabel global tidak disentuh tanpa --table
        $this->assertFalse(Schema::hasColumn('master_location', 'app_code'));

        // kode sama boleh di company lain dalam aplikasi yang sama
        DB::table('master_category')->insert([
            ['category_code' => 'C1', 'category_name' => 'A', 'app_code' => 'APP32', 'company_id' => 647],
            ['category_code' => 'C1', 'category_name' => 'A', 'app_code' => 'APP32', 'company_id' => 648],
        ]);
        $this->assertSame(2, DB::table('master_category')->where('category_code', 'C1')->count());

        // dijalankan ulang: tidak ada perubahan
        $this->artisan('master:scope-upgrade')->expectsOutputToContain('master_category: sudah sesuai')->assertSuccessful();
    }

    public function test_upgrade_department_otomatis_tidak_mengembalikan_unik_lama(): void
    {
        $this->artisan('master:scope-upgrade')->assertSuccessful();
        MasterSchemaUpgrader::flush();

        app(MasterSchemaUpgrader::class)->ensure('master_department');

        $this->assertSame(['department_code,app_code,company_id'], $this->uniqueSets('master_department'));
    }

    public function test_tabel_global_dinaikkan_dengan_table(): void
    {
        $this->artisan('master:scope-upgrade', ['--table' => ['master_location']])->assertSuccessful();

        $this->assertSame(['loc_code,group_type,app_code,company_id'], $this->uniqueSets('master_location'));
        $id = DB::table('master_location')->insertGetId(['loc_code' => 'JKT', 'loc_name' => 'Jakarta']);
        $this->assertSame('APP03', DB::table('master_location')->where('id', $id)->value('app_code'));
        // kode lokasi JKT boleh sama di aplikasi lain
        DB::table('master_location')->insert(['loc_code' => 'JKT', 'loc_name' => 'Jakarta SPB', 'app_code' => 'APP32']);
        $this->assertSame(2, DB::table('master_location')->where('loc_code', 'JKT')->count());
    }

    public function test_tabel_aturan_khusus_ditolak(): void
    {
        $this->artisan('master:scope-upgrade', ['--table' => ['master_employee']])
            ->expectsOutputToContain('aturan khusus')
            ->assertFailed();
    }

    private function createPermissionTables(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
    }

    public function test_permissions_dibuat_dan_alias_lama_digabung(): void
    {
        $this->createPermissionTables();
        $readItem = DB::table('permissions')->insertGetId(['name' => 'read_item', 'guard_name' => 'web']);
        $updateItem = DB::table('permissions')->insertGetId(['name' => 'update_item', 'guard_name' => 'web']);
        $updateItemCode = DB::table('permissions')->insertGetId(['name' => 'update_item_code', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert([
            ['permission_id' => $readItem, 'role_id' => 1],
            ['permission_id' => $updateItem, 'role_id' => 1],
            ['permission_id' => $updateItemCode, 'role_id' => 2],
        ]);

        $this->artisan('master:permissions', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(3, DB::table('permissions')->count());

        $this->artisan('master:permissions')->expectsOutputToContain('read_item -> read_item_code (ganti nama)')->assertSuccessful();

        $names = DB::table('permissions')->pluck('id', 'name');
        $this->assertFalse($names->has('read_item'));
        $this->assertFalse($names->has('update_item'));
        $this->assertTrue($names->has('delete_project_detail'));
        // alias tanpa nama baru diganti nama (id tetap); keduanya ada: id lebih kecil (dirujuk seeder) dipertahankan
        $this->assertSame($readItem, $names['read_item_code']);
        $this->assertSame($updateItem, $names['update_item_code']);
        $this->assertFalse(DB::table('permissions')->where('id', $updateItemCode)->exists());
        $role1 = DB::table('role_has_permissions')->where('role_id', 1)->pluck('permission_id')->all();
        $this->assertEqualsCanonicalizing([$readItem, $updateItem], $role1);
        $this->assertSame([$updateItem], DB::table('role_has_permissions')->where('role_id', 2)->pluck('permission_id')->all());

        // idempoten
        $count = DB::table('permissions')->count();
        $this->artisan('master:permissions')->assertSuccessful();
        $this->assertSame($count, DB::table('permissions')->count());
    }

    public function test_permissions_keep_alias_mempertahankan_nama_lama(): void
    {
        $this->createPermissionTables();
        $alias = DB::table('permissions')->insertGetId(['name' => 'create_itemgroup', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['permission_id' => $alias, 'role_id' => 1]);

        $this->artisan('master:permissions', ['--keep-alias' => true])
            ->expectsOutputToContain('create_itemgroup -> create_item_group (alias dipertahankan)')
            ->assertSuccessful();

        $names = DB::table('permissions')->pluck('id', 'name');
        $this->assertTrue($names->has('create_itemgroup'));
        $this->assertEqualsCanonicalizing([$alias, $names['create_item_group']], DB::table('role_has_permissions')->where('role_id', 1)->pluck('permission_id')->all());

        // tanpa --keep-alias kemudian: digabung ke id lama (alias), nama baru dipakai
        $this->artisan('master:permissions')->assertSuccessful();
        $this->assertSame($alias, DB::table('permissions')->where('name', 'create_item_group')->value('id'));
        $this->assertFalse(DB::table('permissions')->where('name', 'create_itemgroup')->exists());
        $this->assertSame([$alias], DB::table('role_has_permissions')->where('role_id', 1)->pluck('permission_id')->all());
    }

    public function test_filter_setting_baris_scope_dengan_placeholder(): void
    {
        $this->artisan('master:scope-upgrade')->assertSuccessful();
        config(['SsoConfig.main.APP_CODE' => 'APP32', 'MasterCrudConfig.SELECT2_SETTING_FILTER' => true]);
        $user = User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']);
        DB::table('user_details')->insert(['user_email' => $user->email, 'field_key' => 'company_id', 'field_value' => '647']);
        foreach ([['S1', 'APP03', 0], ['M1', 'APP32', 647], ['A1', 'APP32', 0], ['E1', 'APP32', 648], ['W1', 'APP09', 647]] as [$code, $app, $company]) {
            DB::table('master_department')->insert(['department_code' => $code, 'department_name' => 'Dept '.$code, 'app_code' => $app, 'company_id' => $company]);
        }
        DB::table('setting')->insert([
            ['category' => 'master_department', 'name' => 'scope.bersama', 'value' => '{"app_code":["{GLOBAL_APP_CODE}"]}'],
            ['category' => 'master_department', 'name' => 'scope.spb', 'value' => '{"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}'],
        ]);

        $codes = collect($this->actingAs($user)->getJson('/api/getmaster_departmentbyparams?'.http_build_query(['set' => ['text' => 'department_name', 'field' => ['department_code']]]))
            ->assertOk()->json())->pluck('department_code')->sort()->values()->all();

        $this->assertSame(['A1', 'M1', 'S1'], $codes);
    }

    /** @return array<string, string> */
    private function doctorEnv(): array
    {
        Artisan::call('master:scope-doctor', ['--format' => 'env']);
        $report = [];
        foreach (preg_split('/\R/', trim(Artisan::output())) as $line) {
            [$key, $value] = explode('=', $line, 2);
            $report[$key] = $value;
        }

        return $report;
    }

    public function test_doctor_melaporkan_tabel_kunci_dan_filter_setting(): void
    {
        config(['SsoConfig.main.APP_CODE' => 'APP32', 'MasterCrudConfig.MASTER_EDIT_URL' => 'https://master.test']);
        DB::table('setting')->insert([
            // format lama: belum membatasi company
            ['category' => 'master_category', 'name' => 'app_code', 'value' => 'APP03,APP09,APP32'],
            // baris scope + baris lama app_code digabung OR: baris lama membuka lagi data yang dibatasi
            ['category' => 'master_department', 'name' => 'scope.spb', 'value' => '{"app_code":["{APP_CODE}"],"company_id":["{USER_COMPANY}",0]}'],
            ['category' => 'master_department', 'name' => 'app_code', 'value' => 'APP32'],
        ]);

        $before = $this->doctorEnv();
        $this->assertSame('client', $before['role']);
        $this->assertSame('APP32', $before['app_code']);
        $this->assertSame('master_category,master_department', $before['scope_pending']);
        $this->assertSame('master_location', $before['scope_global']);
        $this->assertSame('missing', $before['launch_key']);
        $this->assertSame('https://master.test', $before['edit_url']);
        // department: baris scope diabaikan (company_id belum ada); saran baris scope baru muncul setelah company_id ada
        $this->assertSame('1', $before['filter_warnings']);

        $this->artisan('master:scope-upgrade')->assertSuccessful();
        config(['MasterCrudConfig.LAUNCH_PRIVATE_KEY' => MasterLaunchToken::generateKeyPair()['secret']]);

        $after = $this->doctorEnv();
        $this->assertSame('', $after['scope_pending']);
        $this->assertSame('ok', $after['launch_key']);
        // department: baris lama app_code digabung OR dengan baris scope; category: saran ganti ke baris scope
        $this->assertSame('2', $after['filter_warnings']);

        $this->artisan('master:scope-doctor')
            ->expectsOutputToContain('scope.bersama = {"app_code":["APP03","APP09"]}')
            ->expectsOutputToContain('digabung OR dengan baris scope')
            ->assertSuccessful();
    }

    public function test_launch_key_format_env_untuk_skrip(): void
    {
        Artisan::call('master:launch-key', ['--format' => 'env']);
        $output = Artisan::output();
        preg_match('/^MASTER_LAUNCH_PRIVATE_KEY=(\S+)$/m', $output, $secret);
        preg_match('/^MASTER_LAUNCH_PUBLIC_KEY=(\S+)$/m', $output, $public);

        $this->assertNotEmpty($secret[1] ?? null);
        $this->assertSame(MasterLaunchToken::publicKeyFromSecret($secret[1]), $public[1] ?? null);
    }
}
