<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use League\Flysystem\UnableToCreateDirectory;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Upload Company Logo dan logo header PDF template: file disimpan ke disk dulu, baru database. Upload yang gagal
 * (mis. 2026-11-06 di PC kantor: folder disk master milik root, UnableToCreateDirectory) tidak boleh meninggalkan
 * baris master_gallery kosong, tidak menghapus logo lama, dan tidak menyimpan data company lain setengah jalan.
 * File selain gambar PNG/JPG ditolak (dulu ekstensi diambil dari nama kiriman browser, file .php ikut tersimpan
 * di folder publik).
 */
class CompanyLogoUploadTest extends TestCase
{
    use CreatesMasterTables;

    private int $companyId;

    private int $oldGalleryId;

    private string $brokenRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        config(['MasterCrudConfig.MASTER_HUB' => true, 'MasterCrudConfig.MASTER_DIRECT_EDIT' => true]);

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

        Storage::fake('master_test', ['url' => 'http://master.test/files']);
        config(['app.storage_disk_master' => 'master_test']);
        Storage::disk('master_test')->put('company/png/1-logo-lama.png', 'logo lama');

        $this->oldGalleryId = DB::table('master_gallery')->insertGetId([
            'user_id' => 1,
            'caption' => 'logo-lama.png',
            'url' => 'http://master.test/files/company/png/1-logo-lama.png',
            'path' => 'company/png',
            'filename' => '1-logo-lama.png',
            'file_type' => 'png',
        ]);
        $this->companyId = DB::table('master_company')->insertGetId([
            'company_code' => 'ME',
            'company_name' => 'PT Meindo Elang Indah',
            'company_logo_id' => $this->oldGalleryId,
            'template_json' => json_encode(['APP32' => ['form_no' => ['spb' => 'FM-PGD-B01-001']]]),
        ]);

        $this->brokenRoot = sys_get_temp_dir().'/bangsamu-master-logo-'.getmypid();

        $this->actingAs(User::create(['name' => 'Gita', 'email' => 'gita@meindo.test']));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->brokenRoot);

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $input */
    private function saveCompany(array $input): TestResponse
    {
        return $this->from(route('master.company.edit', $this->companyId))
            ->post(route('master.company.store'), $input + [
                'id' => $this->companyId,
                'company_code' => 'ME',
                'company_name' => 'PT Meindo Elang Indah',
            ]);
    }

    private function company(): object
    {
        return DB::table('master_company')->where('id', $this->companyId)->first();
    }

    /** disk master yang gagal menulis, seperti kejadian 2026-11-06 */
    private function useBrokenDisk(string $mode): void
    {
        if ($mode === 'folder') {
            // folder company/png tidak bisa dibuat: di sini karena "company" sudah berupa file biasa
            File::ensureDirectoryExists($this->brokenRoot);
            File::put($this->brokenRoot.'/company', 'bukan folder');
            config(['filesystems.disks.master_broken' => ['driver' => 'local', 'root' => $this->brokenRoot, 'throw' => false]]);
        } else {
            $disk = Mockery::mock(Cloud::class);
            $disk->shouldReceive('putFileAs')->andReturn(false);
            $disk->shouldReceive('exists')->andReturn(false);
            $disk->shouldReceive('delete')->andReturn(true);
            Storage::set('master_broken', $disk);
        }
        config(['app.storage_disk_master' => 'master_broken']);
    }

    /** @return array<string, array{string}> */
    public static function brokenDisks(): array
    {
        return [
            'folder tidak bisa dibuat (exception)' => ['folder'],
            'putFileAs mengembalikan false' => ['false'],
        ];
    }

    public function test_ganti_logo_menyimpan_gallery_lengkap_dan_melepas_logo_lama(): void
    {
        $this->saveCompany(['company_logo' => UploadedFile::fake()->image('Logo Meindo.png', 209, 80)])
            ->assertRedirect(route('master.company.index'))
            ->assertSessionHasNoErrors();

        $logoId = (int) $this->company()->company_logo_id;
        $this->assertNotSame($this->oldGalleryId, $logoId);

        $gallery = DB::table('master_gallery')->where('id', $logoId)->first();
        $this->assertMatchesRegularExpression('#^logo-meindo-[a-z0-9]{12}\.png$#', $gallery->filename);
        $this->assertSame('company/png', $gallery->path);
        $this->assertSame('http://master.test/files/company/png/'.$gallery->filename, $gallery->url);
        $this->assertSame('Logo Meindo.png', $gallery->caption);
        $this->assertSame('image/png', $gallery->header_type);
        $this->assertSame('png', $gallery->file_type);
        $this->assertSame(64, strlen((string) $gallery->hash_file));
        Storage::disk('master_test')->assertExists('company/png/'.$gallery->filename);

        // logo lama: barisnya di-soft delete, filenya dibiarkan supaya baris itu masih bisa dipulihkan
        $this->assertNotNull(DB::table('master_gallery')->where('id', $this->oldGalleryId)->value('deleted_at'));
        Storage::disk('master_test')->assertExists('company/png/1-logo-lama.png');
    }

    public function test_company_baru_dengan_logo_jpg(): void
    {
        $this->post(route('master.company.store'), [
            'company_code' => 'ENL',
            'company_name' => 'PT ENL',
            'company_logo' => UploadedFile::fake()->image('enl.jpg'),
        ])->assertRedirect(route('master.company.index'))->assertSessionHasNoErrors();

        $company = DB::table('master_company')->where('company_code', 'ENL')->first();
        $gallery = DB::table('master_gallery')->where('id', $company->company_logo_id)->first();
        $this->assertSame('company/jpg', $gallery->path);
        $this->assertMatchesRegularExpression('#^enl-[a-z0-9]{12}\.jpg$#', $gallery->filename);
        Storage::disk('master_test')->assertExists('company/jpg/'.$gallery->filename);
    }

    #[DataProvider('brokenDisks')]
    public function test_disk_gagal_tidak_menulis_database_dan_logo_lama_tetap(string $mode): void
    {
        $this->useBrokenDisk($mode);
        $galleryRows = DB::table('master_gallery')->count();

        $this->saveCompany(['company_name' => 'Nama Diubah', 'company_logo' => UploadedFile::fake()->image('logo_meindo.png')])
            ->assertRedirect(route('master.company.edit', $this->companyId))
            ->assertSessionHasErrors(['company_logo' => 'Logo gagal disimpan ke penyimpanan file (folder tidak bisa ditulis atau penuh). Belum ada data yang disimpan; coba lagi atau hubungi admin.']);

        $this->assertSame($galleryRows, DB::table('master_gallery')->count());
        $company = $this->company();
        $this->assertSame($this->oldGalleryId, (int) $company->company_logo_id);
        $this->assertSame('PT Meindo Elang Indah', $company->company_name);
        $this->assertNull(DB::table('master_gallery')->where('id', $this->oldGalleryId)->value('deleted_at'));
        Storage::disk('master_test')->assertExists('company/png/1-logo-lama.png');
    }

    /** @return array<string, array{string, string}> */
    public static function nonImageFiles(): array
    {
        return [
            'php' => ['shell.php', '<?php echo "x";'],
            'svg berisi script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'html' => ['logo.html', '<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('nonImageFiles')]
    public function test_file_bukan_gambar_png_jpg_ditolak_sebelum_disimpan(string $name, string $content): void
    {
        $galleryRows = DB::table('master_gallery')->count();

        $this->saveCompany(['company_logo' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertSessionHasErrors('company_logo');

        $this->assertSame($galleryRows, DB::table('master_gallery')->count());
        $this->assertSame($this->oldGalleryId, (int) $this->company()->company_logo_id);
        $this->assertSame(['company/png/1-logo-lama.png'], Storage::disk('master_test')->allFiles());
    }

    #[DataProvider('brokenDisks')]
    public function test_logo_template_gagal_tidak_menulis_gallery_maupun_template(string $mode): void
    {
        $this->useBrokenDisk($mode);
        $before = $this->company()->template_json;

        $this->from(route('master.company.edit', $this->companyId))
            ->post(route('master.company.update_template_json', $this->companyId), [
                'template_json' => $before,
                'logo_pdf_key' => 'APP32',
                'logo_pdf_file' => ['iso' => UploadedFile::fake()->image('iso.png')],
            ])->assertRedirect(route('master.company.edit', $this->companyId))
            ->assertSessionHasErrors('logo_pdf_file.iso');

        $this->assertSame(1, DB::table('master_gallery')->count());
        $this->assertSame($before, $this->company()->template_json);
    }

    public function test_logo_template_kedua_gagal_file_pertama_dihapus_lagi(): void
    {
        $calls = 0;
        $disk = Mockery::mock(Cloud::class);
        $disk->shouldReceive('putFileAs')->twice()->andReturnUsing(function (string $path, $file, string $name) use (&$calls) {
            if (++$calls === 2) {
                throw UnableToCreateDirectory::atLocation($path, 'Permission denied');
            }

            return $path.'/'.$name;
        });
        $disk->shouldReceive('exists')->andReturn(true);
        $disk->shouldReceive('url')->andReturnUsing(fn (string $path) => 'http://master.test/files/'.$path);
        $disk->shouldReceive('delete')->once()->with(Mockery::pattern('#^company/png/motto-[a-z0-9]{12}\.png$#'))->andReturn(true);
        // sisa tulis setengah jalan dari file kedua ikut dibersihkan
        $disk->shouldReceive('delete')->once()->with(Mockery::pattern('#^company/png/iso-[a-z0-9]{12}\.png$#'))->andReturn(true);
        Storage::set('master_broken', $disk);
        config(['app.storage_disk_master' => 'master_broken']);
        $before = $this->company()->template_json;

        $this->post(route('master.company.update_template_json', $this->companyId), [
            'template_json' => $before,
            'logo_pdf_key' => 'APP32',
            'logo_pdf_file' => [
                'logo_2' => UploadedFile::fake()->image('motto.png'),
                'iso' => UploadedFile::fake()->image('iso.png'),
            ],
        ])->assertSessionHasErrors('logo_pdf_file.iso');

        $this->assertSame(1, DB::table('master_gallery')->count());
        $this->assertSame($before, $this->company()->template_json);
    }
}
