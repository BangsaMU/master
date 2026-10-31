<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pemilik data master (scope) = (app_code, company_id).
 *
 *  - app_code GLOBAL_APP_CODE (APP03, aplikasi Master Data) + company 0 = data bersama, dipakai semua aplikasi;
 *  - app_code APPxx + company 0 = milik aplikasi APPxx, berlaku semua company di aplikasi itu;
 *  - app_code APPxx + company N = milik aplikasi APPxx khusus company N (master_company.id).
 *
 * Tabel ber-scope ditentukan dari kolomnya (app_code, company_id), bukan daftar tetap: tabel tanpa kolom itu tetap
 * global. Tabel dinaikkan dengan `php artisan master:scope-upgrade`.
 * Di aplikasi Master Data (MASTER_HUB=true) user yang datang dari aplikasi lain lewat launch token membawa session
 * delegasi: pemiliknya diambil dari aplikasi + company asal (lihat MasterLaunchToken).
 */
class MasterScope
{
    public const SESSION_KEY = 'master_delegasi';

    public const OWNER_APP_FIELD = 'owner_app_code';

    public const OWNER_COMPANY_FIELD = 'owner_company_id';

    /** tabel yang sebelum v3 sudah unik per app_code (index unik lokal aplikasi lama juga per app_code) */
    public const LEGACY_UNIQUE_PER_APP = ['master_department'];

    /** @var array<string, array{app_code: bool, company_id: bool}> */
    protected static array $columns = [];

    /** @var array<string, int> */
    protected static array $companies = [];

    public function appCode(): string
    {
        return (string) config('SsoConfig.main.APP_CODE', 'APP03');
    }

    public function globalAppCode(): string
    {
        return (string) config('MasterCrudConfig.GLOBAL_APP_CODE', 'APP03');
    }

    /** aplikasi ini aplikasi Master Data (hub): penerima launch token, tempat edit data master */
    public function isHub(): bool
    {
        return (bool) config('MasterCrudConfig.MASTER_HUB', false);
    }

    public function hasAppCode(string $table): bool
    {
        return $this->columns($table)['app_code'];
    }

    public function hasCompany(string $table): bool
    {
        return $this->columns($table)['company_id'];
    }

    /** tabel punya pemilik per aplikasi (kolom app_code) */
    public function isScoped(string $table): bool
    {
        return $this->hasAppCode($table);
    }

    /**
     * @return array{app_code: bool, company_id: bool}
     */
    protected function columns(string $table): array
    {
        $key = DB::getDefaultConnection().'.'.$table;
        if (! isset(self::$columns[$key])) {
            try {
                $listing = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
            } catch (Throwable $e) {
                $listing = [];
            }
            self::$columns[$key] = [
                'app_code' => in_array('app_code', $listing, true),
                'company_id' => in_array('company_id', $listing, true),
            ];
        }

        return self::$columns[$key];
    }

    /** kosongkan memo kolom/company (setelah master:scope-upgrade, di test) */
    public static function flush(): void
    {
        self::$columns = [];
        self::$companies = [];
    }

    /**
     * Company user login (master_company.id). Urutan: MasterCrudConfig.USER_COMPANY_RESOLVER (nama fungsi / class
     * invokable), fungsi app resolve_user_company_id($user) (SPB), user_details / master_user_details field_key
     * company_id, lalu MASTER_COMPANY_ID.
     */
    public function userCompanyId(?Authenticatable $user = null): int
    {
        $user ??= auth()->user();
        if ($user === null) {
            return (int) config('MasterCrudConfig.MASTER_COMPANY_ID', 1);
        }

        $memo = (string) ($user->email ?? $user->getAuthIdentifier());
        if (isset(self::$companies[$memo])) {
            return self::$companies[$memo];
        }

        return self::$companies[$memo] = $this->resolveCompany($user);
    }

    protected function resolveCompany(Authenticatable $user): int
    {
        $resolver = config('MasterCrudConfig.USER_COMPANY_RESOLVER');
        if (is_string($resolver) && $resolver !== '') {
            if (function_exists($resolver)) {
                return (int) $resolver($user);
            }
            if (class_exists($resolver)) {
                return (int) app($resolver)($user);
            }
        }

        if (function_exists('resolve_user_company_id')) {
            try {
                $company = resolve_user_company_id($user);
                if (filled($company)) {
                    return (int) $company;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        foreach (['user_details', 'master_user_details'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $company = DB::table($table)
                ->where(function ($query) use ($user) {
                    $query->where('user_email', $user->email ?? '')->orWhere('user_id', $user->getAuthIdentifier());
                })
                ->where('field_key', 'company_id')
                ->when(Schema::hasColumn($table, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->value('field_value');
            if (filled($company)) {
                return (int) $company;
            }
        }

        return (int) config('MasterCrudConfig.MASTER_COMPANY_ID', 1);
    }

    /**
     * Session delegasi yang masih berlaku (hanya di hub), null bila tidak ada.
     *
     * @return array{app_code: string, app_name: ?string, company_id: int, company_code: ?string, all_co: bool, mods: array<string, list<string>>, back: ?string, exp: int}|null
     */
    public function delegation(): ?array
    {
        if (! $this->isHub() || ! app()->bound('session') || ! app('request')->hasSession()) {
            return null;
        }

        $delegation = session(self::SESSION_KEY);
        if (! is_array($delegation) || empty($delegation['app_code']) || (int) ($delegation['exp'] ?? 0) < time()) {
            return null;
        }

        return $delegation;
    }

    /**
     * Pemilik baris baru untuk tabel ini (hanya kolom yang ada).
     *
     * @return array{app_code?: string, company_id?: int}
     */
    public function ownerForNew(string $table, ?Request $request = null): array
    {
        $request ??= request();
        $owner = $this->resolveNewOwner($request);

        return $this->onlyExistingColumns($table, $owner);
    }

    /**
     * @return array{app_code: string, company_id: int}
     */
    protected function resolveNewOwner(Request $request): array
    {
        $access = app(MasterAccess::class);
        $wantsAllCompanies = $request->has(self::OWNER_COMPANY_FIELD) && (int) $request->input(self::OWNER_COMPANY_FIELD) === 0;

        if ($delegation = $this->delegation()) {
            return [
                'app_code' => (string) $delegation['app_code'],
                'company_id' => $wantsAllCompanies && ! empty($delegation['all_co']) ? 0 : (int) $delegation['company_id'],
            ];
        }

        if ($this->isHub()) {
            // tim master-data memilih pemilik di form; default data bersama
            return [
                'app_code' => $request->filled(self::OWNER_APP_FIELD) ? strtoupper((string) $request->input(self::OWNER_APP_FIELD)) : $this->globalAppCode(),
                'company_id' => (int) $request->input(self::OWNER_COMPANY_FIELD, 0),
            ];
        }

        return [
            'app_code' => $this->appCode(),
            'company_id' => $wantsAllCompanies && $access->canManageAllCompanies() ? 0 : $this->userCompanyId(),
        ];
    }

    /**
     * Pemilik baris yang diubah: tetap pemilik lama, kecuali is_admin mengisi field pemilik di form.
     *
     * @return array{app_code?: string, company_id?: int}
     */
    public function ownerForUpdate(string $table, object $row, ?Request $request = null): array
    {
        $request ??= request();
        $owner = $this->ownerOf($row);

        if (app(MasterAccess::class)->isAdmin()) {
            if ($request->filled(self::OWNER_APP_FIELD)) {
                $owner['app_code'] = strtoupper((string) $request->input(self::OWNER_APP_FIELD));
            }
            if ($request->has(self::OWNER_COMPANY_FIELD) && $request->input(self::OWNER_COMPANY_FIELD) !== '') {
                $owner['company_id'] = (int) $request->input(self::OWNER_COMPANY_FIELD);
            }
        }

        return $this->onlyExistingColumns($table, $owner);
    }

    /**
     * @return array{app_code: ?string, company_id: int}
     */
    public function ownerOf(object|array $row): array
    {
        $row = (object) $row;

        return [
            'app_code' => isset($row->app_code) ? (string) $row->app_code : null,
            'company_id' => (int) ($row->company_id ?? 0),
        ];
    }

    /**
     * Gabungkan pemilik ke data simpan (hanya kolom yang ada di tabel).
     *
     * @param  array<string, mixed>  $data
     * @param  array{app_code?: ?string, company_id?: int}  $owner
     * @return array<string, mixed>
     */
    public function stamp(string $table, array $data, array $owner): array
    {
        return array_merge($data, $this->onlyExistingColumns($table, $owner));
    }

    /**
     * @param  array{app_code?: ?string, company_id?: int}  $owner
     * @return array{app_code?: string, company_id?: int}
     */
    protected function onlyExistingColumns(string $table, array $owner): array
    {
        $result = [];
        if ($this->hasAppCode($table) && filled($owner['app_code'] ?? null)) {
            $result['app_code'] = (string) $owner['app_code'];
        }
        if ($this->hasCompany($table) && array_key_exists('company_id', $owner)) {
            $result['company_id'] = (int) $owner['company_id'];
        }

        return $result;
    }

    /**
     * Kode tabel ini unik per pemilik (MasterCrudConfig.UNIQUE_PER_OWNER berisi tabel itu atau "*"). Selama transisi
     * (default kosong) kode tetap unik global (department: per app_code seperti v2.4), supaya salinan lokal aplikasi
     * lama yang masih ber-index unik global tidak saling menimpa saat sinkron.
     */
    public function uniquePerOwner(string $table): bool
    {
        $tables = (array) config('MasterCrudConfig.UNIQUE_PER_OWNER', []);

        return $this->hasAppCode($table) && (in_array('*', $tables, true) || in_array($table, $tables, true));
    }

    /**
     * Rule unik kolom kode: berpasangan dengan pemiliknya bila uniquePerOwner() (kode JKT boleh sama di dua aplikasi /
     * company), selain itu unik global.
     *
     * @param  array{app_code?: ?string, company_id?: int}  $owner
     */
    public function uniqueRule(string $table, string $column, array $owner, int|string|null $ignoreId = null): Unique
    {
        $owner = $this->onlyExistingColumns($table, $owner);
        if (! $this->uniquePerOwner($table)) {
            // transisi: unik global, kecuali tabel yang sejak v2.4 sudah unik per aplikasi (department)
            $owner = in_array($table, self::LEGACY_UNIQUE_PER_APP, true) ? array_intersect_key($owner, ['app_code' => true]) : [];
        }

        return Rule::unique($table, $column)
            ->where(function ($query) use ($owner) {
                foreach ($owner as $key => $value) {
                    $query->where($key, $value);
                }
            })
            ->ignore($ignoreId);
    }

    /**
     * Dalam satu aplikasi kode yang sama tidak boleh ada di company 0 (semua company) dan company N sekaligus,
     * supaya daftar company N tidak dobel.
     *
     * @param  array{app_code?: ?string, company_id?: int}  $owner
     *
     * @throws ValidationException
     */
    public function assertNoCompanyConflict(string $table, string $column, ?string $value, array $owner, int|string|null $ignoreId = null, ?string $field = null): void
    {
        $owner = $this->onlyExistingColumns($table, $owner);
        if ($value === null || $value === '' || ! isset($owner['app_code'], $owner['company_id']) || ! $this->uniquePerOwner($table)) {
            return; // unik global sudah mencakup bentrok company 0 / company N
        }

        $conflict = DB::table($table)
            ->where($column, $value)
            ->where('app_code', $owner['app_code'])
            ->when($owner['company_id'] === 0, fn ($query) => $query->where('company_id', '!=', 0), fn ($query) => $query->where('company_id', 0))
            ->when(Schema::hasColumn($table, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($ignoreId !== null && $ignoreId !== '', fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first(['company_id']);

        if ($conflict) {
            $message = $owner['company_id'] === 0
                ? "Kode {$value} sudah dipakai khusus company {$conflict->company_id} di aplikasi {$owner['app_code']}."
                : "Kode {$value} sudah dipakai untuk semua company di aplikasi {$owner['app_code']}.";

            throw ValidationException::withMessages([$field ?? $column => [$message]]);
        }
    }

    /**
     * Nilai placeholder filter setting: {APP_CODE}, {USER_COMPANY}, {GLOBAL_APP_CODE}.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        $delegation = $this->delegation();

        return [
            '{APP_CODE}' => $delegation['app_code'] ?? $this->appCode(),
            '{USER_COMPANY}' => (string) ($delegation['company_id'] ?? $this->userCompanyId()),
            '{GLOBAL_APP_CODE}' => $this->globalAppCode(),
        ];
    }

    /** label pemilik untuk tampilan, mis. "APP32 · MEB" atau "APP03 · Semua company" */
    public function ownerLabel(array $owner): string
    {
        $parts = [];
        if (filled($owner['app_code'] ?? null)) {
            $parts[] = $owner['app_code'];
        }
        if (array_key_exists('company_id', $owner)) {
            $companyId = (int) $owner['company_id'];
            $code = $companyId === 0 ? null : $this->companyCode($companyId);
            $parts[] = $companyId === 0 ? 'Semua company' : ($code ?? 'Company '.$companyId);
        }

        return implode(' · ', $parts);
    }

    public function companyCode(int $companyId): ?string
    {
        if (! Schema::hasTable('master_company')) {
            return null;
        }
        $code = DB::table('master_company')->where('id', $companyId)->value('company_code');

        return filled($code) ? strtoupper(trim((string) $code)) : null;
    }
}
