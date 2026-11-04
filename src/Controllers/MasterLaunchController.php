<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Services\MasterAccess;
use Bangsamu\Master\Services\MasterEditLink;
use Bangsamu\Master\Services\MasterLaunchToken;
use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Support\MasterModules;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * "Edit di Master Data" tanpa login ulang:
 *  - go()      di aplikasi klien: cek izin + pemilik baris, buat launch token, kirim POST otomatis ke hub;
 *  - receive() di hub (MASTER_HUB=true): verifikasi token, login user, simpan session delegasi, buka halaman edit;
 *  - back()    di hub: kembali ke aplikasi asal.
 */
class MasterLaunchController extends Controller
{
    public function __construct(
        protected MasterScope $scope,
        protected MasterAccess $access,
        protected MasterLaunchToken $tokens,
    ) {}

    public function go(Request $request, string $slug, ?string $id = null)
    {
        if (! MasterModules::exists($slug) || ! MasterEditLink::enabled() || ! $this->tokens->canIssue()) {
            abort(404);
        }

        $table = MasterModules::table($slug);
        if ($id !== null) {
            $row = Schema::hasTable($table) ? DB::table($table)->where('id', $id)->first() : null;
            $this->access->authorize($slug, 'update', $row ?? $id);
        } else {
            $this->access->authorize($slug, 'create');
        }

        $user = $request->user();
        $companyId = $this->scope->userCompanyId($user);
        $token = $this->tokens->issue([
            'sub' => $user->email,
            'name' => $user->name ?? null,
            'cid' => $companyId,
            'cco' => $this->scope->companyCode($companyId),
            'all_co' => $this->access->canManageAllCompanies(),
            'mods' => $this->delegatedModules(),
            'next' => '/master/'.$slug.($id !== null ? '/'.rawurlencode($id).'/edit' : '/create'),
            'back' => url()->previous() !== url()->current() ? url()->previous() : url('master/'.$slug),
        ]);

        return response()
            ->view('master::launch.redirect', [
                'action' => rtrim((string) config('MasterCrudConfig.MASTER_EDIT_URL'), '/').'/master-launch',
                'token' => $token,
            ])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Modul ber-scope di aplikasi ini beserta aksi tulis yang dimiliki user (dibatasi lagi oleh allowlist di hub).
     *
     * @return array<string, list<string>>
     */
    protected function delegatedModules(): array
    {
        $mods = [];
        foreach (array_keys(MasterModules::MODULES) as $slug) {
            $table = MasterModules::table($slug);
            if (! $this->scope->isScoped($table)) {
                continue;
            }
            $abilities = array_values(array_filter(['create', 'update', 'delete'], fn ($ability) => $this->access->hasPermission($slug, $ability)));
            if ($abilities !== []) {
                $mods[$table] = $abilities;
            }
        }

        return $mods;
    }

    public function receive(Request $request)
    {
        if (! $this->scope->isHub()) {
            abort(404);
        }

        $denied = null;
        try {
            $payload = $this->tokens->verify((string) $request->input('token'));
            [$user, $app] = $this->resolveUserAndApp($payload);
            $mods = $this->allowedModules((string) $payload['iss'], (array) ($payload['mods'] ?? []));
            $denied = $this->requestedModuleDenial($payload, $mods, $app);
            if ($denied === null && $mods === []) {
                throw new RuntimeException('tidak ada modul master yang boleh didelegasikan');
            }
        } catch (RuntimeException $e) {
            Log::warning('master-launch ditolak: '.$e->getMessage(), [
                'ip' => $request->ip(),
                'iss' => $payload['iss'] ?? null,
                'sub' => $payload['sub'] ?? null,
            ]);

            abort(403, 'Link edit master data tidak valid atau sudah kedaluwarsa. Buka lagi dari aplikasi asal. ('.$e->getMessage().')');
        }

        // modul tujuan sah tetapi tidak didelegasikan: tolak dengan alasan, jangan dialihkan diam-diam ke modul lain
        if ($denied !== null) {
            Log::warning('master-launch ditolak: modul tujuan tidak didelegasikan', [
                'ip' => $request->ip(),
                'iss' => $payload['iss'],
                'sub' => $payload['sub'],
                'next' => $payload['next'] ?? null,
                'mods' => array_keys($mods),
            ]);

            abort(403, $denied);
        }

        if (Auth::check() && strcasecmp((string) Auth::user()->email, (string) $user->email) !== 0) {
            Auth::logout();
            $request->session()->invalidate();
        }
        if (! Auth::check()) {
            Auth::login($user);
        }
        $request->session()->regenerate();

        $companyId = (int) ($payload['cid'] ?? 0);
        $request->session()->put(MasterScope::SESSION_KEY, [
            'app_code' => (string) $payload['iss'],
            'app_name' => $app->name ?? null,
            'company_id' => $companyId,
            'company_code' => $this->scope->companyCode($companyId) ?? ($payload['cco'] ?? null),
            'all_co' => (bool) ($payload['all_co'] ?? false),
            'mods' => $mods,
            'back' => $this->safeBackUrl($payload['back'] ?? null, $app),
            'exp' => time() + max(1, (int) config('MasterCrudConfig.LAUNCH_SESSION_MINUTES', 120)) * 60,
        ]);

        Log::info('master-launch sukses', ['iss' => $payload['iss'], 'sub' => $user->email, 'cid' => $companyId, 'mods' => array_keys($mods)]);

        return redirect($this->safeNext((string) ($payload['next'] ?? ''), $mods));
    }

    public function back(Request $request)
    {
        $delegation = $this->scope->delegation();

        return $delegation !== null && filled($delegation['back'] ?? null)
            ? redirect()->away($delegation['back'])
            : redirect('/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: \Illuminate\Contracts\Auth\Authenticatable, 1: object}
     */
    protected function resolveUserAndApp(array $payload): array
    {
        $app = Schema::hasTable('master_apps')
            ? DB::table('master_apps')->where('app_code', $payload['iss'])->whereNull('deleted_at')->first()
            : null;
        if ($app === null) {
            throw new RuntimeException('aplikasi asal tidak terdaftar');
        }

        $model = config('auth.providers.users.model');
        $user = $model::query()->where('email', $payload['sub'])->first();
        if ($user === null) {
            throw new RuntimeException('user tidak ditemukan');
        }
        if ((isset($user->deleted_at) && $user->deleted_at !== null) || (isset($user->is_active) && ! (int) $user->is_active)) {
            throw new RuntimeException('user nonaktif');
        }
        // user harus terdaftar di aplikasi asal (master_user.list_apps_id berisi master_apps.id)
        if (array_key_exists('list_apps_id', $user->getAttributes())) {
            $apps = array_map('trim', explode(',', (string) $user->list_apps_id));
            if (! in_array((string) $app->id, $apps, true)) {
                throw new RuntimeException('user tidak terdaftar di aplikasi '.$payload['iss']);
            }
        }

        return [$user, $app];
    }

    /**
     * Irisan modul dari token dengan allowlist aplikasi (default: semua tabel ber-scope di hub); tabel global tidak
     * pernah didelegasikan.
     *
     * @param  array<string, mixed>  $claimed
     * @return array<string, list<string>>
     */
    protected function allowedModules(string $appCode, array $claimed): array
    {
        $allowlist = $this->tokens->allowedTables($appCode);
        $mods = [];
        foreach ($claimed as $table => $abilities) {
            if (! is_string($table) || MasterModules::slugForTable($table) === null || ! $this->scope->isScoped($table)) {
                continue;
            }
            if ($allowlist !== null && ! in_array($table, $allowlist, true)) {
                continue;
            }
            $abilities = array_values(array_intersect(['create', 'update', 'delete'], (array) $abilities));
            if ($abilities !== []) {
                $mods[$table] = $abilities;
            }
        }

        return $mods;
    }

    /**
     * Halaman tujuan hanya path modul master yang didelegasikan. Path lain (URL luar, path diubah) jatuh ke modul
     * delegasi pertama; modul sah yang tidak didelegasikan sudah ditolak lebih dulu oleh requestedModuleDenial().
     *
     * @param  array<string, list<string>>  $mods
     */
    protected function safeNext(string $next, array $mods): string
    {
        $slug = $this->requestedSlug($next);
        if ($slug !== null && isset($mods[MasterModules::table($slug)])) {
            return $next;
        }

        $slug = MasterModules::slugForTable((string) array_key_first($mods));

        return '/master/'.$slug;
    }

    /** slug modul master pada path tujuan token (/master/{slug}, /create, /{id}/edit), null bila bukan path modul */
    protected function requestedSlug(string $next): ?string
    {
        if (preg_match('#^/master/([a-z-]+)(?:/create|/[0-9]+/edit)?$#', $next, $m) && MasterModules::exists($m[1])) {
            return $m[1];
        }

        return null;
    }

    /**
     * Alasan penolakan untuk user bila halaman tujuan token adalah modul master yang sah tetapi tidak ikut
     * didelegasikan (dulu user dialihkan tanpa pesan ke modul delegasi pertama). Null bila modulnya didelegasikan atau
     * tujuan bukan path modul.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $mods
     */
    protected function requestedModuleDenial(array $payload, array $mods, object $app): ?string
    {
        $slug = $this->requestedSlug((string) ($payload['next'] ?? ''));
        if ($slug === null) {
            return null;
        }

        $table = MasterModules::table($slug);
        if (isset($mods[$table])) {
            return null;
        }

        $appCode = (string) $payload['iss'];
        $module = ucwords(str_replace('-', ' ', $slug));
        $origin = filled($app->name ?? null) ? $app->name.' ('.$appCode.')' : $appCode;
        $claimed = is_array($payload['mods'] ?? null) ? (array) ($payload['mods'][$table] ?? []) : [];

        if (! $this->scope->isScoped($table)) {
            return "Data {$module} adalah data global Master Data dan tidak dapat diubah dari {$origin}. Hubungi tim Master Data.";
        }
        if (array_intersect(['create', 'update', 'delete'], $claimed) === []) {
            return "Akun Anda tidak punya izin mengubah data {$module} di {$origin}.";
        }

        return "Modul {$module} belum diizinkan diubah dari {$origin}. Minta admin Master Data menambahkan {$table} ke daftar "
            ."modul yang boleh didelegasikan {$appCode} (Setting → Dashboard Settings → master_launch).";
    }

    /** URL kembali hanya ke host aplikasi asal (master_apps.app_url) */
    protected function safeBackUrl(mixed $back, object $app): ?string
    {
        if (! is_string($back) || ! preg_match('#^https?://#i', $back)) {
            return null;
        }
        $backHost = strtolower((string) parse_url($back, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) ($app->app_url ?? ''), PHP_URL_HOST));

        return $backHost !== '' && $backHost === $appHost ? $back : null;
    }
}
