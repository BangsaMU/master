<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * master:scope-upgrade (pemilik app_code + company_id, index unik diperluas), master:permissions (nama per tabel +
 * gabung alias lama), dan filter setting baris scope dengan placeholder.
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

    public function test_permissions_dibuat_dan_alias_lama_digabung(): void
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

        $this->artisan('master:permissions')->expectsOutputToContain('read_item -> read_item_code')->assertSuccessful();

        $names = DB::table('permissions')->pluck('id', 'name');
        $this->assertFalse($names->has('read_item'));
        $this->assertFalse($names->has('update_item'));
        $this->assertTrue($names->has('delete_project_detail'));
        $this->assertSame($updateItemCode, $names['update_item_code']); // id lama dipakai, tidak dibuat ulang
        $role1 = DB::table('role_has_permissions')->where('role_id', 1)->pluck('permission_id')->all();
        $this->assertEqualsCanonicalizing([$names['read_item_code'], $updateItemCode], $role1);

        // idempoten
        $count = DB::table('permissions')->count();
        $this->artisan('master:permissions')->assertSuccessful();
        $this->assertSame($count, DB::table('permissions')->count());
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
}
