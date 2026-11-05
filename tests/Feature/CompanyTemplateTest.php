<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Support\CompanyTemplate;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Kartu Template JSON di form company: dropdown App Code Key berlabel "kode | nama" (master_apps), logo header PDF
 * (Motto/ISO) per App Code Key lewat upload, dan hanya bisa diubah di aplikasi Master Data (MASTER_HUB); aplikasi
 * penyalin (mis. SPB) menampilkan hanya baca dan menolak simpan.
 */
class CompanyTemplateTest extends TestCase
{
    use CreatesMasterTables;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        View::addNamespace('adminlte', __DIR__.'/../stubs/adminlte');

        Schema::table('master_company', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('company_short')->nullable();
            $table->string('company_attention')->nullable();
            $table->text('company_address')->nullable();
            $table->unsignedBigInteger('company_logo_id')->nullable();
            $table->text('template_json')->nullable();
        });
        if (! Schema::hasTable('master_gallery')) {
            Schema::create('master_gallery', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('caption')->nullable();
                $table->text('url')->nullable();
                $table->text('path')->nullable();
                $table->string('filename')->nullable();
                $table->string('size')->nullable();
                $table->string('header_type')->nullable();
                $table->string('file_group')->nullable();
                $table->string('hash_file')->nullable();
                $table->char('file_type', 10);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::create('master_apps', function (Blueprint $table) {
            $table->id();
            $table->string('app_code', 10);
            $table->string('name', 25);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('master_apps')->insert([
            ['app_code' => 'APP32', 'name' => 'SPB V2'],
            ['app_code' => 'APP09', 'name' => 'Warehouse App'],
        ]);

        $this->companyId = DB::table('master_company')->insertGetId([
            'company_code' => 'ME',
            'company_name' => 'PT Meindo Elang Indah',
            'template_json' => json_encode([
                'APP32' => ['form_no' => ['spb' => 'FM-PGD-B01-001'], 'format_date' => 'd-m-Y', 'template_header' => 1],
                'APP09' => ['form_no' => ['spb' => 'MEI-FLK-MTC-101']],
            ]),
        ]);

        $this->actingAs(User::create(['name' => 'Gita', 'email' => 'gita@meindo.test']));
    }

    private function asHub(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => true, 'MasterCrudConfig.MASTER_DIRECT_EDIT' => true]);
    }

    private function asCopyApp(): void
    {
        config(['MasterCrudConfig.MASTER_HUB' => false, 'MasterCrudConfig.MASTER_DIRECT_EDIT' => false]);
    }

    /** @return array<string, array{string}> */
    public static function themes(): array
    {
        return ['adminlte' => [''], 'tabler' => ['_tabler'], 'meridian' => ['_meridian']];
    }

    /** @return array<string, mixed> */
    private function template(): array
    {
        return json_decode((string) DB::table('master_company')->where('id', $this->companyId)->value('template_json'), true);
    }

    #[DataProvider('themes')]
    public function test_hub_menampilkan_app_code_dengan_nama_dan_form_upload_logo(string $theme): void
    {
        config(['app.themes' => $theme]);
        $this->asHub();

        $html = $this->get(route('master.company.edit', $this->companyId))->assertOk()->getContent();

        $this->assertStringContainsString('APP32 | SPB V2</option>', $html);
        $this->assertStringContainsString('APP09 | Warehouse App</option>', $html);
        $this->assertStringContainsString('Save Template</button>', $html);
        $this->assertStringContainsString('id="btn_add_key"', $html);
        $this->assertStringContainsString('name="logo_pdf_file[logo_2]"', $html);
        $this->assertStringContainsString('name="logo_pdf_file[iso]"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    #[DataProvider('themes')]
    public function test_aplikasi_penyalin_menampilkan_template_hanya_baca(string $theme): void
    {
        config(['app.themes' => $theme]);
        $this->asCopyApp();

        foreach (['master.company.show', 'master.company.edit'] as $route) {
            $html = $this->get(route($route, $this->companyId))->assertOk()->getContent();

            $this->assertStringContainsString('APP32 | SPB V2</option>', $html, $route);
            $this->assertStringContainsString('di sini hanya baca', $html, $route);
            $this->assertStringNotContainsString('Save Template</button>', $html, $route);
            $this->assertStringNotContainsString('id="btn_add_key"', $html, $route);
            $this->assertStringNotContainsString('logo_pdf_file[', $html, $route);
            $this->assertStringNotContainsString('type="submit"', $html, $route);
        }
    }

    public function test_aplikasi_penyalin_menolak_simpan_template(): void
    {
        $this->asCopyApp();
        $before = $this->template();

        $this->post(route('master.company.update_template_json', $this->companyId), [
            'template_json' => json_encode(['APP32' => ['form_no' => ['spb' => 'DIUBAH']]]),
        ])->assertForbidden();

        $this->assertSame($before, $this->template());
    }

    public function test_upload_logo_menyimpan_url_gallery_ke_key_yang_dipilih(): void
    {
        $this->asHub();
        Storage::fake('master_test', ['url' => 'http://master.test/files']);
        config(['app.storage_disk_master' => 'master_test']);
        $template = $this->template();

        $this->from(route('master.company.edit', $this->companyId))
            ->post(route('master.company.update_template_json', $this->companyId), [
                'template_json' => json_encode($template),
                'logo_pdf_key' => 'APP32',
                'logo_pdf_file' => ['iso' => UploadedFile::fake()->image('ISO Cert.png', 120, 40)],
            ])->assertRedirect(route('master.company.edit', $this->companyId))->assertSessionHasNoErrors();

        $saved = $this->template();
        $iso = $saved['APP32']['logo_pdf']['iso'] ?? '';
        $this->assertMatchesRegularExpression('#^http://master\.test/files/company/png/\d+-iso-cert\.png$#', $iso);
        $this->assertSame(['iso'], array_keys($saved['APP32']['logo_pdf']));
        $this->assertSame('FM-PGD-B01-001', $saved['APP32']['form_no']['spb']);
        $this->assertArrayNotHasKey('logo_pdf', $saved['APP09']);
        Storage::disk('master_test')->assertExists(substr($iso, strlen('http://master.test/files/')));
        $this->assertSame($iso, DB::table('master_gallery')->value('url'));
    }

    public function test_upload_logo_tanpa_key_valid_ditolak(): void
    {
        $this->asHub();
        Storage::fake('master_test');
        config(['app.storage_disk_master' => 'master_test']);

        $this->post(route('master.company.update_template_json', $this->companyId), [
            'template_json' => json_encode($this->template()),
            'logo_pdf_key' => 'APP99',
            'logo_pdf_file' => ['logo_2' => UploadedFile::fake()->image('motto.png')],
        ])->assertSessionHasErrors('logo_pdf_key');

        $this->assertArrayNotHasKey('logo_pdf', $this->template()['APP32']);
        $this->assertSame(0, DB::table('master_gallery')->count());
    }

    public function test_logo_selain_url_http_dibuang_saat_simpan(): void
    {
        $this->asHub();
        $template = $this->template();
        $template['APP32']['logo_pdf'] = [
            'iso' => 'storage/../../.env',
            'logo_2' => 'https://cms.meindo.test/motto.png',
            'logo_1' => 'https://cms.meindo.test/logo.png',
        ];
        $template['APP09']['logo_pdf'] = ['iso' => 'file:///etc/passwd'];

        $this->post(route('master.company.update_template_json', $this->companyId), [
            'template_json' => json_encode($template),
        ])->assertRedirect();

        $saved = $this->template();
        $this->assertSame(['logo_2' => 'https://cms.meindo.test/motto.png'], $saved['APP32']['logo_pdf']);
        $this->assertArrayNotHasKey('logo_pdf', $saved['APP09']);
    }

    public function test_logo_urls_hanya_milik_aplikasi_dan_url_http(): void
    {
        $json = json_encode([
            'APP32' => ['logo_pdf' => ['logo_2' => 'https://a.test/motto.png', 'iso' => './storage/logo_pdf/iso.png']],
            'APP09' => ['logo_pdf' => ['iso' => 'https://a.test/iso-gudang.png']],
        ]);

        $this->assertSame(['logo_2' => 'https://a.test/motto.png'], CompanyTemplate::logoUrls($json, 'APP32'));
        $this->assertSame(['iso' => 'https://a.test/iso-gudang.png'], CompanyTemplate::logoUrls($json, 'APP09'));
        $this->assertSame([], CompanyTemplate::logoUrls($json, 'APP27'));
        $this->assertSame([], CompanyTemplate::logoUrls(null, 'APP32'));
        $this->assertSame([], CompanyTemplate::logoUrls('bukan json', 'APP32'));
        $this->assertSame('APP32 | SPB V2', CompanyTemplate::appLabel('APP32', ['APP32' => 'SPB V2']));
        $this->assertSame('APP99', CompanyTemplate::appLabel('APP99', ['APP32' => 'SPB V2']));
    }
}
