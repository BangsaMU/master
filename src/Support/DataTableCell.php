<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

/**
 * Nilai sel untuk JSON dataJson() daftar master (layouts.dashboard.index).
 *
 * DataTables 1.10 di layout itu memasang data kolom sebagai HTML (innerHTML, tanpa `render` text), jadi setiap teks dari
 * database wajib di-escape di server; tanpa itu nama berisi `<img src=x onerror=...>` (form, import Excel, sinkron
 * aplikasi lain) dieksekusi di browser semua pembuka daftar (stored XSS). Yang boleh berisi HTML hanya buatan server:
 * kolom action (tombol) dan pemisah `<br>` Attributes Item Code (key/value tetap lewat text()).
 */
final class DataTableCell
{
    /**
     * Teks di-escape seperti `{{ }}` Blade (e(), termasuk tanda kutip); angka, bool dan null dibiarkan supaya tipe JSON
     * tidak berubah.
     */
    public static function text(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (! is_string($value)) {
            $value = is_scalar($value) || $value instanceof \Stringable
                ? (string) $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return e($value);
    }

    /**
     * Pasangan key: value dari kolom JSON (mis. attributes Item Code), satu per baris dengan `<br>`. Key dan value
     * di-escape; value bersarang ditulis sebagai JSON (dulu "Array to string conversion" = daftar 500). Bukan objek
     * JSON = string kosong.
     */
    public static function keyValueLines(?string $json): string
    {
        $pairs = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (! is_array($pairs)) {
            return '';
        }

        $lines = [];
        foreach ($pairs as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', (string) $key));
            $lines[] = self::text($label).': '.self::text(is_bool($value) ? ($value ? '1' : '') : $value);
        }

        return implode('<br>', $lines);
    }
}
