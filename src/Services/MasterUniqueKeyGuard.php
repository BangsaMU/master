<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penjaga kunci unik master data saat create/edit:
 *  - kolom kode selalu disimpan huruf besar;
 *  - kunci unik tidak boleh diubah kecuali is_admin;
 *  - is_admin wajib mengonfirmasi perubahan (confirm_key_change=1, diisi dialog konfirmasi di form).
 */
class MasterUniqueKeyGuard
{
    public const CONFIRM_FIELD = 'confirm_key_change';

    /**
     * entity => [tabel, kolom kunci unik => label, kolom kode (huruf besar)].
     */
    public const KEYS = [
        'category' => ['master_category', ['category_code' => 'Category Code'], ['category_code']],
        'uom' => ['master_uom', ['uom_code' => 'UOM Code'], ['uom_code']],
        'item-group' => ['master_item_group', ['item_group_code' => 'Item Group Code'], ['item_group_code']],
        'item-code' => ['master_item_code', ['item_code' => 'Item Code'], ['item_code']],
        'pca' => ['master_pca', ['pca_code' => 'PCA Code'], ['pca_code']],
        'location' => ['master_location', ['loc_code' => 'Location Code', 'group_type' => 'Group Type'], ['loc_code']],
        'project' => ['master_project', ['project_code' => 'Project Code'], ['project_code']],
        'project-detail' => ['master_project_detail', ['project_id' => 'Project', 'project_code_client' => 'Project Code Client', 'company_id' => 'Company'], ['project_code_client']],
        'vendor' => ['master_vendor', ['vendor_code' => 'Vendor Code'], ['vendor_code']],
    ];

    public function canChangeKey(): bool
    {
        return function_exists('checkPermission') && (bool) checkPermission('is_admin');
    }

    /**
     * Normalisasi & validasi request sebelum validasi lain di store().
     *
     * @throws ValidationException
     */
    public function prepare(Request $request, string $entity): void
    {
        [$table, $keys, $codes] = self::KEYS[$entity];

        $upper = [];
        foreach ($codes as $field) {
            if ($request->filled($field)) {
                $upper[$field] = strtoupper(trim((string) $request->input($field)));
            }
        }
        $request->merge($upper);

        if (! $request->filled('id')) {
            return;
        }

        $current = DB::table($table)->where('id', $request->input('id'))->first(array_keys($keys));
        if (! $current) {
            return;
        }

        $missing = [];
        $changed = [];
        foreach ($keys as $field => $label) {
            $old = (string) ($current->{$field} ?? '');
            if (! $request->has($field)) {
                $missing[$field] = $current->{$field}; // input terkunci/disabled tidak ikut terkirim

                continue;
            }
            $new = (string) $request->input($field);
            if (in_array($field, $codes, true) ? strtoupper($old) !== $new : $old !== $new) {
                $changed[$field] = $label;
            }
        }
        $request->merge($missing);

        if ($changed === []) {
            return;
        }

        if (! $this->canChangeKey()) {
            throw ValidationException::withMessages(array_map(
                fn ($label) => ["{$label} adalah kunci unik dan tidak boleh diubah. Hubungi admin bila perlu koreksi."],
                $changed,
            ));
        }

        if (! $request->boolean(self::CONFIRM_FIELD)) {
            throw ValidationException::withMessages([
                array_key_first($changed) => ['Perubahan kunci unik ('.implode(', ', $changed).') harus dikonfirmasi sebelum disimpan.'],
            ]);
        }
    }

    /**
     * Konfigurasi untuk partial view components.unique-key-guard.
     *
     * @return array{fields:array<string,string>, codes:list<string>, locked:bool, confirm:bool, original:array<string,string>, confirm_field:string}
     */
    public function viewConfig(string $entity, ?object $param): array
    {
        [, $keys, $codes] = self::KEYS[$entity];
        $isEdit = $param !== null && ! empty($param->id);

        $original = [];
        foreach (array_keys($keys) as $field) {
            $original[$field] = $isEdit ? (string) ($param->{$field} ?? '') : '';
        }

        return [
            'fields' => $keys,
            'codes' => $codes,
            'locked' => $isEdit && ! $this->canChangeKey(),
            'confirm' => $isEdit && $this->canChangeKey(),
            'original' => $original,
            'confirm_field' => self::CONFIRM_FIELD,
        ];
    }
}
