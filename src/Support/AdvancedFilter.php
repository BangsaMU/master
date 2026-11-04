<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Filter lanjutan daftar master (modal "Filter Lanjutan", parameter request `advanced_filter` berisi JSON).
 *
 * Bentuk: grup kondisi bertingkat, maksimal MAX_DEPTH tingkat (grup utama = tingkat 1), tiap grup AND atau OR (bawaan AND):
 *   {"logic":"and","rules":[
 *     {"field":"description","op":"contains","value":"Excavator"},
 *     {"logic":"or","rules":[{"field":"description","op":"contains","value":"[Remark]"},
 *                            {"field":"description","op":"contains","value":"[Brand]"}]},
 *     {"field":"category","op":"equals","value":"EQUIPMENT"}]}
 *
 * Keamanan: field hanya dari whitelist controller (alias => ekspresi SQL), nilai user selalu lewat binding, dan grup
 * utama selalu dibungkus kurung supaya OR tidak lepas dari filter data terhapus / scope aplikasi. Nilai dicari utuh
 * (frasa, termasuk spasi dan koma), tanpa beda huruf besar/kecil; % dan _ ketikan user = huruf biasa.
 */
final class AdvancedFilter
{
    public const MAX_DEPTH = 3;

    /** batas kondisi; filter dikirim lewat query string GET DataTables, jadi panjang URL ikut dijaga */
    public const MAX_RULES = 20;

    public const MAX_VALUE_LENGTH = 100;

    /** batas panjang JSON mentah sebelum di-decode */
    private const MAX_JSON_LENGTH = 10000;

    /** operator => label di modal (urutan = urutan pilihan) */
    public const OPERATORS = [
        'contains' => 'mengandung',
        'not_contains' => 'tidak mengandung',
        'equals' => 'sama dengan',
        'not_equals' => 'tidak sama dengan',
        'starts_with' => 'diawali',
        'ends_with' => 'diakhiri',
        'empty' => 'kosong',
        'not_empty' => 'tidak kosong',
    ];

    /** operator tanpa nilai */
    public const OPERATORS_WITHOUT_VALUE = ['empty', 'not_empty'];

    /**
     * @param  array{logic: string, rules: list<array<string, mixed>>}  $group  sudah dinormalisasi
     * @param  array<string, string>  $fields  alias => ekspresi SQL
     */
    private function __construct(private readonly array $group, private readonly array $fields, private readonly int $ruleCount)
    {
    }

    /**
     * Baca filter dari request. Null = tidak ada filter (kosong, atau semua kondisi belum diisi nilainya).
     *
     * @param  array<string, string>  $fields  whitelist alias => ekspresi SQL (dari controller, bukan dari request)
     *
     * @throws InvalidArgumentException bentuk filter salah; pesannya aman ditampilkan ke user
     */
    public static function fromRequest(mixed $raw, array $fields): ?self
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_string($raw) || strlen($raw) > self::MAX_JSON_LENGTH) {
            throw new InvalidArgumentException('Filter lanjutan tidak dikenali atau terlalu panjang.');
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Filter lanjutan tidak dikenali (bukan JSON).');
        }

        $count = 0;
        $group = self::normalizeGroup($data, $fields, 1, $count);

        return $group === null ? null : new self($group, $fields, $count);
    }

    /** jumlah kondisi yang dipakai (kondisi tanpa nilai tidak dihitung) */
    public function ruleCount(): int
    {
        return $this->ruleCount;
    }

    /** tambahkan filter ke query sebagai satu grup berkurung (AND dengan where lain) */
    public function apply(Builder $query): Builder
    {
        return $query->where(fn (Builder $nested) => $this->applyGroup($nested, $this->group));
    }

    /**
     * Data untuk modal di view (layouts.dashboard.index, partial master::partials.advanced-filter.*).
     *
     * @param  array<string, string>  $labels  alias field => label kolom
     * @param  list<string>  $suggest  field yang diberi saran nilai (datalist) dari $optionsUrl
     * @return array<string, mixed>
     */
    public static function viewConfig(array $labels, array $suggest = [], ?string $optionsUrl = null): array
    {
        $fields = [];
        foreach ($labels as $field => $label) {
            $fields[] = ['field' => $field, 'label' => $label, 'suggest' => in_array($field, $suggest, true)];
        }
        $operators = [];
        foreach (self::OPERATORS as $op => $label) {
            $operators[] = ['op' => $op, 'label' => $label, 'value' => ! in_array($op, self::OPERATORS_WITHOUT_VALUE, true)];
        }

        return [
            'fields' => $fields,
            'operators' => $operators,
            'max_depth' => self::MAX_DEPTH,
            'max_rules' => self::MAX_RULES,
            'max_value_length' => self::MAX_VALUE_LENGTH,
            'options_url' => $suggest !== [] ? $optionsUrl : null,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{logic: string, rules: list<array<string, mixed>>}|null  null = grup tanpa kondisi berisi
     */
    private static function normalizeGroup(mixed $group, array $fields, int $depth, int &$count): ?array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Filter lanjutan maksimal '.self::MAX_DEPTH.' tingkat grup.');
        }
        if (! is_array($group) || ! array_key_exists('rules', $group) || ! is_array($group['rules']) || ! array_is_list($group['rules'])) {
            throw new InvalidArgumentException('Grup filter lanjutan tidak dikenali.');
        }

        $logic = $group['logic'] ?? 'and';
        if (! is_string($logic) || ! in_array(strtolower($logic), ['and', 'or'], true)) {
            throw new InvalidArgumentException('Logika grup filter lanjutan hanya AND atau OR.');
        }

        $rules = [];
        foreach ($group['rules'] as $item) {
            $normalized = is_array($item) && array_key_exists('rules', $item)
                ? self::normalizeGroup($item, $fields, $depth + 1, $count)
                : self::normalizeRule($item, $fields, $count);
            if ($normalized !== null) {
                $rules[] = $normalized;
            }
        }

        return $rules === [] ? null : ['logic' => strtolower($logic), 'rules' => $rules];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{field: string, op: string, value: string}|null  null = kondisi belum diisi nilainya (dilewati)
     */
    private static function normalizeRule(mixed $rule, array $fields, int &$count): ?array
    {
        if (! is_array($rule)) {
            throw new InvalidArgumentException('Kondisi filter lanjutan tidak dikenali.');
        }

        $field = $rule['field'] ?? null;
        if (! is_string($field) || ! array_key_exists($field, $fields)) {
            throw new InvalidArgumentException('Kolom filter lanjutan tidak dikenal.');
        }

        $op = $rule['op'] ?? 'contains';
        if (! is_string($op) || ! array_key_exists($op, self::OPERATORS)) {
            throw new InvalidArgumentException('Operator filter lanjutan tidak dikenal.');
        }

        $value = $rule['value'] ?? '';
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Nilai filter lanjutan harus berupa teks.');
        }
        $value = trim($value);

        $needsValue = ! in_array($op, self::OPERATORS_WITHOUT_VALUE, true);
        if ($needsValue && $value === '') {
            return null;
        }
        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            throw new InvalidArgumentException('Nilai filter lanjutan maksimal '.self::MAX_VALUE_LENGTH.' karakter.');
        }
        if (++$count > self::MAX_RULES) {
            throw new InvalidArgumentException('Filter lanjutan maksimal '.self::MAX_RULES.' kondisi.');
        }

        return ['field' => $field, 'op' => $op, 'value' => $needsValue ? $value : ''];
    }

    /** @param array{logic: string, rules: list<array<string, mixed>>} $group */
    private function applyGroup(Builder $query, array $group): void
    {
        foreach ($group['rules'] as $rule) {
            if (isset($rule['rules'])) {
                $query->where(fn (Builder $nested) => $this->applyGroup($nested, $rule), null, null, $group['logic']);
            } else {
                $this->applyRule($query, $rule, $group['logic']);
            }
        }
    }

    /**
     * LIKE dipakai juga untuk "sama dengan": tanpa beda huruf besar/kecil di MariaDB (collation *_ci) dan SQLite.
     * "Tidak ..." ikut memasukkan nilai NULL (tanpa referensi), seperti yang terlihat kosong di daftar.
     *
     * @param  array{field: string, op: string, value: string}  $rule
     */
    private function applyRule(Builder $query, array $rule, string $boolean): void
    {
        $column = $this->fields[$rule['field']];
        $value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $rule['value']);

        match ($rule['op']) {
            'contains' => $query->whereRaw($column." LIKE ? ESCAPE '!'", ['%'.$value.'%'], $boolean),
            'not_contains' => $query->whereRaw('('.$column.' IS NULL OR '.$column." NOT LIKE ? ESCAPE '!')", ['%'.$value.'%'], $boolean),
            'equals' => $query->whereRaw($column." LIKE ? ESCAPE '!'", [$value], $boolean),
            'not_equals' => $query->whereRaw('('.$column.' IS NULL OR '.$column." NOT LIKE ? ESCAPE '!')", [$value], $boolean),
            'starts_with' => $query->whereRaw($column." LIKE ? ESCAPE '!'", [$value.'%'], $boolean),
            'ends_with' => $query->whereRaw($column." LIKE ? ESCAPE '!'", ['%'.$value], $boolean),
            'empty' => $query->whereRaw('('.$column.' IS NULL OR '.$column." = '')", [], $boolean),
            'not_empty' => $query->whereRaw('('.$column.' IS NOT NULL AND '.$column." <> '')", [], $boolean),
        };
    }
}
