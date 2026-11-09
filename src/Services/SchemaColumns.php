<?php

namespace Bangsamu\Master\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pengganti Schema::hasTable / hasColumn / getColumnListing di jalur panas paket (dropdown api/getmaster_*byparams,
 * daftar dan form master, filter Setting, boot model). Tanpa ini setiap cek = satu query information_schema, 5-11 kali
 * per panggilan dropdown.
 *
 * - Memo per request/job: instance `scoped` di container (dibuat ulang tiap request, job queue, dan test).
 * - Cache lintas request (store cache default) per koneksi + database + tabel selama ttl() detik, hanya untuk tabel
 *   yang ada. Tabel yang belum ada tidak di-memo/di-cache karena bisa dibuat kapan saja (MasterTableInstaller, boot model).
 * - flush(): token generasi baru (semua kunci lama tidak dipakai lagi) + memo dikosongkan. Dipanggil
 *   MasterDataSyncService::flushSchemaCache() (installer, master:scope-upgrade, master:schema-columns) dan setelah
 *   `php artisan migrate` (event MigrationsEnded). ALTER di luar itu terlihat setelah ttl() atau `php artisan cache:clear`.
 * - Store cache bermasalah tidak mematikan halaman: dianggap tidak ada cache, kolom dibaca dari database.
 */
class SchemaColumns
{
    public const TTL = 3600;

    public const CACHE_PREFIX = 'master_schema:columns:';

    public const GENERATION_KEY = 'master_schema:generation';

    /** @var array<string, list<string>> kolom per "koneksi|tabel" (hanya tabel yang ada) */
    protected array $memo = [];

    protected ?string $generation = null;

    /**
     * Kolom tabel (nama asli), kosong bila tabel tidak ada.
     *
     * @return list<string>
     */
    public static function listing(string $table, ?string $connection = null): array
    {
        return app(self::class)->columnsOf($table, $connection);
    }

    public static function hasTable(string $table, ?string $connection = null): bool
    {
        return self::listing($table, $connection) !== [];
    }

    /**
     * Sama dengan Schema::hasColumn: nama kolom tidak peka huruf besar/kecil.
     *
     * Nama kolom sering berasal dari parameter request (set[text], set[id], search[...]) yang boleh tidak dikirim.
     * Schema::hasColumn tanpa tipe parameter menjawab false untuk null; di sini juga false untuk nilai bukan string
     * atau kosong, bukan TypeError (v3.9.0: form Item Code memanggil set[field] tanpa set[text] = 500).
     */
    public static function hasColumn(string $table, mixed $column, ?string $connection = null): bool
    {
        if (! is_string($column) || $column === '') {
            return false;
        }

        return in_array(strtolower($column), array_map('strtolower', self::listing($table, $connection)), true);
    }

    /**
     * Lupakan semua daftar kolom (memo proses ini + cache lintas request) setelah struktur tabel berubah.
     */
    public static function flush(): void
    {
        app(self::class)->forgetMemo();

        try {
            Cache::forever(self::GENERATION_KEY, bin2hex(random_bytes(8)));
        } catch (Throwable $e) {
        }
    }

    /** Detik cache lintas request; 0 = hanya memo per request (MasterConfig.schema_cache_ttl). */
    public static function ttl(): int
    {
        return max(0, (int) config('MasterConfig.schema_cache_ttl', self::TTL));
    }

    /**
     * @return list<string>
     */
    public function columnsOf(string $table, ?string $connection = null): array
    {
        $connection ??= DB::getDefaultConnection();
        $memoKey = $connection.'|'.$table;
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $cacheKey = self::ttl() > 0 ? $this->cacheKey($connection, $table) : null;
        $cached = $cacheKey === null ? null : $this->cacheGet($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $this->memo[$memoKey] = array_values($cached);
        }

        $columns = array_values(Schema::connection($connection)->getColumnListing($table));
        if ($columns !== []) {
            $this->memo[$memoKey] = $columns;
            if ($cacheKey !== null) {
                $this->cachePut($cacheKey, $columns);
            }
        }

        return $columns;
    }

    public function forgetMemo(): void
    {
        $this->memo = [];
        $this->generation = null;
    }

    protected function cacheKey(string $connection, string $table): ?string
    {
        try {
            $database = (string) DB::connection($connection)->getDatabaseName();
        } catch (Throwable $e) {
            return null;
        }

        return self::CACHE_PREFIX.$this->generation().':'.$connection.':'.$database.':'.$table;
    }

    protected function generation(): string
    {
        if ($this->generation === null) {
            try {
                $this->generation = (string) (Cache::get(self::GENERATION_KEY) ?? '0');
            } catch (Throwable $e) {
                $this->generation = '0';
            }
        }

        return $this->generation;
    }

    protected function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param  list<string>  $columns
     */
    protected function cachePut(string $key, array $columns): void
    {
        try {
            Cache::put($key, $columns, self::ttl());
        } catch (Throwable $e) {
        }
    }
}
