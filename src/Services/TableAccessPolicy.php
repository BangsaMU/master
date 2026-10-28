<?php

namespace Bangsamu\Master\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Batas tabel dan kolom yang boleh dibaca API generik paket (get{tabel}byparams, get-{tabel}, getMaster-{tabel}).
 * Kolom rahasia tidak pernah keluar dan tidak boleh dipakai filter/urut/join (mencegah tebak isi kolom lewat LIKE).
 */
class TableAccessPolicy
{
    /** Kolom yang selalu ditolak, apa pun isi MasterCrudConfig.SELECT2_HIDDEN_COLUMNS. */
    public const ALWAYS_HIDDEN_COLUMNS = ['password', 'remember_token', 'api_token'];

    /** Tabel (pola Str::is) yang selalu ditolak, apa pun isi MasterCrudConfig.SELECT2_DENY_TABLES. */
    public const ALWAYS_DENIED_TABLES = [
        'sessions', 'password_reset*', '*token*', 'oauth_*', 'jobs', 'failed_jobs', 'job_batches',
        'cache', 'cache_locks', 'migrations', 'telescope_*', 'pulse_*',
    ];

    public function isValidTableName(mixed $tabel): bool
    {
        return is_string($tabel) && preg_match('/^[A-Za-z0-9_]+$/', $tabel) === 1;
    }

    /** Tabel boleh dibaca API generik bertoken (get-{tabel}, getMaster-{tabel}): nama valid dan tidak di daftar tolak. */
    public function isReadableTable(mixed $tabel): bool
    {
        if (! $this->isValidTableName($tabel)) {
            return false;
        }

        $denied = array_merge(self::ALWAYS_DENIED_TABLES, (array) config('MasterCrudConfig.SELECT2_DENY_TABLES', []));

        return ! Str::is($denied, strtolower($tabel));
    }

    /** Tabel (termasuk tabel join) boleh dipakai get{tabel}byparams: lolos daftar tolak dan whitelist SELECT2_TABLES bila diisi. */
    public function isSelect2Table(mixed $tabel): bool
    {
        if (! $this->isReadableTable($tabel)) {
            return false;
        }

        $allowed = (array) config('MasterCrudConfig.SELECT2_TABLES', []);

        return $allowed === [] || Str::is($allowed, strtolower($tabel));
    }

    /** @return list<string> */
    public function hiddenColumns(): array
    {
        $columns = array_merge(self::ALWAYS_HIDDEN_COLUMNS, (array) config('MasterCrudConfig.SELECT2_HIDDEN_COLUMNS', []));

        return array_values(array_unique(array_map('strtolower', $columns)));
    }

    /** Nama kolom dari input (boleh `alias.kolom`, berkutip, beda huruf besar, atau path JSON `kolom->x`) termasuk kolom rahasia. */
    public function isHiddenColumn(mixed $column): bool
    {
        if (! is_string($column) && ! is_int($column)) {
            return false;
        }

        $name = strtolower(Str::before((string) $column, '->'));
        $name = trim(Str::afterLast($name, '.'), " \t\n\r\0\x0B`\"'[]");

        return in_array($name, $this->hiddenColumns(), true);
    }

    /**
     * Satu baris hasil tanpa kolom rahasia.
     *
     * @return array<string, mixed>
     */
    public function withoutHiddenColumns(array|object $row): array
    {
        return array_filter((array) $row, fn ($column) => ! $this->isHiddenColumn($column), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Kolom tabel yang boleh keluar (pengganti `tabel.*`).
     *
     * @return list<string>
     */
    public function visibleColumns(string $tabel): array
    {
        return array_values(array_filter(Schema::getColumnListing($tabel), fn ($column) => ! $this->isHiddenColumn($column)));
    }
}
