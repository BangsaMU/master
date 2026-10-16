<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Services\AnnotationLaunchTokenService;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Encryption\Encrypter;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AnnotationLaunchTokenTest extends TestCase
{
    private const FILE_URL = 'http://localhost/storage/pdf/Req-Slip_20000-SPB-BTN-GA-0010160.pdf?version=2';

    private const FILE_NAME = 'Req-Slip_20000-SPB-BTN-GA-0010160';

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    private function makeUser(string $email = 'budi@meindo.test'): User
    {
        return User::create(['name' => 'Budi', 'email' => $email, 'api_token' => 'api-'.md5($email)]);
    }

    /**
     * Buat session yang tersimpan di session store (file) dan login sebagai $user, seperti login sungguhan.
     */
    private function loggedInSession(User $user): string
    {
        $store = new Store(config('session.cookie'), app('session')->driver()->getHandler());
        $store->start();
        $store->put(Auth::guard('web')->getName(), $user->id);
        $store->save();

        return $store->getId();
    }

    private function enableLaunchMode(bool $acceptLegacy = true): void
    {
        config([
            'AnnotationConfig.launch.TOKEN_MODE' => 'launch',
            'AnnotationConfig.launch.ACCEPT_LEGACY_TOKEN' => $acceptLegacy,
        ]);
    }

    private function tokens(): AnnotationLaunchTokenService
    {
        return app(AnnotationLaunchTokenService::class);
    }

    private function issueToken(User $user, ?string $sessionId = null): string
    {
        return $this->tokens()->issue($user, self::FILE_URL, $sessionId ?? $this->loggedInSession($user), 'web');
    }

    // ---------- mode legacy (default) tidak berubah ----------

    public function test_default_config_is_legacy_mode(): void
    {
        $this->assertSame('legacy', config('AnnotationConfig.launch.TOKEN_MODE'));
        $this->assertTrue((bool) config('AnnotationConfig.launch.ACCEPT_LEGACY_TOKEN'));
        // key 'main' milik app tidak tertimpa config paket
        $this->assertSame('http://meindo-annotation.test', config('AnnotationConfig.main.URL'));
    }

    public function test_legacy_md5_token_still_works_with_same_json_shape(): void
    {
        $user = $this->makeUser();
        $token = md5($user->email);

        $response = $this->getJson('/api/getcurrentuser?token='.$token.'&file_name='.self::FILE_NAME);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://meindo-annotation.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertJsonStructure([
                'validate_session',
                'success',
                'user' => ['id', 'user', 'name', 'permission', 'routing_id', 'signature_url', 'paraf_url'],
                'session' => ['logged_in', 'user'],
            ])
            ->assertJson([
                'validate_session' => true,
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'user' => $user->email,
                    'permission' => true,
                    'signature_url' => url('api/getSignature?token='.$token),
                    'paraf_url' => url('api/getParaf?token='.$token),
                ],
            ]);
    }

    public function test_legacy_api_token_still_works(): void
    {
        $user = $this->makeUser();

        $this->getJson('/api/getcurrentuser?token='.$user->api_token)
            ->assertOk()
            ->assertJsonPath('user.user', $user->email);
    }

    public function test_unknown_or_empty_token_is_rejected(): void
    {
        $this->makeUser();

        $this->getJson('/api/getcurrentuser?token='.md5('orang-lain@meindo.test'))->assertStatus(401);
        $this->getJson('/api/getcurrentuser')->assertStatus(401);
        // sebelumnya token kosong bisa cocok ke user dengan api_token null
        User::create(['name' => 'Tanpa Token', 'email' => 'null@meindo.test']);
        $this->get('/api/getSignature')->assertStatus(401);
    }

    public function test_accept_legacy_flag_is_ignored_in_legacy_mode(): void
    {
        $user = $this->makeUser();
        config(['AnnotationConfig.launch.ACCEPT_LEGACY_TOKEN' => false]);

        $this->getJson('/api/getcurrentuser?token='.md5($user->email))->assertOk();
    }

    public function test_helper_returns_legacy_viewer_url_in_legacy_mode(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->assertSame(
            'http://meindo-annotation.test/viewer?app_id=APP27&file_url='.base64_encode(self::FILE_URL)
                .'&token='.md5($user->email).'&callback='.base64_encode(url('api/setAnnotationId')),
            annotation_launch_url(self::FILE_URL)
        );
        $this->assertNull(annotation_launch_url(null));
    }

    // ---------- route annotation/open ----------

    public function test_helper_returns_open_route_in_launch_mode(): void
    {
        $this->enableLaunchMode();

        $url = annotation_launch_url('/storage/pdf/a.pdf');

        $this->assertStringStartsWith('http://localhost/annotation/open?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('http://localhost/storage/pdf/a.pdf', $query['file_url']);
    }

    public function test_open_redirects_guest_to_login_and_remembers_intended_url(): void
    {
        $this->enableLaunchMode();
        $openUrl = 'http://localhost/annotation/open?file_url='.urlencode(self::FILE_URL);

        $this->get($openUrl)
            ->assertRedirect('http://localhost/login')
            ->assertSessionHas('url.intended', $openUrl);
    }

    public function test_open_redirects_logged_in_user_to_viewer_with_launch_token(): void
    {
        $this->enableLaunchMode();
        $user = $this->makeUser();
        $sessionId = $this->loggedInSession($user);

        $response = $this->withCookie(config('session.cookie'), $sessionId)
            ->get('/annotation/open?file_url='.urlencode(self::FILE_URL));

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('http://meindo-annotation.test/viewer?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('APP27', $query['app_id']);
        $this->assertSame(self::FILE_URL, base64_decode($query['file_url']));
        $this->assertSame('http://localhost/api/setAnnotationId', base64_decode($query['callback']));
        $this->assertStringStartsWith(AnnotationLaunchTokenService::PREFIX, $query['token']);
        // aman disambung ke URL tanpa encode oleh viewer JS
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $query['token']);

        $payload = $this->tokens()->decode($query['token']);
        $this->assertSame($sessionId, $payload['sid']);
        $this->assertSame($user->id, $payload['uid']);
        $this->assertSame(self::FILE_URL, $payload['f']);
    }

    public function test_open_rejects_file_url_on_other_host(): void
    {
        $this->enableLaunchMode();
        $user = $this->makeUser();

        $this->withCookie(config('session.cookie'), $this->loggedInSession($user))
            ->get('/annotation/open?file_url='.urlencode('http://evil.test/a.pdf'))
            ->assertStatus(422);

        config(['AnnotationConfig.launch.ALLOWED_HOSTS' => 'files.meindo.test, evil.test']);
        $this->withCookie(config('session.cookie'), $this->loggedInSession($user))
            ->get('/annotation/open?file_url='.urlencode('http://evil.test/a.pdf'))
            ->assertStatus(302);
    }

    // ---------- validasi token launch ----------

    public function test_valid_launch_token_is_accepted_without_cookie(): void
    {
        $this->enableLaunchMode();
        $user = $this->makeUser();
        $token = $this->issueToken($user);

        $this->getJson('/api/getcurrentuser?token='.$token.'&file_name='.self::FILE_NAME)
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://meindo-annotation.test')
            ->assertJson([
                'validate_session' => false,
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'user' => $user->email,
                    'permission' => true,
                    'signature_url' => url('api/getSignature?token='.$token),
                    'paraf_url' => url('api/getParaf?token='.$token),
                ],
            ]);
    }

    public function test_launch_token_also_accepted_without_file_name(): void
    {
        $user = $this->makeUser();

        $this->getJson('/api/getcurrentuser?token='.$this->issueToken($user))->assertOk();
    }

    public function test_launch_token_for_other_file_is_rejected(): void
    {
        $this->enableLaunchMode();
        $token = $this->issueToken($this->makeUser());

        $this->getJson('/api/getcurrentuser?token='.$token.'&file_name=Req-Slip_LAIN')->assertStatus(401);
    }

    public function test_expired_launch_token_is_rejected(): void
    {
        $this->enableLaunchMode();
        config(['AnnotationConfig.launch.TOKEN_TTL' => 30]);
        $token = $this->issueToken($this->makeUser());

        $this->travel(31)->minutes();

        $this->getJson('/api/getcurrentuser?token='.$token)->assertStatus(401);
    }

    public function test_tampered_launch_token_is_rejected(): void
    {
        $this->enableLaunchMode();
        $token = $this->issueToken($this->makeUser());

        $tampered = substr($token, 0, -6).(substr($token, -6, 1) === 'A' ? 'B' : 'A').substr($token, -5);
        $this->getJson('/api/getcurrentuser?token='.$tampered)->assertStatus(401);
        $this->getJson('/api/getcurrentuser?token='.AnnotationLaunchTokenService::PREFIX.'bukan-token')->assertStatus(401);

        // dibuat dengan APP_KEY lain (dipalsukan)
        $other = new Encrypter(str_repeat('x', 32), 'AES-256-CBC');
        $forged = AnnotationLaunchTokenService::PREFIX.rtrim(strtr($other->encryptString(json_encode([
            'v' => 1, 'uid' => 1, 'em' => 'budi@meindo.test', 'f' => self::FILE_URL,
            'sid' => str_repeat('a', 40), 'g' => 'web', 'exp' => time() + 3600,
        ])), '+/', '-_'), '=');
        $this->getJson('/api/getcurrentuser?token='.$forged)->assertStatus(401);
    }

    public function test_launch_token_is_rejected_after_logout(): void
    {
        $this->enableLaunchMode();
        $user = $this->makeUser();
        $sessionId = $this->loggedInSession($user);
        $token = $this->issueToken($user, $sessionId);

        $this->getJson('/api/getcurrentuser?token='.$token)->assertOk();

        // logout Laravel meng-invalidate session: data di store dihapus
        app('session')->driver()->getHandler()->destroy($sessionId);

        $this->getJson('/api/getcurrentuser?token='.$token)->assertStatus(401);
        $this->get('/api/getSignature?token='.$token)->assertStatus(401);
    }

    public function test_launch_token_is_rejected_when_session_belongs_to_other_user(): void
    {
        $this->enableLaunchMode();
        $budi = $this->makeUser();
        $ani = $this->makeUser('ani@meindo.test');

        $token = $this->issueToken($budi, $this->loggedInSession($ani));

        $this->getJson('/api/getcurrentuser?token='.$token)->assertStatus(401);
    }

    public function test_legacy_token_rejected_in_launch_mode_when_not_accepted(): void
    {
        $user = $this->makeUser();
        $token = md5($user->email);

        $this->enableLaunchMode(acceptLegacy: true);
        $this->getJson('/api/getcurrentuser?token='.$token)
            ->assertOk()
            ->assertJsonPath('validate_session', true);

        $this->enableLaunchMode(acceptLegacy: false);
        $this->getJson('/api/getcurrentuser?token='.$token)->assertStatus(401);
        $this->getJson('/api/getcurrentuser?token='.$user->api_token)->assertStatus(401);
        $this->get('/api/getSignature?token='.$token)->assertStatus(401);
        $this->get('/api/getParaf?token='.$token)->assertStatus(401);

        // token launch tetap jalan
        $this->getJson('/api/getcurrentuser?token='.$this->issueToken($user))->assertOk();
    }

    public function test_signature_and_paraf_accept_valid_launch_token(): void
    {
        $this->enableLaunchMode(acceptLegacy: false);
        $token = $this->issueToken($this->makeUser());

        // lolos autentikasi; user belum punya tanda tangan / paraf -> 403 seperti sebelumnya
        $this->get('/api/getSignature?token='.$token)->assertStatus(403);
        $this->get('/api/getParaf?token='.$token)->assertStatus(403);
    }

    public function test_file_name_normalization(): void
    {
        $this->assertSame(
            AnnotationLaunchTokenService::normalizeFileName(self::FILE_URL),
            AnnotationLaunchTokenService::normalizeFileName(self::FILE_NAME)
        );
        $this->assertSame('my file', AnnotationLaunchTokenService::normalizeFileName('http://localhost/a/My%20File.PDF'));
    }
}
