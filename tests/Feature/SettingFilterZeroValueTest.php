<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Exports\DataExport;
use Bangsamu\Master\Exports\Master\ItemCodeExport;
use Bangsamu\Master\Services\MasterFilterGroups;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Baris Setting format kolom dengan nilai 0 (mis. `master_item_code` / `company_id` = `0` atau `{USER_COMPANY},0`).
 * Sebelum v3.19.1 array_filter() tanpa callback membuang "0": baris `company_id = 0` diabaikan seluruhnya, sehingga
 * dengan baris app_code yang tidak cocok daftar Item Code kosong, dan item bersama (company_id 0) tidak pernah ikut.
 * Angka 0 = nilai company_id 0 (baris bersama), bukan "semua company".
 */
class SettingFilterZeroValueTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDashboardSettings();
        Route::get('/login', fn () => 'login page')->name('login');

        foreach ([['SHARED-0001', 0], ['ME-0001', 1], ['MEB-0001', 647]] as [$code, $companyId]) {
            DB::table('master_item_code')->insert([
                'item_code' => $code, 'item_name' => 'Item '.$code, 'app_code' => 'APP03', 'company_id' => $companyId,
            ]);
        }

        config(['test.denied_permissions' => ['is_admin']]);
        $this->actingAs(User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']));
    }

    /** @param  array<string, string>  $rows  name => value baris setting master_item_code */
    private function setting(array $rows): void
    {
        foreach ($rows as $name => $value) {
            DB::table('setting')->insert(['category' => 'master_item_code', 'name' => $name, 'value' => $value]);
        }
    }

    /** @return list<string> */
    private function listedCodes(): array
    {
        $json = $this->getJson('/master/item-code?draw=1&start=0&length=50', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('data');

        return collect($json)->pluck('item_code')->sort()->values()->all();
    }

    public function test_nilai_nol_tetap_nilai_hanya_teks_kosong_yang_dibuang(): void
    {
        $this->assertSame(['0'], MasterFilterGroups::settingValues('0'));
        $this->assertSame(['{USER_COMPANY}', '0', '1'], MasterFilterGroups::settingValues(' {USER_COMPANY}, 0 ,, 1 ,'));
        $this->assertSame([], MasterFilterGroups::settingValues(''));
        $this->assertSame([], MasterFilterGroups::settingValues(null));
    }

    public function test_company_id_nol_menampilkan_item_bersama_bukan_daftar_kosong(): void
    {
        // kasus spb_v2: baris app_code sengaja tidak cocok, company_id 0 dulu diabaikan = daftar kosong
        $this->setting(['app_code' => 'APP09,APP32,APP03 PAKEK COMPANY DULU', 'company_id' => '0']);

        $this->assertSame(['SHARED-0001'], $this->listedCodes());
    }

    public function test_company_id_nol_ditambah_company_lain_menampilkan_keduanya(): void
    {
        $this->setting(['company_id' => '1, 0']);

        $this->assertSame(['ME-0001', 'SHARED-0001'], $this->listedCodes());
    }

    public function test_tanpa_baris_setting_semua_company_tampil(): void
    {
        $this->assertSame(['ME-0001', 'MEB-0001', 'SHARED-0001'], $this->listedCodes());
    }

    public function test_export_item_code_dan_export_generik_ikut_membaca_nilai_nol(): void
    {
        $this->setting(['company_id' => '0,1']);

        $codes = (new ItemCodeExport)->collection()->pluck('item_code')->sort()->values()->all();
        $this->assertSame(['ME-0001', 'SHARED-0001'], $codes);

        $export = new class extends DataExport
        {
            public function filtersOn(string $connection): array
            {
                return $this->getDynamicFilters($connection);
            }
        };
        $export->setTable('master_item_code');

        $this->assertSame([['column' => 'company_id', 'values' => ['0', '1']]], $export->filtersOn(config('database.default')));
    }
}
