<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

use Bangsamu\Master\Services\MasterScope;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Template header dokumen per company (`master_company.template_json`), dikelompokkan per kode aplikasi:
 * `{"APP32": {"form_no": {...}, "rev_no": {...}, "logo_pdf": {"logo_2": "https://...", "iso": "https://..."}}}`.
 *
 * - Hanya diubah di aplikasi Master Data (hub, MASTER_HUB=true). Aplikasi lain menampilkannya hanya baca dan menerima
 *   isinya lewat sync master_company (kolom template_json ikut MasterDataSyncService).
 * - Gambar header PDF per aplikasi (motto = logo_2, ISO) disimpan sebagai URL http(s) hasil upload ke master_gallery.
 *   Yang disimpan URL, bukan id gallery, karena master_gallery tidak ikut sync ke aplikasi penyalin. Logo utama tetap
 *   kolom company_logo_id (Company Logo).
 */
final class CompanyTemplate
{
    /** key gambar header PDF di template_json[APP] */
    public const LOGO_KEY = 'logo_pdf';

    /**
     * Gambar header PDF per aplikasi => label form.
     *
     * @var array<string, string>
     */
    public const LOGO_FIELDS = [
        'logo_2' => 'Motto (logo_2)',
        'iso' => 'ISO',
    ];

    /** template + logo header PDF hanya boleh diubah di aplikasi Master Data (pusat) */
    public static function editableHere(): bool
    {
        return app(MasterScope::class)->isHub();
    }

    /**
     * Nama aplikasi per kode dari master_apps, untuk label "kode | nama". DB aplikasi dulu, lalu db_master (master_apps
     * tidak ikut sync ke aplikasi penyalin, di SPB tabel lokalnya kosong). Kosong bila tidak terbaca.
     *
     * @return array<string, string>
     */
    public static function appNames(): array
    {
        foreach ([null, 'db_master'] as $connection) {
            if ($connection !== null && config('database.connections.'.$connection) === null) {
                continue;
            }
            try {
                $names = DB::connection($connection)->table('master_apps')
                    ->whereNull('deleted_at')
                    ->orderBy('app_code')
                    ->pluck('name', 'app_code')
                    ->all();
            } catch (Throwable) {
                continue;
            }
            if ($names !== []) {
                return array_map(fn ($name): string => (string) $name, $names);
            }
        }

        return [];
    }

    /**
     * Label kode aplikasi: "APP32 | SPB V2", atau kode saja bila tidak terdaftar di master_apps.
     *
     * @param  array<string, string>  $names
     */
    public static function appLabel(string $code, array $names): string
    {
        return isset($names[$code]) && $names[$code] !== '' ? $code.' | '.$names[$code] : $code;
    }

    /**
     * URL gambar header PDF milik aplikasi $appCode. Hanya URL http(s): path lokal / skema lain diabaikan supaya isi
     * template tidak bisa membuat aplikasi membaca file di servernya sendiri.
     *
     * @return array<string, string> mis. ['logo_2' => 'https://...', 'iso' => 'https://...']
     */
    public static function logoUrls(mixed $templateJson, string $appCode): array
    {
        $template = is_array($templateJson) ? $templateJson : json_decode((string) $templateJson, true);
        $logos = is_array($template) ? ($template[$appCode][self::LOGO_KEY] ?? null) : null;

        return is_array($logos) ? self::validLogos($logos) : [];
    }

    /**
     * Rapikan logo_pdf tiap aplikasi sebelum disimpan: hanya field LOGO_FIELDS berisi URL http(s); kosong = dihapus.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    public static function sanitizeLogos(array $template): array
    {
        foreach ($template as $app => $data) {
            if (! is_array($data) || ! array_key_exists(self::LOGO_KEY, $data)) {
                continue;
            }
            $logos = is_array($data[self::LOGO_KEY]) ? self::validLogos($data[self::LOGO_KEY]) : [];
            if ($logos === []) {
                unset($template[$app][self::LOGO_KEY]);
            } else {
                $template[$app][self::LOGO_KEY] = $logos;
            }
        }

        return $template;
    }

    public static function isHttpUrl(mixed $value): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @param  array<mixed>  $logos
     * @return array<string, string>
     */
    private static function validLogos(array $logos): array
    {
        $valid = [];
        foreach (array_keys(self::LOGO_FIELDS) as $field) {
            if (self::isHttpUrl($logos[$field] ?? null)) {
                $valid[$field] = $logos[$field];
            }
        }

        return $valid;
    }
}
