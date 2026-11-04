<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Daftar master/item-code: cari per kolom (input di bawah judul kolom, columns[i][search][value]) dan cari global.
 * Dulu: cari global tidak menyentuh nama UoM/PCA/Category/Item Group, % dan _ ketikan user menjadi wildcard, jumlah hasil
 * dihitung dengan mengambil semua baris ke PHP, dan urut kolom Attributes memakai indeks array yang salah (error SQL).
 */
class ItemCodeColumnSearchTest extends TestCase
{
    use CreatesMasterTables;

    private const KOLOM = ['No', 'item_code', 'description', 'uom', 'pca', 'category', 'item_group', 'attributes', 'app_code', 'company_id', 'action'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDashboardSettings();
        Route::get('/login', fn () => 'login page')->name('login');

        $meter = DB::table('master_uom')->insertGetId(['uom_code' => 'M', 'uom_name' => 'Meter']);
        $pcs = DB::table('master_uom')->insertGetId(['uom_code' => 'PCS', 'uom_name' => 'Pieces']);
        $listrik = DB::table('master_category')->insertGetId(['category_code' => 'ELC', 'category_name' => 'Electrical']);
        $k3 = DB::table('master_category')->insertGetId(['category_code' => 'SFT', 'category_name' => 'Safety']);
        $kabel = DB::table('master_item_group')->insertGetId(['item_group_code' => 'CBL', 'item_group_name' => 'Cable']);
        $apd = DB::table('master_item_group')->insertGetId(['item_group_code' => 'PPE', 'item_group_name' => 'Personal Protective']);

        foreach ([
            ['ELC-CBL-0001', 'Cable NYY 4x16mm Supreme', $meter, $listrik, $kabel, '{"size_1":"4x16"}', null],
            ['ELC-CBL-0002', 'Cable NYM 3x2.5mm', $meter, $listrik, $kabel, null, null],
            ['SFTY-PPE-0001', 'Safety Helmet Red', $pcs, $k3, $apd, null, null],
            ['SFTY_PPE_0002', 'Glove 100% Cotton', $pcs, $k3, $apd, null, null],
            ['SFTY-PPE-0003', 'Helmet Lama', $pcs, $k3, $apd, null, '2026-11-01 08:00:00'],
        ] as [$code, $name, $uom, $category, $group, $attributes, $deletedAt]) {
            DB::table('master_item_code')->insert([
                'item_code' => $code, 'item_name' => $name, 'uom_id' => $uom, 'category_id' => $category,
                'group_id' => $group, 'attributes' => $attributes, 'deleted_at' => $deletedAt,
            ]);
        }
    }

    private function login(bool $admin = false): void
    {
        config(['test.denied_permissions' => $admin ? [] : ['is_admin']]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    /** @return array<string, mixed> respon DataTables */
    private function daftar(array $cariKolom = [], string $cariGlobal = '', array $order = [0, 'asc'], int $length = 10): array
    {
        $columns = [];
        foreach (self::KOLOM as $name) {
            $columns[] = ['data' => $name, 'name' => $name, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => $cariKolom[$name] ?? '', 'regex' => 'false']];
        }
        $query = http_build_query([
            'draw' => 1, 'start' => 0, 'length' => $length, 'columns' => $columns,
            'order' => [['column' => $order[0], 'dir' => $order[1]]], 'search' => ['value' => $cariGlobal, 'regex' => 'false'],
        ]);

        return $this->getJson('/master/item-code?'.$query, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
    }

    /** @return list<string> */
    private function kode(array $json): array
    {
        return array_column($json['data'], 'item_code');
    }

    public function test_cari_per_kolom_item_code_dan_jumlah_hasil(): void
    {
        $this->login();

        $json = $this->daftar(['item_code' => 'cbl']);

        $this->assertSame(['ELC-CBL-0001', 'ELC-CBL-0002'], $this->kode($json));
        $this->assertSame(4, $json['recordsTotal']); // baris terhapus tidak dihitung untuk non-admin
        $this->assertSame(2, $json['recordsFiltered']);
    }

    public function test_cari_kolom_referensi_dan_antar_kolom_and(): void
    {
        $this->login();

        $this->assertSame(['SFTY-PPE-0001', 'SFTY_PPE_0002'], $this->kode($this->daftar(['uom' => 'piec'])));
        $this->assertSame(['SFTY-PPE-0001', 'SFTY_PPE_0002'], $this->kode($this->daftar(['item_group' => 'protective'])));
        $this->assertSame(['SFTY-PPE-0001'], $this->kode($this->daftar(['uom' => 'pieces', 'description' => 'helmet'])));
        $this->assertSame([], $this->kode($this->daftar(['uom' => 'meter', 'description' => 'helmet'])));
    }

    public function test_beberapa_kata_dalam_satu_kotak_cari_semuanya_wajib_ada(): void
    {
        $this->login();

        $this->assertSame(['ELC-CBL-0001'], $this->kode($this->daftar(['description' => 'supreme  cable'])));
        $this->assertSame(['ELC-CBL-0001'], $this->kode($this->daftar(['attributes' => '4x16'])));
    }

    public function test_cari_global_ikut_nama_referensi(): void
    {
        $this->login();

        // dulu kolom gabungan (UoM/Category/Item Group) dilewati cari global
        $json = $this->daftar([], 'electrical');
        $this->assertSame(['ELC-CBL-0001', 'ELC-CBL-0002'], $this->kode($json));
        $this->assertSame(2, $json['recordsFiltered']);

        // tiap kata boleh ada di kolom berbeda
        $this->assertSame(['ELC-CBL-0002'], $this->kode($this->daftar([], 'meter nym')));
    }

    public function test_persen_dan_garis_bawah_dibaca_sebagai_huruf_biasa(): void
    {
        $this->login();

        $this->assertSame(['SFTY_PPE_0002'], $this->kode($this->daftar(['item_code' => 'y_p'])));
        $this->assertSame(['SFTY_PPE_0002'], $this->kode($this->daftar(['description' => '100%'])));
        $this->assertSame([], $this->kode($this->daftar(['description' => '%'.'Helmet'.'%Red_'])));
        $this->assertSame([], $this->kode($this->daftar(['item_code' => '!'])));
    }

    public function test_nama_kolom_asing_diabaikan_dan_urut_kolom_attributes_tidak_error(): void
    {
        $this->login();

        $columns = [['data' => "mic.id) OR 1=1 -- ", 'search' => ['value' => 'x']], ['data' => 'app_code', 'search' => ['value' => 'zzz']]];
        $json = $this->getJson('/master/item-code?'.http_build_query(['columns' => $columns, 'order' => [['column' => 0, 'dir' => 'asc; drop']]]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json();
        $this->assertSame(4, $json['recordsFiltered']);

        // indeks 7 = attributes; dulu terpetakan ke "action" (indeks $view_tabel lain) -> error SQL
        $this->assertCount(4, $this->daftar([], '', [7, 'desc'])['data']);
        $this->assertSame(['SFTY_PPE_0002', 'SFTY-PPE-0001', 'ELC-CBL-0002', 'ELC-CBL-0001'], $this->kode($this->daftar([], '', [3, 'desc'])));
    }

    public function test_admin_tetap_melihat_baris_terhapus_saat_mencari(): void
    {
        $this->login(admin: true);

        $json = $this->daftar(['description' => 'helmet']);

        $this->assertSame(['SFTY-PPE-0001', 'SFTY-PPE-0003'], $this->kode($json));
        $this->assertSame(['', 'master-row-trashed'], array_column($json['data'], 'DT_RowClass'));
        $this->assertSame(2, $json['recordsFiltered']);
    }

    public function test_hanya_satu_halaman_yang_diambil_dari_database(): void
    {
        $this->login();
        $selects = [];
        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'mic.item_name AS description')) {
                $selects[] = $query->sql;
            }
        });

        $json = $this->daftar(['category' => 'a'], '', [0, 'asc'], 2);

        $this->assertCount(2, $json['data']);
        $this->assertSame(4, $json['recordsFiltered']);
        $this->assertCount(1, $selects, 'jumlah hasil dihitung lewat COUNT(*), bukan mengambil semua baris');
        $this->assertStringContainsString('limit 2', strtolower($selects[0]));
        $this->assertStringNotContainsString('group by', strtolower($selects[0]));
    }

    public function test_metadata_kolom_dan_baris_input_cari_di_halaman(): void
    {
        $this->login();

        $filter = array_column($this->daftar()['columns'], 'filter', 'data');
        $this->assertSame(
            ['No' => false, 'item_code' => true, 'description' => true, 'uom' => true, 'pca' => true, 'category' => true,
                'item_group' => true, 'attributes' => true, 'app_code' => false, 'company_id' => false, 'action' => false],
            $filter
        );

        $html = $this->get('/master/item-code')->assertOk()->getContent();
        $this->assertStringContainsString('<tr class="cari-kolom"></tr>', $html);
        $this->assertStringContainsString('var columnSearch = true;', $html);
        $this->assertStringContainsString('orderCellsTop: true', $html);
    }
}
