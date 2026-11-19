<?php

namespace Bangsamu\Master\Support;

use Illuminate\Http\Request;

/**
 * Cari dan urut daftar master (DataTables server-side) lewat whitelist kolom.
 *
 * - Whitelist: nama kolom `data` DataTables (alias di view_tabel_index) => ekspresi SQL. Nilai dari request tidak pernah
 *   menjadi nama kolom; kolom di luar whitelist diabaikan.
 * - Cari per kolom (`columns[i][search][value]`, input di bawah judul kolom, view opt-in
 *   `$data['datatable']['column_search']`): setiap kata wajib ada di kolom itu, antar kolom AND.
 * - Cari global (`search[value]`): setiap kata wajib ada di salah satu kolom whitelist, termasuk kolom hasil join. Helper
 *   lama `datatabelFilterQuery()` aplikasi hanya mencari kolom yang ada di tabel utama (`Schema::hasColumn`), jadi kolom
 *   join seperti `mp.project_code` di daftar Project Detail diam-diam tidak ikut dicari.
 * - LIKE '%kata%' dengan % dan _ ketikan user dibaca sebagai huruf biasa (ESCAPE '!', berlaku di MariaDB dan SQLite);
 *   tanpa beda huruf besar/kecil mengikuti collation (MariaDB *_ci) / LIKE SQLite.
 */
final class ListSearch
{
    /** batas kata per kotak cari (tiap kata = satu LIKE) */
    public const MAX_TERMS = 5;

    /**
     * @param  array<string, string>  $columns  whitelist nama kolom DataTables => ekspresi SQL
     * @param  array<string, list<string>>  $columnTerms
     * @param  list<string>  $globalTerms
     */
    private function __construct(
        private readonly array $columns,
        private readonly array $columnTerms,
        private readonly array $globalTerms,
    ) {}

    /** @param  array<string, string>  $columns  whitelist nama kolom DataTables => ekspresi SQL */
    public static function fromRequest(Request $request, array $columns): self
    {
        $columnTerms = [];
        foreach ((array) $request->input('columns', []) as $column) {
            $name = is_array($column) ? ($column['data'] ?? null) : null;
            if (! is_string($name) || ! isset($columns[$name])) {
                continue;
            }
            $terms = self::terms($column['search']['value'] ?? null);
            if ($terms !== []) {
                $columnTerms[$name] = $terms;
            }
        }

        return new self($columns, $columnTerms, self::terms($request->input('search.value')));
    }

    /** tidak ada kata cari per kolom maupun global */
    public function isEmpty(): bool
    {
        return $this->columnTerms === [] && $this->globalTerms === [];
    }

    /** pasang syarat cari ke query (Query Builder atau Eloquent Builder) */
    public function apply($query): void
    {
        foreach ($this->columnTerms as $column => $terms) {
            foreach ($terms as $term) {
                self::whereContains($query, $this->columns[$column], $term);
            }
        }

        foreach ($this->globalTerms as $term) {
            $query->where(function ($any) use ($term) {
                foreach ($this->columns as $expression) {
                    self::whereContains($any, $expression, $term, 'or');
                }
            });
        }
    }

    /**
     * Ekspresi urut dari kolom DataTables `order[0]` (dicocokkan lewat nama `columns[i][data]`, bukan indeks array
     * controller); kolom No / tak dikenal = $default.
     *
     * @param  array<string, string>  $columns
     */
    public static function orderExpression(Request $request, array $columns, string $default): string
    {
        $name = $request->input('columns.'.(int) $request->input('order.0.column', 0).'.data');

        return is_string($name) && isset($columns[$name]) ? $columns[$name] : $default;
    }

    /** arah urut `order[0][dir]`: hanya asc/desc, bawaan desc */
    public static function direction(Request $request): string
    {
        return strtolower((string) ($request->input('order.0.dir') ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    /** LIKE '%nilai%' dengan % dan _ ketikan user dibaca sebagai huruf biasa */
    public static function whereContains($query, string $expression, string $value, string $boolean = 'and'): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
        $query->whereRaw($expression." LIKE ? ESCAPE '!'", ['%'.$escaped.'%'], $boolean);
    }

    /** @return list<string> kata pencarian dipisah spasi, unik, maksimal MAX_TERMS */
    public static function terms(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $terms = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);
    }
}
