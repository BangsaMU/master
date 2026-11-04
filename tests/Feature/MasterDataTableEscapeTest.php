<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Support\DataTableCell;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Stored XSS di daftar master: layouts.dashboard.index memasang nilai JSON dataJson() ke sel DataTables 1.10 sebagai
 * HTML (tanpa render text). Dulu nilai kolom dikirim mentah (`$nestedData[$c_filed] = @$row->$c_filed`), jadi nama berisi
 * `<img src=x onerror=...>` (form, import Excel, atau sinkron aplikasi lain) dieksekusi di browser semua pembuka daftar.
 * Kini teks di-escape di server (DataTableCell); hanya kolom action (HTML tombol buatan server) dan Attributes Item Code
 * (`<br>` pemisah, tiap key/value di-escape) yang berisi HTML.
 */
class MasterDataTableEscapeTest extends TestCase
{
    use CreatesMasterTables;

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        // model Master* memakai koneksi db_master: di test satu database dengan koneksi default (seperti hub)
        $app['config']->set('database.connections.db_master', $app['config']->get('database.connections.testing'));
    }

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->createDashboardSettings();
        DB::connection('db_master')->setPdo(DB::connection()->getPdo());
        app(MasterSchemaUpgrader::class)->ensure('master_department');

        // kolom/tabel yang tidak dibuat CreatesMasterTables tetapi dibaca daftar Company, Project Detail, Priority
        Schema::table('master_company', fn (Blueprint $table) => $table->string('company_name')->nullable());
        Schema::create('master_priority', function (Blueprint $table) {
            $table->id();
            $table->string('priority_code')->nullable();
            $table->string('priority_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        config(['test.denied_permissions' => []]);
        $this->actingAs(User::create(['name' => 'Gita Samudra', 'email' => 'gita'.uniqid().'@meindo.test']));
    }

    /** @return array<int, array<string, mixed>> */
    private function daftar(string $slug): array
    {
        return $this->getJson("/master/$slug", ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
    }

    /** baris milik aplikasi ini (APP27 di TestCase) bila tabel ber-app_code */
    private function insert(string $tabel, array $baris): int
    {
        if (Schema::hasColumn($tabel, 'app_code')) {
            $baris += ['app_code' => 'APP27'];
        }

        return DB::table($tabel)->insertGetId($baris);
    }

    /** semua sel kecuali action (HTML tombol) tidak boleh berisi tag dari data */
    private function assertTanpaTagMentah(array $row, string $konteks): void
    {
        unset($row['action']);
        foreach ($row as $kolom => $nilai) {
            if (is_string($nilai)) {
                $this->assertStringNotContainsString('<img', $nilai, "$konteks kolom $kolom berisi tag mentah");
                $this->assertStringNotContainsString('<script', $nilai, "$konteks kolom $kolom berisi tag mentah");
            }
        }
    }

    #[Test]
    public function item_code_nama_dan_referensi_berisi_tag_dikirim_ter_escape(): void
    {
        $uom = DB::table('master_uom')->insertGetId(['uom_code' => 'PCS', 'uom_name' => '<script>uom()</script>']);
        $this->insert('master_item_code', [
            'item_code' => 'XSS-0001',
            'item_name' => self::PAYLOAD,
            'uom_id' => $uom,
            'attributes' => json_encode(['size_1' => '<b onmouseover=x()>4x16</b>', '<i>warna</i>' => 'Merah & "Biru"']),
        ]);

        $row = collect($this->daftar('item-code'))->firstWhere('item_code', 'XSS-0001');

        $this->assertNotNull($row);
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $row['description']);
        $this->assertSame('&lt;script&gt;uom()&lt;/script&gt;', $row['uom']);
        // Attributes: tiap key/value di-escape, baris dipisah <br> (satu-satunya HTML yang sengaja dikirim)
        $this->assertSame(
            'Size 1: &lt;b onmouseover=x()&gt;4x16&lt;/b&gt;<br>&lt;i&gt;warna&lt;/i&gt;: Merah &amp; &quot;Biru&quot;',
            $row['attributes']
        );
        $this->assertTanpaTagMentah($row, 'item-code');
        // kolom action tetap HTML tombol
        $this->assertStringContainsString('<a href="', $row['action']);
    }

    #[Test]
    public function item_code_attributes_bersarang_atau_bukan_json_tidak_error(): void
    {
        $this->insert('master_item_code', ['item_code' => 'XSS-0002', 'item_name' => 'Pipa', 'attributes' => json_encode(['dimensi' => ['<b>p</b>' => 2]])]);
        $this->insert('master_item_code', ['item_code' => 'XSS-0003', 'item_name' => 'Kabel', 'attributes' => self::PAYLOAD]);

        $rows = collect($this->daftar('item-code'))->keyBy('item_code');

        $this->assertSame('Dimensi: {&quot;&lt;b&gt;p&lt;/b&gt;&quot;:2}', $rows['XSS-0002']['attributes']);
        $this->assertSame('', $rows['XSS-0003']['attributes']);
    }

    public static function modulProvider(): array
    {
        return [
            'category' => ['category', 'master_category', 'category_code', 'category_name', ['category_code' => 'XCAT01']],
            'uom' => ['uom', 'master_uom', 'uom_code', 'uom_name', ['uom_code' => 'XUOM01']],
            'pca' => ['pca', 'master_pca', 'pca_code', 'pca_name', ['pca_code' => 'XPCA01']],
            'item-group' => ['item-group', 'master_item_group', 'item_group_code', 'item_group_name', ['item_group_code' => 'XGRP01']],
            'project' => ['project', 'master_project', 'project_code', 'project_name', ['project_code' => 'XPRJ01']],
            'department' => ['department', 'master_department', 'department_code', 'department_name', ['department_code' => 'XDEP01']],
            'location' => ['location', 'master_location', 'loc_code', 'loc_name', ['loc_code' => 'XLOC01', 'group_type' => 'warehouse']],
            'vendor' => ['vendor', 'master_vendor', 'vendor_code', 'vendor_description', ['vendor_code' => 'XVEN01', 'loc_id' => 0]],
            'company' => ['company', 'master_company', 'company_code', 'company_name', ['company_code' => 'XCOM01']],
            'priority' => ['priority', 'master_priority', 'priority_code', 'priority_name', ['priority_code' => 'XPRI01']],
        ];
    }

    #[Test]
    #[DataProvider('modulProvider')]
    public function nama_berisi_tag_dikirim_ter_escape(string $slug, string $tabel, string $kolomKode, string $kolomNama, array $baris): void
    {
        $this->insert($tabel, $baris + [$kolomNama => self::PAYLOAD]);

        $row = collect($this->daftar($slug))->firstWhere($kolomKode, $baris[$kolomKode]);

        $this->assertNotNull($row, "baris $slug tidak tampil");
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $row[$kolomNama]);
        $this->assertTanpaTagMentah($row, $slug);
        $this->assertStringContainsString('<a href="', $row['action']);
    }

    #[Test]
    public function project_detail_nama_project_company_dan_kode_klien_ter_escape(): void
    {
        $project = $this->insert('master_project', ['project_code' => 'XPRJ02', 'project_name' => self::PAYLOAD, 'internal_external' => 'E']);
        $company = $this->insert('master_company', ['company_code' => 'XCOM02', 'company_name' => '<script>c()</script>']);
        $this->insert('master_project_detail', ['project_id' => $project, 'company_id' => $company, 'project_code_client' => '<b>K1</b>']);

        $row = collect($this->daftar('project-detail'))->firstWhere('project_code', 'XPRJ02');

        $this->assertNotNull($row);
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $row['project_name']);
        $this->assertSame('&lt;script&gt;c()&lt;/script&gt;', $row['company_name']);
        $this->assertSame('&lt;b&gt;K1&lt;/b&gt;', $row['project_code_client']);
        $this->assertSame('External', $row['project_type']);
        $this->assertTanpaTagMentah($row, 'project-detail');
    }

    #[Test]
    public function helper_hanya_meng_escape_teks_dan_membiarkan_angka_dan_null(): void
    {
        $this->assertSame('&lt;b&gt;A &amp; B&lt;/b&gt; &#039;x&#039;', DataTableCell::text("<b>A & B</b> 'x'"));
        $this->assertSame(7, DataTableCell::text(7));
        $this->assertSame(1.5, DataTableCell::text(1.5));
        $this->assertNull(DataTableCell::text(null));
    }

    /**
     * Employee (query MySQL: FIND_IN_SET), CRUD tabel generik dan modul lain tidak bisa dijalankan di SQLite, jadi dijaga
     * dari sumber: tidak ada lagi controller yang memasang nilai baris mentah ke data DataTables.
     */
    #[Test]
    public function tidak_ada_controller_yang_memasang_nilai_baris_mentah(): void
    {
        $mentah = [];
        foreach (glob(dirname(__DIR__, 2).'/src/Controllers/*.php') as $file) {
            foreach (file($file) as $nomor => $baris) {
                if (preg_match('/\$nestedData\[\$c_filed\]\s*=\s*@?\$row->\$c_filed\s*;/', $baris)) {
                    $mentah[] = basename($file).':'.($nomor + 1);
                }
            }
        }

        $this->assertSame([], $mentah, 'nilai baris wajib lewat DataTableCell::text()');
    }
}
