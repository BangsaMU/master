<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

use Bangsamu\Master\Services\SchemaColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filter "klik company" di daftar Project (kolom Pemilik) dan Project Detail (kolom Company Code / Company Name):
 * klik kode/nama company di tabel atau di card ringkasan = daftar hanya menampilkan baris company itu.
 *
 * Parameter `company_id` (id master_company) ikut setiap request ajax daftar (layout: window.masterCompanyFilter,
 * partial master::partials.company-filter) dan boleh ditulis di URL halaman (`?company_id=648`), jadi tautannya bisa
 * dibuka di tab baru atau dibagikan. Cocok persis per id, bukan LIKE kode: cari teks "ME" juga menemukan MEB.
 * Company yang sudah dihapus tetap bisa dipakai (baris Project Detail lama masih memakainya); id tidak sah atau tidak
 * ada di master = tanpa filter.
 */
final class CompanyListFilter
{
    public const PARAM = 'company_id';

    public const TABLE = 'master_company';

    /**
     * Company terpilih dari request, atau null bila tidak ada / tidak sah.
     *
     * @return array{id: int, label: string, deleted: bool}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $id = filter_var($request->input(self::PARAM), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || ! SchemaColumns::hasTable(self::TABLE)) {
            return null;
        }

        $columns = ['id', 'company_code'];
        foreach (['company_name', 'deleted_at'] as $optional) {
            if (SchemaColumns::hasColumn(self::TABLE, $optional)) {
                $columns[] = $optional;
            }
        }

        $row = DB::table(self::TABLE)->where('id', $id)->first($columns);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'label' => self::label($row->company_code ?? null, $row->company_name ?? null),
            'deleted' => filled($row->deleted_at ?? null),
        ];
    }

    /** "KODE - Nama" (teks biasa, belum di-escape); bagian yang kosong dilewati. */
    public static function label(mixed $code, mixed $name): string
    {
        $parts = array_filter([trim((string) $code), trim((string) $name)], fn (string $part) => $part !== '');

        return implode(' - ', $parts);
    }

    /**
     * $data['datatable']['company_filter'] untuk layouts.dashboard.index.
     *
     * @param  string  $text  awalan teks bar filter, mis. "Hanya project milik"
     * @param  list<string>  $columns  alias kolom daftar (`data` DataTables) yang selnya jadi tautan filter
     * @param  string  $url  URL halaman daftar (tautan sel: URL ini + ?company_id=)
     * @return array{text: string, columns: list<string>, url: string, initial: array{id: int, label: string, deleted: bool}|null}
     */
    public static function config(Request $request, string $text, array $columns, string $url): array
    {
        return [
            'text' => $text,
            'columns' => array_values($columns),
            'url' => $url,
            'initial' => self::fromRequest($request),
        ];
    }

    /**
     * Kolom tambahan baris JSON daftar (bukan kolom tabel): id + label company untuk tautan filter di sel. Label
     * di-escape seperti sel lain (DataTableCell::text) karena dipakai di atribut HTML oleh skrip layout.
     *
     * @return array{company_filter_id: int|null, company_filter_label: string|null}
     */
    public static function rowFields(mixed $id, mixed $code, mixed $name): array
    {
        $id = (int) $id > 0 ? (int) $id : null;

        return [
            'company_filter_id' => $id,
            'company_filter_label' => $id === null ? null : DataTableCell::text(self::label($code, $name)),
        ];
    }
}
