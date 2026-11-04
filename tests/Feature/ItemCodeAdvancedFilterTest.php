<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Support\AdvancedFilter;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Daftar master/item-code: modal Filter Lanjutan (parameter advanced_filter, JSON). Grup kondisi bertingkat maks 3,
 * tiap grup AND/OR (bawaan AND), kolom hanya dari whitelist SEARCH_COLUMNS, digabung AND dengan cari kolom/global.
 */
class ItemCodeAdvancedFilterTest extends TestCase
{
    use CreatesMasterTables;

    private const KOLOM = ['No', 'item_code', 'description', 'uom', 'pca', 'category', 'item_group', 'attributes', 'app_code', 'company_id', 'action'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDashboardSettings();
        Route::get('/login', fn () => 'login page')->name('login');

        $unit = DB::table('master_uom')->insertGetId(['uom_code' => 'UNT', 'uom_name' => 'Unit']);
        $pcs = DB::table('master_uom')->insertGetId(['uom_code' => 'PCS', 'uom_name' => 'Pieces']);
        $alat = DB::table('master_category')->insertGetId(['category_code' => 'EQPT', 'category_name' => 'EQUIPMENT']);
        $kimia = DB::table('master_category')->insertGetId(['category_code' => 'CHEM', 'category_name' => 'CHEMICAL']);
        $lama = DB::table('master_category')->insertGetId(['category_code' => 'OLD', 'category_name' => 'OBSOLETE']);
        $pca = DB::table('master_pca')->insertGetId(['pca_code' => 'PCA-1', 'pca_name' => 'PCA Alat']);

        foreach ([
            ['EQPT-CTR-0003', 'Excavator, [Specification], [Model], [Brand]', $unit, $alat, $pca, null],
            ['EQPT-CTR-ACC-0001', 'Accessories Excavator, Bucket, [Remark], [Brand]', $pcs, $alat, null, null],
            ['EQPT-CTR-SPR-0006', 'Sparepart Excavator, Bolt, [Remark]', $pcs, $alat, null, null],
            ['EQPT-CTR-SPR-0099', 'Sparepart Excavator, Nut, 100% Steel', $pcs, $alat, null, null],
            ['EQPT-DOZ-0001', 'Bulldozer, [Remark], [Brand]', $unit, $alat, null, null],
            ['CHEM-0001', 'Acetone, [Remark]', $pcs, $kimia, null, null],
            ['CHEM-0002', 'Excavator Grease', $pcs, $kimia, null, null],
            ['OLD-0001', 'Excavator Lama, [Remark], [Brand]', $unit, $lama, null, '2026-11-01 08:00:00'],
        ] as [$code, $name, $uom, $category, $pcaId, $deletedAt]) {
            DB::table('master_item_code')->insert([
                'item_code' => $code, 'item_name' => $name, 'uom_id' => $uom, 'category_id' => $category,
                'pca_id' => $pcaId, 'deleted_at' => $deletedAt,
            ]);
        }
    }

    private function login(bool $admin = false): void
    {
        config(['test.denied_permissions' => $admin ? [] : ['is_admin']]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    private static function kondisi(string $field, string $op, string $value = ''): array
    {
        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    private static function grup(string $logic, array ...$rules): array
    {
        return ['logic' => $logic, 'rules' => $rules];
    }

    private function request(mixed $filter, array $cariKolom = [], string $cariGlobal = '')
    {
        $columns = [];
        foreach (self::KOLOM as $name) {
            $columns[] = ['data' => $name, 'name' => $name, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => $cariKolom[$name] ?? '', 'regex' => 'false']];
        }
        $query = http_build_query([
            'draw' => 1, 'start' => 0, 'length' => 50, 'columns' => $columns,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => $cariGlobal, 'regex' => 'false'],
            'advanced_filter' => is_array($filter) ? json_encode($filter) : $filter,
        ]);

        return $this->getJson('/master/item-code?'.$query, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    /** @return list<string> */
    private function kode(mixed $filter, array $cariKolom = [], string $cariGlobal = ''): array
    {
        return array_column($this->request($filter, $cariKolom, $cariGlobal)->assertOk()->json('data'), 'item_code');
    }

    public function test_contoh_user_description_excavator_and_remark_or_brand_and_category_equipment(): void
    {
        $this->login();

        // Description mengandung Excavator AND (Description [Remark] OR Description [Brand]) AND Category = EQUIPMENT
        $filter = self::grup('and',
            self::kondisi('description', 'contains', 'Excavator'),
            self::grup('or', self::kondisi('description', 'contains', '[Remark]'), self::kondisi('description', 'contains', '[Brand]')),
            self::kondisi('category', 'equals', 'equipment'),
        );
        $json = $this->request($filter)->assertOk()->json();

        $this->assertSame(['EQPT-CTR-0003', 'EQPT-CTR-ACC-0001', 'EQPT-CTR-SPR-0006'], array_column($json['data'], 'item_code'));
        $this->assertSame(3, $json['recordsFiltered']);
        $this->assertSame(7, $json['recordsTotal']);

        // nilai dicari utuh: frasa "[Remark], [Brand]" (dengan koma dan spasi), bukan per kata
        $this->assertSame(['EQPT-CTR-ACC-0001'], $this->kode(self::grup('and',
            self::kondisi('description', 'contains', 'excavator'),
            self::kondisi('description', 'contains', '[Remark], [Brand]'),
            self::kondisi('category', 'equals', 'EQUIPMENT'),
        )));
    }

    public function test_tiga_tingkat_grup_dan_logika_bawaan_and(): void
    {
        $this->login();

        // tingkat 1 OR: Category CHEMICAL, atau (tingkat 2 AND: Excavator AND (tingkat 3 OR: Bolt / Nut))
        $filter = self::grup('or',
            self::kondisi('category', 'equals', 'CHEMICAL'),
            self::grup('and',
                self::kondisi('description', 'contains', 'Excavator'),
                self::grup('or', self::kondisi('description', 'contains', 'bolt'), self::kondisi('description', 'contains', 'nut')),
            ),
        );
        $this->assertSame(['EQPT-CTR-SPR-0006', 'EQPT-CTR-SPR-0099', 'CHEM-0001', 'CHEM-0002'], $this->kode($filter));

        // logic tidak dikirim = AND
        $this->assertSame(['CHEM-0002'], $this->kode(['rules' => [self::kondisi('description', 'contains', 'excavator'), self::kondisi('category', 'equals', 'chemical')]]));
    }

    public function test_or_di_grup_utama_tidak_lepas_dari_filter_data_terhapus(): void
    {
        $this->login();

        // OLD-0001 terhapus: grup utama dibungkus kurung, jadi "deleted_at IS NULL AND (A OR B)", bukan "... AND A OR B"
        $filter = self::grup('or', self::kondisi('item_code', 'starts_with', 'OLD'), self::kondisi('description', 'contains', 'lama'));
        $this->assertSame([], $this->kode($filter));

        $this->login(admin: true);
        $json = $this->request($filter)->assertOk()->json();
        $this->assertSame(['OLD-0001'], array_column($json['data'], 'item_code'));
        $this->assertSame(['master-row-trashed'], array_column($json['data'], 'DT_RowClass'));
    }

    public function test_operator_lain(): void
    {
        $this->login();

        $this->assertSame(['EQPT-CTR-SPR-0099', 'CHEM-0002'], $this->kode(self::grup('and',
            self::kondisi('description', 'contains', 'excavator'),
            self::kondisi('description', 'not_contains', '['),
        )));
        $this->assertSame(['EQPT-DOZ-0001', 'CHEM-0001', 'CHEM-0002'], $this->kode(self::grup('and', self::kondisi('description', 'not_contains', 'excavator,'))));
        $this->assertSame(['CHEM-0001', 'CHEM-0002'], $this->kode(self::grup('and', self::kondisi('category', 'not_equals', 'equipment'))));
        $this->assertSame(['EQPT-CTR-SPR-0006', 'EQPT-CTR-SPR-0099'], $this->kode(self::grup('and', self::kondisi('description', 'starts_with', 'sparepart'))));
        $this->assertSame(['EQPT-CTR-ACC-0001', 'EQPT-DOZ-0001'], $this->kode(self::grup('and', self::kondisi('description', 'ends_with', '[remark], [brand]'))));
        $this->assertSame(['EQPT-CTR-0003'], $this->kode(self::grup('and', self::kondisi('pca', 'not_empty'))));
        $this->assertCount(6, $this->kode(self::grup('and', self::kondisi('pca', 'empty'))));
        // "tidak sama dengan" ikut item tanpa referensi (NULL), seperti sel kosong di daftar
        $this->assertCount(6, $this->kode(self::grup('and', self::kondisi('pca', 'not_equals', 'PCA Alat'))));
        $this->assertSame(['CHEM-0002'], $this->kode(self::grup('and', self::kondisi('item_code', 'equals', 'chem-0002'))));
    }

    public function test_persen_dan_garis_bawah_dibaca_sebagai_huruf_biasa(): void
    {
        $this->login();

        $this->assertSame(['EQPT-CTR-SPR-0099'], $this->kode(self::grup('and', self::kondisi('description', 'contains', '100%'))));
        $this->assertSame([], $this->kode(self::grup('and', self::kondisi('description', 'contains', '%Steel')))); // wildcard = cocok '100% Steel'
        $this->assertSame([], $this->kode(self::grup('and', self::kondisi('item_code', 'contains', 'EQPT_CTR'))));
    }

    public function test_digabung_and_dengan_cari_kolom_dan_cari_global(): void
    {
        $this->login();

        $filter = self::grup('and', self::kondisi('category', 'equals', 'EQUIPMENT'));
        $this->assertSame(['EQPT-CTR-SPR-0006', 'EQPT-CTR-SPR-0099'], $this->kode($filter, ['item_code' => 'spr']));
        $this->assertSame(['EQPT-CTR-0003', 'EQPT-DOZ-0001'], $this->kode($filter, [], 'unit'));
    }

    public function test_kondisi_tanpa_nilai_dilewati_dan_filter_kosong_sama_dengan_tanpa_filter(): void
    {
        $this->login();

        $this->assertSame(['CHEM-0001', 'CHEM-0002'], $this->kode(self::grup('or',
            self::kondisi('category', 'equals', 'chemical'),
            self::kondisi('description', 'contains', '   '),
            self::grup('and', self::kondisi('item_code', 'contains', '')),
        )));

        $json = $this->request(self::grup('and', self::kondisi('description', 'contains', '')))->assertOk()->json();
        $this->assertSame(7, $json['recordsFiltered']);
        $this->assertSame(7, $this->request('')->assertOk()->json('recordsFiltered'));
    }

    public function test_filter_salah_ditolak_422_dengan_pesan(): void
    {
        $this->login();

        $empatTingkat = self::grup('and', self::grup('and', self::grup('and', self::grup('and', self::kondisi('description', 'contains', 'x')))));
        $terlaluBanyak = ['logic' => 'and', 'rules' => array_fill(0, AdvancedFilter::MAX_RULES + 1, self::kondisi('description', 'contains', 'x'))];

        foreach ([
            [$empatTingkat, 'maksimal 3 tingkat'],
            [$terlaluBanyak, 'maksimal '.AdvancedFilter::MAX_RULES.' kondisi'],
            [self::grup('and', self::kondisi("mic.id) OR 1=1 -- ", 'contains', 'x')), 'Kolom filter lanjutan tidak dikenal'],
            [self::grup('and', self::kondisi('app_code', 'contains', 'x')), 'Kolom filter lanjutan tidak dikenal'],
            [self::grup('and', self::kondisi('description', 'regex', 'x')), 'Operator filter lanjutan tidak dikenal'],
            [self::grup('xor', self::kondisi('description', 'contains', 'x')), 'hanya AND atau OR'],
            [self::grup('and', self::kondisi('description', 'contains', str_repeat('a', AdvancedFilter::MAX_VALUE_LENGTH + 1))), 'maksimal 100 karakter'],
            [self::grup('and', ['field' => 'description', 'op' => 'contains', 'value' => ['x']]), 'harus berupa teks'],
            ['{bukan json', 'bukan JSON'],
            [['rules' => 'x'], 'Grup filter lanjutan tidak dikenali'],
        ] as [$filter, $pesan]) {
            $this->assertStringContainsString($pesan, (string) $this->request($filter)->assertStatus(422)->json('message'));
        }
    }

    public function test_saran_nilai_referensi_ikut_filter_data_terhapus(): void
    {
        $this->login();

        $options = $this->getJson('/master/item-code?advanced_filter_options=1', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('options');

        $this->assertSame(['uom', 'pca', 'category', 'item_group'], array_keys($options));
        $this->assertSame(['CHEMICAL', 'EQUIPMENT'], $options['category']); // OBSOLETE hanya dipakai item terhapus
        $this->assertSame(['Pieces', 'Unit'], $options['uom']);
        $this->assertSame(['PCA Alat'], $options['pca']);
        $this->assertSame([], $options['item_group']);

        $this->login(admin: true);
        $this->assertSame(['CHEMICAL', 'EQUIPMENT', 'OBSOLETE'], $this->getJson('/master/item-code?advanced_filter_options=1', ['X-Requested-With' => 'XMLHttpRequest'])->json('options.category'));
    }

    public function test_halaman_daftar_memuat_tombol_dan_modal_filter_lanjutan(): void
    {
        $this->login();

        $html = $this->get('/master/item-code')->assertOk()->getContent();

        $this->assertStringContainsString('data-maf-open', $html);
        $this->assertStringContainsString('id="maf-modal"', $html);
        $this->assertSame(1, substr_count($html, 'window.masterAdvancedFilter = {'));
        $this->assertStringContainsString('d.advanced_filter = advancedFilter;', $html);
        $this->assertStringContainsString('"max_depth":3', $html);
        $this->assertStringContainsString('"field":"description","label":"Description","suggest":false', $html);
        $this->assertStringContainsString('"field":"category","label":"Category","suggest":true', $html);
        $this->assertStringContainsString('advanced_filter_options=1', $html);
    }
}
