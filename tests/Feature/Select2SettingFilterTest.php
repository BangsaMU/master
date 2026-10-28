<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dropdown api/getmaster_*byparams ikut filter Setting/DashboardSettings seperti list master (DynamicFilterable).
 */
class Select2SettingFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('master_department', function (Blueprint $table) {
            $table->id();
            $table->string('department_code');
            $table->string('department_name');
            $table->string('app_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        if (! Schema::hasTable('dashboard_settings')) {
            Schema::create('dashboard_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->string('type')->default('text');
                $table->string('label');
                $table->text('options')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        foreach ([['HRD', 'APP03'], ['IT', 'APP32'], ['QA', 'APP03'], ['OLD', 'APP03']] as [$code, $appCode]) {
            DB::table('master_department')->insert([
                'department_code' => $code,
                'department_name' => 'Dept '.$code,
                'app_code' => $appCode,
                'deleted_at' => $code === 'OLD' ? now() : null,
            ]);
        }

        config(['MasterCrudConfig.SELECT2_SETTING_FILTER' => true]);

        // api/get{tabel}byparams wajib login sejak v2.4.5
        $this->actingAs(User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']));
    }

    /** @return list<string> */
    private function departmentCodes(array $query = []): array
    {
        $response = $this->getJson('/api/getmaster_departmentbyparams?'.http_build_query(
            $query + ['set' => ['text' => 'department_name', 'field' => ['department_code']]]
        ))->assertOk();

        return collect($response->json())->pluck('department_code')->filter()->sort()->values()->all();
    }

    public function test_setting_kolom_digabung_or_seperti_list_master(): void
    {
        DB::table('setting')->insert([
            ['category' => 'master_department', 'name' => 'app_code', 'value' => 'APP32'],
            ['category' => 'master_department', 'name' => 'department_code', 'value' => 'HRD, XYZ'],
        ]);

        $this->assertSame(['HRD', 'IT'], $this->departmentCodes());
        // pencarian select2 tetap di dalam filter
        $this->assertSame(['IT'], $this->departmentCodes(['search' => ['department_name' => 'IT']]));
    }

    public function test_fallback_ke_dashboard_settings_bila_setting_kosong(): void
    {
        DB::table('dashboard_settings')->insert([
            'key' => 'department_code', 'value' => 'QA', 'group' => 'master_department', 'label' => 'Filter department',
        ]);

        $this->assertSame(['QA'], $this->departmentCodes());
    }

    public function test_tanpa_setting_atau_config_mati_semua_baris_tampil(): void
    {
        $this->assertSame(['HRD', 'IT', 'QA'], $this->departmentCodes());

        DB::table('setting')->insert(['category' => 'master_department', 'name' => 'app_code', 'value' => 'APP32']);
        config(['MasterCrudConfig.SELECT2_SETTING_FILTER' => false]);

        $this->assertSame(['HRD', 'IT', 'QA'], $this->departmentCodes());
    }
}
