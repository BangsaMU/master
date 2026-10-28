<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\Concerns\CreatesMasterTables;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * API generik paket (get{tabel}byparams, get-{tabel}, sync-master) dulu tanpa auth: tamu bisa membaca tabel apa pun
 * termasuk password + api_token user, dan menimpa tabel master lokal lewat webhook sync-master.
 */
class MasterApiSecurityTest extends TestCase
{
    use CreatesMasterTables;

    private User $user;

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Budi',
            'email' => 'budi@meindo.test',
            'password' => bcrypt('rahasia'),
            'api_token' => md5('budi@meindo.test'),
            'remember_token' => 'remember-me',
        ]);
        DB::table('master_uom')->insert(['id' => 7, 'uom_code' => 'PCS', 'uom_name' => 'Pieces']);
    }

    public function test_tamu_ditolak_dropdown_select2(): void
    {
        $query = '?set[text]=name&set[field][]=email&set[field][]=password';

        $this->getJson('/api/getusersbyparams'.$query)->assertUnauthorized();
        $this->get('/api/getusersbyparams'.$query)->assertRedirect(route('login'));
        $this->getJson('/api/getuombyparams')->assertUnauthorized();
    }

    public function test_user_login_tetap_bisa_memakai_dropdown_select2(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/getusersbyparams?set[text][ - ]=name&set[text][]=email&set[field][]=email')
            ->assertOk()
            ->assertExactJson([['id' => $this->user->id, 'email' => 'budi@meindo.test', 'text' => 'Budi - budi@meindo.test']]);
    }

    public function test_set_field_bintang_tidak_mengeluarkan_kolom_rahasia(): void
    {
        $row = $this->actingAs($this->user)->getJson('/api/getusersbyparams?set[field]=*&set[text]=name')
            ->assertOk()
            ->json(0);

        $this->assertSame('budi@meindo.test', $row['email']);
        $this->assertArrayNotHasKey('password', $row);
        $this->assertArrayNotHasKey('api_token', $row);
        $this->assertArrayNotHasKey('remember_token', $row);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function kolomRahasiaProvider(): array
    {
        return [
            'set field' => ['set[field][]=password'],
            'set id' => ['set[id]=api_token'],
            'set text' => ['set[text]=remember_token'],
            'set text concat' => ['set[text][]=name&set[text][]=password'],
            'search (tebak hash lewat LIKE)' => ['search[password]=$2y'],
            'search huruf besar' => ['search[PASSWORD]=$2y'],
            'where' => ['where[api_token]=abc'],
            'find_in_set' => ['find_in_set[api_token]=abc'],
            'not_null' => ['not_null[]=remember_token'],
            'order' => ['order[column]=u.password'],
            'group' => ['group[]=password'],
            'join' => ['join[users.id]=api_token'],
        ];
    }

    #[DataProvider('kolomRahasiaProvider')]
    public function test_kolom_rahasia_ditolak_di_parameter_apa_pun(string $query): void
    {
        $this->actingAs($this->user)->getJson('/api/getusersbyparams?set[text]=name&'.$query)->assertForbidden();
    }

    public function test_pemisah_set_text_tidak_bisa_menyisipkan_sql(): void
    {
        $sisipan = "'),password,('";

        $this->actingAs($this->user)
            ->getJson('/api/getusersbyparams?'.http_build_query(['set' => ['text' => ['name', $sisipan => 'name', 'email']]]))
            ->assertOk()
            ->assertJsonPath('0.text', 'Budi-Budi'.$sisipan.'budi@meindo.test');
    }

    public function test_tabel_sistem_dan_tabel_di_luar_whitelist_ditolak(): void
    {
        $this->actingAs($this->user);

        $this->getJson('/api/getmigrationsbyparams?set[text]=migration')->assertForbidden();
        $this->getJson('/api/getsessionsbyparams?set[text]=id')->assertForbidden();

        config(['MasterCrudConfig.SELECT2_TABLES' => ['master_*']]);

        $this->getJson('/api/getmaster_uombyparams?set[text]=uom_name')->assertOk()->assertJsonPath('0.text', 'Pieces');
        $this->getJson('/api/getusersbyparams?set[text]=name')->assertForbidden();
        $this->getJson('/api/getmaster_uombyparams?set[text]=uom_name&join[users.id]=id')->assertForbidden();
    }

    public function test_get_tabel_wajib_token_dan_tanpa_kolom_rahasia(): void
    {
        $id = $this->user->id;
        config(['SsoConfig.main.KEY' => 'kunci-rahasia']);

        $this->getJson("/api/get-users/{$id}")->assertJsonPath('data.message', 'Data Not Found');
        $this->getJson("/api/get-users/{$id}?api_token=".md5("{$id}:salah"))->assertJsonPath('data.message', 'Data Not Found');

        $row = $this->getJson("/api/get-users/{$id}?api_token=".md5("{$id}:kunci-rahasia"))->json('data.0');
        $this->assertSame('budi@meindo.test', $row['email']);
        $this->assertArrayNotHasKey('password', $row);
        $this->assertArrayNotHasKey('api_token', $row);

        // KEY kosong: md5("{id}:") mudah ditebak, selalu ditolak
        config(['SsoConfig.main.KEY' => '']);
        $this->getJson("/api/get-users/{$id}?api_token=".md5("{$id}:"))->assertJsonPath('data.message', 'Data Not Found');
    }

    public function test_sync_master_tanpa_token_tidak_memakai_isi_rows_dari_request(): void
    {
        $payload = ['tabel' => 'uom', 'rows' => [['id' => 7, 'uom_code' => 'PCS', 'uom_name' => 'DIRETAS']]];

        // tanpa token: hanya tarik ulang dari DB master (di test tidak ada koneksi db_master), rows diabaikan
        $this->postJson('/api/sync-master/uom/7', $payload)->assertOk()->assertJsonPath('code', 503);
        $this->assertSame('Pieces', DB::table('master_uom')->where('id', 7)->value('uom_name'));

        $this->postJson('/api/sync-master/uom/abc', $payload)->assertJsonPath('code', 400);
        $this->postJson('/api/sync-master/users/1', $payload)->assertJsonPath('code', 400);
    }

    public function test_sync_master_dengan_token_valid_memakai_rows_dari_request(): void
    {
        config(['MasterConfig.SYNC_TOKEN' => 'token-hub']);
        $payload = ['tabel' => 'uom', 'rows' => [['id' => 7, 'uom_code' => 'PCS', 'uom_name' => 'Pcs baru']]];

        $this->postJson('/api/sync-master/uom/7', $payload, ['X-Master-Sync-Token' => 'salah'])->assertJsonPath('code', 503);
        $this->assertSame('Pieces', DB::table('master_uom')->where('id', 7)->value('uom_name'));

        $this->postJson('/api/sync-master/uom/7', $payload, ['X-Master-Sync-Token' => 'token-hub'])->assertOk();
        $this->assertSame('Pcs baru', DB::table('master_uom')->where('id', 7)->value('uom_name'));
    }
}
