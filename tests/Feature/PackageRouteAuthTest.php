<?php

namespace Bangsamu\Master\Tests\Feature;

use App\Models\User;
use Bangsamu\Master\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Route paket yang dulu terbuka tanpa login: /optimize, /composer (tamu bisa ?update, ?require=vendor/paket, ?remove),
 * dan halaman uji mud-*. Kini /optimize + /composer wajib login + ability MasterConfig.maintenance.ability.
 */
class PackageRouteAuthTest extends TestCase
{
    /** @var list<string> */
    private array $artisanCalls = [];

    protected function defineRoutes($router)
    {
        Route::get('/login', fn () => 'login page')->name('login');
    }

    protected function useRootAbility($app): void
    {
        $app['config']->set('MasterConfig.maintenance.ability', 'is_root');
    }

    private function user(): User
    {
        return User::create(['name' => 'Budi', 'email' => 'budi@meindo.test']);
    }

    /**
     * Artisan::call di closure /optimize dicatat, tidak benar-benar menjalankan optimize (menyimpan cache config/route
     * di skeleton testbench dan merusak test lain). Dengan $allow false, panggilan apa pun menggagalkan test.
     */
    private function fakeArtisan(bool $allow): void
    {
        $calls = &$this->artisanCalls;

        Artisan::swap(new class($allow, $calls)
        {
            /** @param list<string> $calls */
            public function __construct(private bool $allow, private array &$calls) {}

            public function call(string $command, array $parameters = []): int
            {
                if (! $this->allow) {
                    throw new RuntimeException("Artisan::call('{$command}') tidak boleh terpanggil di test ini.");
                }

                $this->calls[] = $command;

                return 0;
            }

            public function output(): string
            {
                return 'palsu';
            }
        });
    }

    /** @return list<string> */
    private function middlewareOf(string $uri): array
    {
        return Route::getRoutes()->match(Request::create($uri))->gatherMiddleware();
    }

    /**
     * Tanpa parameter /composer hanya menampilkan bantuan, jadi aman dipanggil di test. Sebelum perbaikan tamu
     * mendapat 404 dari closure (bukan redirect login), sedangkan ?update/?require langsung menjalankan composer.
     *
     * @return array<string, array{0: string}>
     */
    public static function guestBlockedUrls(): array
    {
        return [
            'optimize' => ['/optimize'],
            'optimize clear' => ['/optimize?clear=1'],
            'composer' => ['/composer'],
            'mud-master' => ['/mud-master'],
            'master/mud-view' => ['/master/mud-view'],
        ];
    }

    #[DataProvider('guestBlockedUrls')]
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->fakeArtisan(allow: false);

        $this->get($url)->assertRedirect(route('login'));
    }

    /**
     * Perintah composer sungguhan tidak dipanggil lewat HTTP di test (bila route terbuka lagi, composer benar-benar
     * jalan di skeleton testbench); cukup dipastikan route yang cocok memasang auth + ability.
     *
     * @return array<string, array{0: string}>
     */
    public static function composerCommandUrls(): array
    {
        return [
            'update' => ['/composer?update=1'],
            'install' => ['/composer?install=1'],
            'require' => ['/composer?require=vendor/paket-asing'],
            'remove' => ['/composer?remove=bangsamu/master'],
            'dump-autoload' => ['/composer?autoload=1'],
            'optimize' => ['/optimize'],
        ];
    }

    #[DataProvider('composerCommandUrls')]
    public function test_maintenance_routes_use_auth_and_admin_ability(string $url): void
    {
        $middleware = $this->middlewareOf($url);

        $this->assertContains('auth', $middleware);
        $this->assertContains('can:is_admin', $middleware);
    }

    #[DefineEnvironment('useRootAbility')]
    public function test_ability_follows_config(): void
    {
        $this->assertContains('can:is_root', $this->middlewareOf('/composer?update=1'));
        $this->assertNotContains('can:is_admin', $this->middlewareOf('/optimize'));
    }

    public function test_logged_in_user_without_ability_is_forbidden(): void
    {
        $this->fakeArtisan(allow: false);
        $this->actingAs($this->user());

        $this->get('/optimize')->assertForbidden();
        $this->get('/optimize?clear=1')->assertForbidden();
        $this->get('/composer')->assertForbidden();
    }

    public function test_admin_can_still_use_maintenance_routes(): void
    {
        $this->fakeArtisan(allow: true);
        Gate::define('is_admin', fn (): bool => true);
        $this->actingAs($this->user());

        $this->get('/optimize?clear=1')->assertOk()->assertSee('Optimize successfully');
        $this->get('/optimize')->assertOk();
        $this->assertSame(['optimize:clear', 'optimize'], $this->artisanCalls);

        // tanpa parameter hanya menampilkan bantuan, tidak menjalankan composer
        $this->get('/composer')->assertOk()->assertSee('Composer Management Route');
    }

    public function test_logged_in_user_can_open_mud_pages(): void
    {
        $this->actingAs($this->user());

        $this->get('/master/mud-view')->assertOk();

        // closure mud-master memakai echo (keluaran langsung), bukan isi response
        $this->expectOutputRegex('/Hello from the master package!/');
        $this->get('/mud-master')->assertOk();
    }
}
