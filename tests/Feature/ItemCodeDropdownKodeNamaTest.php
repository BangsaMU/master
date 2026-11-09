<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Dropdown UoM/PCA/Category/Item Group di form Item Code tampil "kode | nama" dan bisa dicari dari kode atau nama.
 * Dulu hanya nama (set[text]=uom_name, search[uom_name]), sehingga kode tidak terlihat dan tidak bisa dicari.
 * Tab ke dropdown langsung membuka kotak cari (partial select2-keyboard).
 */
class ItemCodeDropdownKodeNamaTest extends TestCase
{
    use CreatesMasterTables;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->actingAs(User::create(['name' => 'Bagas Setyo', 'email' => 'bagas@meindo.test']));
    }

    public function test_api_byparams_mengembalikan_kode_pipa_nama_dan_mencari_di_kode_atau_nama(): void
    {
        DB::table('master_uom')->insert([
            ['uom_code' => 'KG', 'uom_name' => 'Kilogram'],
            ['uom_code' => 'LTR', 'uom_name' => 'Liter'],
            ['uom_code' => 'PCS', 'uom_name' => 'Pieces'],
        ]);

        // URL sama persis dengan form (pemisah " | " dikodekan di key set[text])
        $url = '/api/getmaster_uombyparams?set[id]=id&set[text][%20%7C%20]=uom_code&set[text][]=uom_name';

        $semua = collect($this->getJson($url)->assertOk()->json())->pluck('text')->sort()->values()->all();
        $this->assertSame(['KG | Kilogram', 'LTR | Liter', 'PCS | Pieces'], $semua);

        // kata dicari di kode ATAU nama, seperti data() select2 di form
        $cari = fn (string $kata) => collect($this->getJson($url.'&'.http_build_query(['search' => [
            'uom_code' => [$kata],
            'uom_name' => [$kata],
        ]]))->assertOk()->json())->pluck('text')->sort()->values()->all();

        $this->assertSame(['LTR | Liter'], $cari('ltr'));
        $this->assertSame(['PCS | Pieces'], $cari('piece'));
    }

    /**
     * Pilih Item Group di form Item Code memuat isian atribut lewat set[field][]=item_group_attributes tanpa set[text].
     * v3.9.0: SchemaColumns::hasColumn($tabel, null) = TypeError 500, isian atribut hilang dan Save mengosongkan nilainya.
     */
    public function test_atribut_item_group_tanpa_set_text_tetap_ok(): void
    {
        $group = DB::table('master_item_group')->insertGetId([
            'item_group_code' => 'ABR',
            'item_group_name' => 'ABRASSIVE',
            'item_group_attributes' => '{"size":null,"grit":null}',
        ]);

        // URL sama persis dengan ajax select2:select di form Item Code
        $response = $this->getJson('/api/getmaster_item_groupbyparams?set[field][]=item_group_attributes&where[id]='.$group)
            ->assertOk()
            ->json();

        $this->assertSame($group, (int) $response[0]['id']);
        $this->assertSame('{"size":null,"grit":null}', $response[0]['item_group_attributes']);
        $this->assertArrayNotHasKey('text', $response[0]);
    }

    public function test_form_create_memakai_kode_nama_dan_skrip_keyboard(): void
    {
        $html = $this->get(route('master.item-code.create'))->assertOk()->getContent();

        foreach (['uom', 'pca', 'category', 'item_group'] as $ref) {
            $this->assertStringContainsString("set[text][%20%7C%20]={$ref}_code&set[text][]={$ref}_name", $html);
            $this->assertStringContainsString("['{$ref}_code', '{$ref}_name']", $html);
        }
        $this->assertStringNotContainsString('set[text]=uom_name', $html);

        // Tab membuka select2, dimuat sekali per halaman
        $this->assertSame(1, substr_count($html, 'window.masterSelect2Keyboard = true'));
    }

    public function test_form_edit_menampilkan_kode_pipa_nama_untuk_nilai_tersimpan(): void
    {
        $uom = DB::table('master_uom')->insertGetId(['uom_code' => 'KG', 'uom_name' => 'Kilogram']);
        $pca = DB::table('master_pca')->insertGetId(['pca_code' => 'PCA-6300.008', 'pca_name' => 'Consumable']);
        $group = DB::table('master_item_group')->insertGetId(['item_group_code' => 'ABR', 'item_group_name' => 'ABRASSIVE']);
        $id = DB::table('master_item_code')->insertGetId([
            'item_code' => 'ABR-0001',
            'item_name' => 'Cutting wheel',
            'uom_id' => $uom,
            'pca_id' => $pca,
            'category_id' => null,
            'group_id' => $group,
            'attributes' => '{}',
        ]);

        $html = $this->get(route('master.item-code.edit', $id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$uom.'" selected>KG \| Kilogram<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$pca.'" selected>PCA-6300\.008 \| Consumable<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$group.'" selected>ABR \| ABRASSIVE<\/option>/', $html);
        // tanpa category: tidak ada opsi terpilih di dropdown category
        $this->assertDoesNotMatchRegularExpression('/name="category_id" id="category">\s*<option value=""><\/option>\s*<option/', $html);
    }
}
