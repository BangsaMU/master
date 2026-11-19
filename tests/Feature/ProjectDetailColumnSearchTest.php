<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar master/project-detail: cari per kolom (input di bawah judul kolom, columns[i][search][value]) dan cari global.
 * Dulu cari global (helper app datatabelFilterQuery) hanya mencari kolom master_project_detail: cari "21317" hanya
 * menemukan baris ber-Project Code Client 21317, bukan baris company lain milik project 21317 (temuan user 2026-11-19).
 */
class ProjectDetailColumnSearchTest extends TestCase
{
    use CreatesMasterTables;

    private const KOLOM = ['No', 'project_type', 'project_code', 'project_name', 'company_code', 'company_name', 'project_code_client', 'action'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDashboardSettings();
        Route::get('/login', fn () => 'login page')->name('login');
        Schema::table('master_company', fn (Blueprint $table) => $table->string('company_name')->nullable());

        $me = DB::table('master_company')->insertGetId(['company_code' => 'ME', 'company_name' => 'PT Meindo Elang Indah']);
        $meb = DB::table('master_company')->insertGetId(['company_code' => 'MEB', 'company_name' => 'PT. Meitech Eka Bintan']);
        $enl = DB::table('master_company')->insertGetId(['company_code' => 'ENL', 'company_name' => 'Elnusa']);
        $sisi = DB::table('master_project')->insertGetId(['project_code' => '21317', 'project_name' => 'EPSCC for Sisi Nubi AOI Package A', 'internal_external' => 'E']);
        $alat = DB::table('master_project')->insertGetId(['project_code' => '20000', 'project_name' => 'Heavy Equipment 100%', 'internal_external' => 'I']);

        foreach ([
            [$sisi, $me, '21317', null],
            [$sisi, $meb, '73029', null],
            [$alat, $me, '20000', null],
            [$alat, $enl, 'EN_001', null],
            [$sisi, $enl, 'OLD-1', '2026-11-01 08:00:00'],
        ] as [$project, $company, $client, $deletedAt]) {
            DB::table('master_project_detail')->insert([
                'project_id' => $project, 'company_id' => $company, 'project_code_client' => $client, 'deleted_at' => $deletedAt,
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

        return $this->getJson('/master/project-detail?'.$query, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json();
    }

    /** @return list<string> Project Code Client baris hasil */
    private function klien(array $json): array
    {
        return array_column($json['data'], 'project_code_client');
    }

    public function test_cari_global_ikut_kolom_project_dan_company(): void
    {
        $this->login();

        // temuan user: dulu hanya 21317 (Project Code Client), baris MEB 73029 milik project 21317 tidak ikut
        $json = $this->daftar([], '21317');
        $this->assertSame(['21317', '73029'], $this->klien($json));
        $this->assertSame(4, $json['recordsTotal']); // baris terhapus tidak dihitung untuk non-admin
        $this->assertSame(2, $json['recordsFiltered']);

        $this->assertSame(['73029'], $this->klien($this->daftar([], 'meitech')));
        $this->assertSame(['73029'], $this->klien($this->daftar([], 'MEB 21317'))); // tiap kata boleh di kolom berbeda
        $this->assertSame(['21317', '20000'], $this->klien($this->daftar([], 'elang indah')));
    }

    public function test_cari_per_kolom_dan_antar_kolom_and(): void
    {
        $this->login();

        $this->assertSame(['21317', '73029'], $this->klien($this->daftar(['project_code' => '21317'])));
        $this->assertSame(['21317'], $this->klien($this->daftar(['project_code_client' => '21317'])));
        $this->assertSame(['73029'], $this->klien($this->daftar(['project_code' => '21317', 'company_code' => 'meb'])));
        $this->assertSame(['21317', '20000'], $this->klien($this->daftar(['company_name' => 'meindo'])));
        $this->assertSame(['EN_001'], $this->klien($this->daftar(['project_name' => 'heavy', 'company_code' => 'enl'])));
        $this->assertSame([], $this->klien($this->daftar(['project_name' => 'heavy', 'company_code' => 'meb'])));
    }

    public function test_project_type_dicari_sebagai_teks_yang_tampil(): void
    {
        $this->login();

        $json = $this->daftar(['project_type' => 'intern']);
        $this->assertSame(['20000', 'EN_001'], $this->klien($json));
        $this->assertSame(['Internal', 'Internal'], array_column($json['data'], 'project_type'));
        $this->assertSame(['21317', '73029'], $this->klien($this->daftar(['project_type' => 'external'])));
    }

    public function test_persen_dan_garis_bawah_dibaca_sebagai_huruf_biasa(): void
    {
        $this->login();

        $this->assertSame(['EN_001'], $this->klien($this->daftar(['project_code_client' => 'n_0'])));
        $this->assertSame([], $this->klien($this->daftar(['project_code_client' => '7_0']))); // wildcard dulu kena 73029
        $this->assertSame(['20000', 'EN_001'], $this->klien($this->daftar(['project_name' => '100%'])));
        $this->assertSame([], $this->klien($this->daftar([], '%sisi'))); // % bukan wildcard: "%sisi" tidak ada di data
        $this->assertSame(['20000', 'EN_001'], $this->klien($this->daftar([], '%'))); // % sungguhan di nama project
    }

    public function test_nama_kolom_asing_diabaikan_dan_urut_lewat_nama_kolom(): void
    {
        $this->login();

        $columns = [['data' => 'mpd.id) OR 1=1 -- ', 'search' => ['value' => 'x']], ['data' => 'action', 'search' => ['value' => 'zzz']]];
        $json = $this->getJson('/master/project-detail?'.http_build_query(['columns' => $columns, 'order' => [['column' => 0, 'dir' => 'asc; drop']]]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json();
        $this->assertSame(4, $json['recordsFiltered']);

        // indeks 2 = project_code, 5 = company_name, 1 = project_type (ekspresi CASE); dulu indeks dipetakan ke $view_tabel lain
        $this->assertSame(['20000', 'EN_001', '21317', '73029'], $this->klien($this->daftar([], '', [2, 'asc'])));
        // nama company sama (PT Meindo Elang Indah) = urutan cadangan mpd.id ikut arah desc
        $this->assertSame(['73029', '20000', '21317', 'EN_001'], $this->klien($this->daftar([], '', [5, 'desc'])));
        $this->assertSame(['EN_001', '20000', '73029', '21317'], $this->klien($this->daftar([], '', [1, 'desc']))); // Internal dulu
    }

    public function test_admin_tetap_melihat_baris_terhapus_saat_mencari(): void
    {
        $this->login(admin: true);

        $json = $this->daftar(['company_code' => 'enl']);

        $this->assertSame(['EN_001', 'OLD-1'], $this->klien($json));
        $this->assertSame(['', 'master-row-trashed'], array_column($json['data'], 'DT_RowClass'));
        $this->assertSame(2, $json['recordsFiltered']);
    }

    public function test_hanya_satu_halaman_yang_diambil_dari_database(): void
    {
        $this->login();
        $selects = [];
        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'mp.project_code AS project_code')) {
                $selects[] = $query->sql;
            }
        });

        $json = $this->daftar(['company_code' => 'e'], '', [0, 'asc'], 2);

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
            ['No' => false, 'project_type' => true, 'project_code' => true, 'project_name' => true, 'company_code' => true,
                'company_name' => true, 'project_code_client' => true, 'action' => false],
            $filter
        );

        $html = $this->get('/master/project-detail')->assertOk()->getContent();
        $this->assertStringContainsString('<tr class="cari-kolom"></tr>', $html);
        $this->assertStringContainsString('var columnSearch = true;', $html);
    }
}
