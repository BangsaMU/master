<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Memasang filter Setting master ke query daftar/dropdown. Masukannya kelompok per baris Setting
 * (DynamicFilterable::settingFilterGroup(), placeholder sudah diganti nilai): kolom => daftar nilai.
 *
 *  - Kolom biasa (kolom tabel itu sendiri): AND di dalam satu baris, OR antar baris (perilaku sejak dulu).
 *  - Kolom relasi: kolomnya tidak ada di tabel itu melainkan di tabel relasi, didaftarkan di
 *    MasterCrudConfig.RELATION_FILTERS[tabel][kolom] = ['table' => tabel relasi, 'local' => kolom tabel ini,
 *    'foreign' => kolom tabel relasi, 'owner' => nilai pemilik (opsional), 'exclusive' => daftar nilai / class
 *    invokable / callable (opsional)]. Contoh: master_project.company_id lewat master_project_detail.
 *
 *    Nilai V menghasilkan `local IN (SELECT foreign FROM relasi WHERE kolom IN (V) AND deleted_at IS NULL)`.
 *    Bila `owner` ada di V, baris yang juga punya relasi ke salah satu nilai `exclusive` di luar V tidak ikut:
 *    `AND local NOT IN (SELECT foreign FROM relasi WHERE kolom IN (exclusive - V) AND deleted_at IS NULL)`.
 *    Dipakai untuk company pemilik yang barisnya ada di semua project: project yang sudah diberi baris company
 *    pengguna lain tidak lagi tampil untuk company pemilik.
 *
 *    Filter relasi SELALU AND: tidak pernah masuk kelompok OR, jadi baris Setting lain di kategori yang sama
 *    (mis. app_code) tidak bisa melonggarkannya. Kolom relasi di dalam baris scope dikeluarkan menjadi syarat AND
 *    tersendiri; sisa kolomnya tetap satu kelompok OR.
 *
 * Config kosong (bawaan) = tidak ada kolom relasi, hasil query sama persis dengan sebelum kelas ini ada.
 */
class MasterFilterGroups
{
    /**
     * @param  QueryBuilder|EloquentBuilder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<array<string, list<string|int>>>  $groups  satu elemen per baris Setting, placeholder sudah diganti
     * @param  ?string  $alias  alias tabel utama di query (mis. "mp"), null = tanpa awalan
     */
    public static function apply($query, string $table, array $groups, ?string $alias = null): void
    {
        $plain = [];
        $relations = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $columns = [];
            foreach ($group as $column => $values) {
                $column = (string) $column;
                $values = self::values($values);
                $relation = self::relation($table, $column);

                if ($relation === null) {
                    $columns[$column] = $values;

                    continue;
                }

                // syarat yang sama dari dua baris cukup dipasang sekali
                $values = self::trimmed($values);
                $sorted = $values;
                sort($sorted);
                $relations[$column.'|'.implode(',', $sorted)] = ['column' => $column, 'values' => $values, 'relation' => $relation];
            }

            // baris yang hanya berisi kolom relasi tidak menyisakan kelompok kosong (kelompok kosong = lolos semua)
            if ($columns !== []) {
                $plain[] = $columns;
            }
        }

        $qualify = fn (string $column): string => $alias !== null && $alias !== '' ? $alias.'.'.$column : $column;

        if ($plain !== []) {
            $query->where(function ($anyGroup) use ($plain, $qualify): void {
                foreach ($plain as $columns) {
                    $anyGroup->orWhere(function ($allColumns) use ($columns, $qualify): void {
                        foreach ($columns as $column => $values) {
                            $allColumns->whereIn($qualify((string) $column), $values);
                        }
                    });
                }
            });
        }

        foreach ($relations as $condition) {
            self::applyRelation($query, $condition['relation'], $condition['column'], $condition['values'], $qualify);
        }
    }

    /**
     * Nilai baris Setting format kolom ("1, 647, 0") jadi daftar nilai: dipisah koma, spasi tepi dibuang, hanya teks
     * kosong yang dilewati. Angka 0 tetap nilai (company_id 0 = baris bersama semua company). Sebelum v3.19.1
     * array_filter() tanpa callback membuang "0", jadi baris `company_id = 0` diabaikan seluruhnya tanpa pesan.
     *
     * @return list<string>
     */
    public static function settingValues(mixed $value): array
    {
        $value = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));
    }

    /** Kolom ini terdaftar sebagai filter relasi tabel itu (MasterCrudConfig.RELATION_FILTERS). */
    public static function isRelationColumn(string $table, string $column): bool
    {
        return self::relation($table, $column) !== null;
    }

    /**
     * Definisi relasi yang sudah dirapikan, null bila kolom tidak terdaftar atau definisinya tidak lengkap.
     *
     * @return array{table: string, local: string, foreign: string, owner: ?string, exclusive: mixed}|null
     */
    public static function relation(string $table, string $column): ?array
    {
        $relations = config('MasterCrudConfig.RELATION_FILTERS');
        $relation = is_array($relations) ? ($relations[$table][$column] ?? null) : null;
        if (! is_array($relation)) {
            return null;
        }

        foreach (['table', 'local', 'foreign'] as $key) {
            if (! is_string($relation[$key] ?? null) || $relation[$key] === '') {
                return null;
            }
        }

        $owner = $relation['owner'] ?? null;

        return [
            'table' => $relation['table'],
            'local' => $relation['local'],
            'foreign' => $relation['foreign'],
            'owner' => is_scalar($owner) && trim((string) $owner) !== '' ? trim((string) $owner) : null,
            'exclusive' => $relation['exclusive'] ?? null,
        ];
    }

    /**
     * @param  QueryBuilder|EloquentBuilder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array{table: string, local: string, foreign: string, owner: ?string, exclusive: mixed}  $relation
     * @param  list<string>  $values
     * @param  callable(string): string  $qualify
     */
    protected static function applyRelation($query, array $relation, string $column, array $values, callable $qualify): void
    {
        $connection = self::connectionName($query);
        $related = $relation['table'];
        $foreign = $relation['foreign'];

        // tabel relasi belum ada / belum punya kolomnya: tidak ada baris yang lolos, bukan semua baris
        if (! SchemaColumns::hasColumn($related, $column, $connection) || ! SchemaColumns::hasColumn($related, $foreign, $connection)) {
            $query->whereRaw('0 = 1');

            return;
        }

        $softDeletes = SchemaColumns::hasColumn($related, 'deleted_at', $connection);
        $subquery = fn (array $in) => function ($sub) use ($related, $foreign, $column, $in, $softDeletes): void {
            // foreign NULL di dalam NOT IN membuat seluruh syarat bernilai NULL (tidak ada baris yang lolos)
            $sub->select($foreign)->from($related)->whereIn($column, $in)->whereNotNull($foreign);
            if ($softDeletes) {
                $sub->whereNull('deleted_at');
            }
        };

        $local = $qualify($relation['local']);
        $query->whereIn($local, $subquery($values));

        $excluded = self::excluded($relation, $values);
        if ($excluded !== []) {
            $query->whereNotIn($local, $subquery($excluded));
        }
    }

    /**
     * Nilai `exclusive` yang dikecualikan: hanya bila `owner` termasuk nilai yang diminta, dikurangi nilai itu sendiri.
     *
     * @param  array{table: string, local: string, foreign: string, owner: ?string, exclusive: mixed}  $relation
     * @param  list<string>  $values
     * @return list<string>
     */
    protected static function excluded(array $relation, array $values): array
    {
        if ($relation['owner'] === null || ! in_array($relation['owner'], $values, true)) {
            return [];
        }

        return array_values(array_diff(self::exclusive($relation['exclusive']), $values));
    }

    /**
     * Daftar nilai `exclusive`: array, class invokable (lewat container), nama fungsi, callable, atau teks angka
     * dipisah koma (mis. dari .env).
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException nama class/fungsi tidak ditemukan (salah ketik = pengecualian hilang diam-diam)
     */
    protected static function exclusive(mixed $exclusive): array
    {
        if (is_string($exclusive)) {
            if (preg_match('/^[\d\s,]*$/', $exclusive) === 1) {
                $exclusive = explode(',', $exclusive);
            } elseif (class_exists($exclusive)) {
                $exclusive = app($exclusive)();
            } elseif (function_exists($exclusive)) {
                $exclusive = $exclusive();
            } else {
                throw new InvalidArgumentException("MasterCrudConfig.RELATION_FILTERS exclusive: class atau fungsi [{$exclusive}] tidak ditemukan.");
            }
        } elseif (is_callable($exclusive)) {
            $exclusive = $exclusive(); // Closure, objek invokable, atau [class, method]
        }

        if ($exclusive instanceof \Traversable) {
            $exclusive = iterator_to_array($exclusive, false);
        }

        return is_array($exclusive) ? self::trimmed(self::values($exclusive)) : [];
    }

    /**
     * @param  list<string>  $values
     * @return list<string> tanpa spasi tepi dan tanpa nilai kosong (nilai relasi dibandingkan dengan owner/exclusive)
     */
    protected static function trimmed(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $values), fn (string $value): bool => $value !== '')));
    }

    /**
     * @return list<string> nilai unik sebagai string
     */
    protected static function values(mixed $values): array
    {
        $result = [];
        foreach ((array) $values as $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $result[] = (string) $value;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param  QueryBuilder|EloquentBuilder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected static function connectionName($query): ?string
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        return $base instanceof QueryBuilder ? $base->getConnection()->getName() : null;
    }
}
