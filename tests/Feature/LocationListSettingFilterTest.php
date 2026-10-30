<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * List master/location ikut filter Setting/DashboardSettings kolom apa pun (DynamicFilterable), sama dengan dropdown
 * api/getmaster_locationbyparams. Dulu hanya setting group_type yang dibaca, jadi setting loc_code diabaikan.
 */
class LocationListSettingFilterTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

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

        foreach ([['TCC', 'warehouse'], ['BPN', 'office'], ['JKT', 'site'], ['SBY', 'warehouse'], ['OLD', 'warehouse']] as [$code, $groupType]) {
            DB::table('master_location')->insert([
                'loc_code' => $code,
                'loc_name' => 'Lokasi '.$code,
                'group_type' => $groupType,
                'deleted_at' => $code === 'OLD' ? now() : null,
            ]);
        }

        $this->actingAs(User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']));
    }

    /** @return array{codes: list<string>, total: int} */
    private function listLocations(): array
    {
        $json = $this->getJson('/master/location', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();

        return [
            'codes' => collect($json['data'])->pluck('loc_code')->sort()->values()->all(),
            'total' => $json['recordsTotal'],
        ];
    }

    public function test_setting_loc_code_membatasi_list(): void
    {
        DB::table('setting')->insert(['category' => 'master_location', 'name' => 'loc_code', 'value' => 'TCC, BPN,XYZ']);

        $this->assertSame(['codes' => ['BPN', 'TCC'], 'total' => 2], $this->listLocations());
    }

    public function test_setting_group_type_lama_tetap_berlaku_dan_kolom_digabung_or(): void
    {
        DB::table('setting')->insert(['category' => 'master_location', 'name' => 'group_type', 'value' => 'site']);
        $this->assertSame(['codes' => ['JKT'], 'total' => 1], $this->listLocations());

        DB::table('setting')->insert(['category' => 'master_location', 'name' => 'loc_code', 'value' => 'TCC']);
        $this->assertSame(['codes' => ['JKT', 'TCC'], 'total' => 2], $this->listLocations());
    }

    public function test_fallback_dashboard_settings_dan_tanpa_setting_semua_tampil(): void
    {
        $this->assertSame(['codes' => ['BPN', 'JKT', 'SBY', 'TCC'], 'total' => 4], $this->listLocations());

        DB::table('dashboard_settings')->insert([
            'key' => 'loc_code', 'value' => 'SBY', 'group' => 'master_location', 'label' => 'Filter lokasi',
        ]);
        $this->assertSame(['codes' => ['SBY'], 'total' => 1], $this->listLocations());
    }
}
