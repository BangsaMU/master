<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\MasterLaunchToken;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * "Edit di Master Data" tanpa login ulang: klien (APP27) menandatangani token Ed25519, hub (APP03) memverifikasi,
 * me-login-kan user, dan menyimpan session delegasi.
 */
class MasterLaunchTokenTest extends TestCase
{
    use CreatesMasterTables;

    private array $keys;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        $this->createDashboardSettings();
        Artisan::call('master:scope-upgrade');
        Schema::table('users', function (Blueprint $table) {
            $table->string('list_apps_id')->nullable();
            $table->integer('is_active')->default(1);
        });
        Schema::create('master_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('app_code');
            $table->string('app_url')->nullable();
            $table->softDeletes();
        });
        DB::table('master_apps')->insert([
            ['id' => 3, 'name' => 'MASTER DATA', 'app_code' => 'APP03', 'app_url' => 'https://hub.test/'],
            ['id' => 39, 'name' => 'SPB V2', 'app_code' => 'APP27', 'app_url' => 'https://spb.test/'],
        ]);

        $this->keys = MasterLaunchToken::generateKeyPair();
        config([
            'MasterCrudConfig.LAUNCH_PRIVATE_KEY' => $this->keys['secret'],
            'MasterCrudConfig.MASTER_DIRECT_EDIT' => false,
            'MasterCrudConfig.MASTER_EDIT_URL' => 'https://hub.test/',
        ]);
        app(MasterLaunchToken::class)->registerPublicKey('APP27', $this->keys['public']);

        $this->user = User::create(['name' => 'Admin SPB', 'email' => 'admin.spb@meindo.test', 'list_apps_id' => '3,39']);
        DB::table('user_details')->insert(['user_email' => $this->user->email, 'field_key' => 'company_id', 'field_value' => '647']);
    }

    /** token dibuat sebagai klien APP27 */
    private function issue(array $claims = []): string
    {
        config(['SsoConfig.main.APP_CODE' => 'APP27', 'MasterCrudConfig.MASTER_HUB' => false]);

        return app(MasterLaunchToken::class)->issue($claims + [
            'sub' => $this->user->email, 'cid' => 647, 'all_co' => false,
            'mods' => ['master_category' => ['create', 'update'], 'master_company' => ['update']],
            'next' => '/master/category/create', 'back' => 'https://spb.test/master/category',
        ]);
    }

    private function asHub(): void
    {
        config(['SsoConfig.main.APP_CODE' => 'APP03', 'MasterCrudConfig.MASTER_HUB' => true]);
    }

    private function assertRejected(string $token, string $reason): void
    {
        try {
            app(MasterLaunchToken::class)->verify($token);
            $this->fail('token seharusnya ditolak: '.$reason);
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($reason, $e->getMessage());
        }
    }

    public function test_token_valid_sekali_pakai(): void
    {
        $token = $this->issue();
        $this->asHub();

        $payload = app(MasterLaunchToken::class)->verify($token);
        $this->assertSame('APP27', $payload['iss']);
        $this->assertSame($this->user->email, $payload['sub']);

        $this->assertRejected($token, 'sudah dipakai');
    }

    public function test_token_diubah_atau_kunci_lain_ditolak(): void
    {
        $token = $this->issue();
        $this->asHub();
        [$prefix, $body, $signature] = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $payload['cid'] = 1;
        $forged = $prefix.'.'.rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=').'.'.$signature;
        $this->assertRejected($forged, 'tanda tangan tidak valid');

        // ditandatangani kunci lain (bukan kunci terdaftar APP27)
        config(['MasterCrudConfig.LAUNCH_PRIVATE_KEY' => MasterLaunchToken::generateKeyPair()['secret']]);
        $other = $this->issue();
        $this->asHub();
        $this->assertRejected($other, 'tanda tangan tidak valid');
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $token = $this->issue();
        $this->asHub();
        $this->travel(10)->minutes();

        $this->assertRejected($token, 'kedaluwarsa');
    }

    public function test_token_untuk_hub_lain_ditolak(): void
    {
        config(['MasterCrudConfig.GLOBAL_APP_CODE' => 'APP99']); // aud
        $token = $this->issue();
        config(['MasterCrudConfig.GLOBAL_APP_CODE' => 'APP03']);
        $this->asHub();

        $this->assertRejected($token, 'bukan untuk aplikasi ini');
    }

    public function test_kunci_publik_belum_terdaftar_ditolak(): void
    {
        DB::table('dashboard_settings')->where('key', 'master_launch.public_key.APP27')->delete();
        $token = $this->issue();
        $this->asHub();

        $this->assertRejected($token, 'belum didaftarkan');
    }

    public function test_hub_login_user_dan_simpan_delegasi(): void
    {
        $token = $this->issue();
        $this->asHub();

        $this->post('/master-launch', ['token' => $token])->assertRedirect('/master/category/create');

        $this->assertTrue(Auth::check());
        $this->assertSame($this->user->email, Auth::user()->email);
        $delegation = session(MasterScope::SESSION_KEY);
        $this->assertSame('APP27', $delegation['app_code']);
        $this->assertSame(647, $delegation['company_id']);
        // tabel global (master_company) tidak pernah didelegasikan
        $this->assertSame(['master_category' => ['create', 'update']], $delegation['mods']);
        $this->assertSame('https://spb.test/master/category', $delegation['back']);
    }

    public function test_hub_menolak_user_yang_tidak_terdaftar_di_aplikasi_asal(): void
    {
        $this->user->update(['list_apps_id' => '3']);
        $token = $this->issue();
        $this->asHub();

        $this->post('/master-launch', ['token' => $token])->assertForbidden();
        $this->assertFalse(Auth::check());
    }

    public function test_hub_menolak_user_nonaktif_dan_modul_di_luar_allowlist(): void
    {
        $first = $this->issue();
        $second = $this->issue();
        $this->asHub();
        app(MasterLaunchToken::class)->setAllowedTables('APP27', ['master_uom']);
        $this->post('/master-launch', ['token' => $first])->assertForbidden();

        app(MasterLaunchToken::class)->setAllowedTables('APP27', ['master_category']);
        $this->user->update(['is_active' => 0]);
        $this->post('/master-launch', ['token' => $second])->assertForbidden();
        $this->assertFalse(Auth::check());
    }

    public function test_hub_next_dan_back_dibatasi(): void
    {
        $token = $this->issue(['next' => 'https://evil.test/master/category', 'back' => 'https://evil.test/']);
        $this->asHub();

        $this->post('/master-launch', ['token' => $token])->assertRedirect('/master/category');
        $this->assertNull(session(MasterScope::SESSION_KEY)['back']);
    }

    public function test_klien_tombol_edit_mengirim_token_ke_hub(): void
    {
        config(['test.allowed_permissions' => ['update_category', 'create_category']]);
        $own = DB::table('master_category')->insertGetId(['category_code' => 'C1', 'category_name' => 'Milik', 'app_code' => 'APP27', 'company_id' => 647]);
        $shared = DB::table('master_category')->insertGetId(['category_code' => 'C2', 'category_name' => 'Bersama', 'app_code' => 'APP03', 'company_id' => 0]);
        $this->actingAs($this->user);

        $response = $this->get(route('master-launch.go', ['slug' => 'category', 'id' => $own]))->assertOk();
        $response->assertSee('action="https://hub.test/master-launch"', false);
        $response->assertSee('value="mlt1.', false);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));

        // data bersama / aplikasi lain tidak bisa diedit dari klien
        $this->get(route('master-launch.go', ['slug' => 'category', 'id' => $shared]))->assertForbidden();
    }

    public function test_klien_tanpa_kunci_memakai_link_lama(): void
    {
        config(['MasterCrudConfig.LAUNCH_PRIVATE_KEY' => null, 'test.allowed_permissions' => ['update_category']]);
        $this->actingAs($this->user);

        $this->get(route('master-launch.go', ['slug' => 'category']))->assertNotFound();
        $this->assertSame('https://hub.test/master/category/create', \Bangsamu\Master\Services\MasterEditLink::url('category'));
    }
}
