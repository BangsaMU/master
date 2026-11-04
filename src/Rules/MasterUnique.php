<?php

declare(strict_types=1);

namespace Bangsamu\Master\Rules;

use Bangsamu\Master\Services\MasterTrash;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;

/**
 * Rule unik kode master (MasterScope::uniqueRule): query sama dengan rule `unique` Laravel (baris terhapus ikut
 * dihitung, karena index unik DB juga mencakupnya), tetapi pesannya menyebut baris yang memakai kode itu: nama,
 * pemilik, atau keterangan terhapus + cara memulihkannya (MasterTrash::conflictMessage).
 *
 * Tetap turunan Unique supaya pemanggil lama yang merangkai ->where()/->ignore() tidak berubah.
 */
class MasterUnique extends Unique implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value)) {
            $fail('validation.unique')->translate();

            return;
        }

        $column = $this->column === 'NULL' ? $attribute : $this->column;
        $row = $this->conflict($column, $value);
        if ($row !== null) {
            $fail(app(MasterTrash::class)->conflictMessage($this->tableName(), $column, (string) $value, $row));
        }
    }

    /** baris lain yang sudah memakai nilai ini (aturan DatabasePresenceVerifier::getCount) */
    public function conflict(string $column, mixed $value): ?object
    {
        [$connection, $table] = str_contains((string) $this->table, '.') ? explode('.', (string) $this->table, 2) : [null, $this->table];

        $query = DB::connection($connection)->table($table)->where($column, '=', $value);
        if ($this->ignore !== null && $this->ignore !== '' && strtolower((string) $this->ignore) !== 'null') {
            $query->where($this->idColumn ?: 'id', '<>', $this->ignore);
        }
        foreach ($this->wheres as $where) {
            $extra = (string) $where['value'];
            match (true) {
                $extra === 'NULL' => $query->whereNull($where['column']),
                $extra === 'NOT_NULL' => $query->whereNotNull($where['column']),
                str_starts_with($extra, '!') => $query->where($where['column'], '!=', mb_substr($extra, 1)),
                default => $query->where($where['column'], $extra),
            };
        }
        foreach ($this->using as $callback) {
            $query->where(fn ($query) => $callback($query));
        }

        // bila kode dipakai baris aktif dan baris terhapus sekaligus, sebut baris aktifnya
        $rows = $query->limit(5)->get();

        return $rows->first(fn ($row) => empty($row->deleted_at)) ?? $rows->first();
    }

    protected function tableName(): string
    {
        return str_contains((string) $this->table, '.') ? explode('.', (string) $this->table, 2)[1] : (string) $this->table;
    }
}
