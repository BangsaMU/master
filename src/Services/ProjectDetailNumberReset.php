<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Http\Request;

/**
 * Penanda reset nomor tahunan per baris Project Detail (project × company): master_project_detail.number_reset_yearly.
 *
 * 1 = nomor dokumen company itu di project itu mulai dari 1 lagi tiap tahun (aplikasi SPB: pola {yy}{sequence:3}),
 * 0 = tidak reset. Per company karena tiap company punya kode project sendiri (Meindo 21317 = MEB 73029) dan
 * ketentuan sendiri. Baris pemilik project juga punya penanda ini: kunci baris pemilik hanya untuk kode/nama
 * (ProjectOwnerDetail::detailChangeDenial), dan cermin project tidak menyalin kolom ini.
 *
 * Hanya admin Master Data (MasterAccess::isAdmin) yang boleh mengubahnya, karena mengubah nomor dokumen aplikasi lain.
 * User lain melihat nilainya; nilai yang mereka kirim (form, import) diabaikan. Rancangan:
 * docs/rancangan-reset-tahunan-project.md di SPB (meb_spb).
 */
class ProjectDetailNumberReset
{
    public const TABLE = 'master_project_detail';

    public const COLUMN = 'number_reset_yearly';

    /** label kolom (daftar, import, template) */
    public const LABEL = 'Yearly Reset';

    /** teks nilai di daftar dan preview import */
    public const YES = 'Ya';

    public const NO = 'Tidak';

    public function __construct(protected MasterAccess $access) {}

    /** Kolom sudah ada (paket >= v3.16: migration / master:install-tables / sync). */
    public function available(): bool
    {
        return SchemaColumns::hasColumn(self::TABLE, self::COLUMN);
    }

    /** User ini boleh mengubah penanda (admin Master Data) dan kolomnya ada. */
    public function canEdit(): bool
    {
        return $this->available() && $this->access->isAdmin();
    }

    /** Ekspresi SQL teks Ya/Tidak untuk alias tabel master_project_detail (daftar, cari, urut). */
    public function textSql(string $alias = 'mpd'): string
    {
        return "(CASE WHEN {$alias}.".self::COLUMN." = 1 THEN '".self::YES."' ELSE '".self::NO."' END)";
    }

    /** Nilai baris (stdClass / model) sebagai bool; kolom belum ada / kosong = tidak reset. */
    public function isYearly(?object $detail): bool
    {
        if ($detail === null) {
            return false;
        }

        $attributes = method_exists($detail, 'getAttributes') ? $detail->getAttributes() : get_object_vars($detail);

        return (int) ($attributes[self::COLUMN] ?? 0) === 1;
    }

    /**
     * Nilai untuk disimpan dari form: null = jangan diubah (bukan admin, kolom belum ada, atau field tidak dikirim).
     * Form mengirim hidden 0 + checkbox 1, jadi field selalu ada bila centangnya tampil dan bisa diubah.
     */
    public function valueFromRequest(Request $request): ?int
    {
        if (! $this->canEdit() || ! $request->has(self::COLUMN)) {
            return null;
        }

        return $request->boolean(self::COLUMN) ? 1 : 0;
    }

    /**
     * Isi kolom Yearly Reset di file import menjadi Ya/Tidak; '' = kosong (nilai lama tidak diubah), null = tidak
     * dikenali.
     */
    public static function normalizeImportValue(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return match (true) {
            $value === '' => '',
            in_array($value, ['Y', 'YA', 'YES', '1', 'TRUE'], true) => self::YES,
            in_array($value, ['N', 'T', 'TIDAK', 'NO', '0', 'FALSE', '-'], true) => self::NO,
            default => null,
        };
    }

    /** Teks Ya/Tidak → nilai kolom. */
    public static function toColumnValue(string $text): int
    {
        return $text === self::YES ? 1 : 0;
    }
}
