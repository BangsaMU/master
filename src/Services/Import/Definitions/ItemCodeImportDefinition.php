<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Models\Uom;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Unik: item_code. Kolom posisi: Item Code, Item Name, UoM, PCA, Category, Item Group, lalu kolom atribut.
 * Referensi (UOM/PCA/Category/Item Group) yang belum ada dibuat otomatis, sama seperti import lama.
 */
class ItemCodeImportDefinition extends MasterImportDefinition
{
    /** Referensi: ref => [tabel, kolom kode, kolom nama, model, kolom id di item code, label]. */
    protected const REFS = [
        'uom_code' => ['master_uom', 'uom_code', 'uom_name', Uom::class, 'uom_id', 'UOM'],
        'pca_code' => ['master_pca', 'pca_code', 'pca_name', Pca::class, 'pca_id', 'PCA'],
        'category_code' => ['master_category', 'category_code', 'category_name', Category::class, 'category_id', 'Category'],
        'item_group_code' => ['master_item_group', 'item_group_code', 'item_group_name', ItemGroup::class, 'group_id', 'Item Group'],
    ];

    /** @var list<string> nama atribut dari header kolom ke-7 dst. */
    protected array $attributeKeys = [];

    protected Collection $existing;

    /** @var array<string, Collection> ref => kode upper => row */
    protected array $refByCode = [];

    /** @var array<string, Collection> ref => id => row */
    protected array $refById = [];

    public function entity(): string
    {
        return 'item-code';
    }

    public function label(): string
    {
        return 'Item Code';
    }

    public function table(): string
    {
        return 'master_item_code';
    }

    public function permission(): string
    {
        return 'item_code';
    }

    public function keyLabel(): string
    {
        return 'Item Code';
    }

    public function ownershipColumn(): ?string
    {
        return 'app_code';
    }

    public function masterSyncKey(): ?string
    {
        return 'item_code';
    }

    public function masterSyncKeyFor(Model $model): ?string
    {
        if ($model->getTable() === $this->table()) {
            return 'item_code';
        }
        foreach (self::REFS as [$table, $codeColumn]) {
            if ($model->getTable() === $table) {
                return $codeColumn;
            }
        }

        return null;
    }

    public function fields(): array
    {
        return [
            'item_code' => 'Item Code',
            'item_name' => 'Item Name',
            'uom_code' => 'UoM Code',
            'pca_code' => 'PCA Code',
            'category_code' => 'Category Code',
            'item_group_code' => 'Item Group Code',
            'attributes' => 'Attributes',
        ];
    }

    public function updatableFields(): array
    {
        return ['item_name', 'uom_code', 'pca_code', 'category_code', 'item_group_code', 'attributes'];
    }

    public function usesHeadingRow(): bool
    {
        return false;
    }

    public function readHeader(array $header): void
    {
        $this->attributeKeys = [];
        foreach (array_values($header) as $index => $value) {
            if ($index > 5 && trim((string) $value) !== '') {
                $this->attributeKeys[$index] = strtolower(str_replace(' ', '_', trim((string) $value)));
            }
        }
    }

    public function meta(): array
    {
        return ['attribute_keys' => $this->attributeKeys];
    }

    public function withMeta(array $meta): void
    {
        $this->attributeKeys = $meta['attribute_keys'] ?? [];
    }

    public function normalize(array $raw): array
    {
        $raw = array_values($raw);
        $attributes = [];
        foreach ($this->attributeKeys as $index => $name) {
            $value = $raw[$index] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $attributes[$name] = trim((string) $value);
            }
        }

        return [
            'item_code' => self::upper((string) ($raw[0] ?? '')),
            'item_name' => trim((string) ($raw[1] ?? '')),
            'uom_code' => self::upper((string) ($raw[2] ?? '')),
            'pca_code' => self::upper((string) ($raw[3] ?? '')),
            'category_code' => self::upper((string) ($raw[4] ?? '')),
            'item_group_code' => self::upper((string) ($raw[5] ?? '')),
            'attributes' => $attributes ? json_encode($attributes) : '',
        ];
    }

    public function validate(array $data): ?string
    {
        return match (true) {
            $data['item_code'] === '' => 'Item Code wajib diisi.',
            mb_strlen($data['item_code']) > 25 => 'Item Code melebihi 25 karakter.',
            $data['item_name'] === '' => 'Item Name wajib diisi.',
            $data['uom_code'] === '' => 'UoM Code wajib diisi.',
            mb_strlen($data['uom_code']) > 10 => 'UoM Code melebihi 10 karakter.',
            mb_strlen($data['pca_code']) > 20 => 'PCA Code melebihi 20 karakter.',
            $data['category_code'] === '' => 'Category Code wajib diisi.',
            mb_strlen($data['category_code']) > 55 => 'Category Code melebihi 55 karakter.',
            $data['item_group_code'] === '' => 'Item Group Code wajib diisi.',
            mb_strlen($data['item_group_code']) > 25 => 'Item Group Code melebihi 25 karakter.',
            default => null,
        };
    }

    public function key(array $data): string
    {
        return $data['item_code'];
    }

    public function prepare(array $rows): void
    {
        $this->existing = $this->fetchByValues('master_item_code', 'item_code', array_column($rows, 'item_code'),
            ['id', 'item_code', 'item_name', 'uom_id', 'pca_id', 'category_id', 'group_id', 'attributes', 'app_code', 'deleted_at'])
            ->keyBy(fn ($row) => self::upper($row->item_code));

        foreach (self::REFS as $ref => [$table, $codeColumn, , , $idColumn]) {
            $select = ['id', $codeColumn, 'deleted_at'];
            if ($ref === 'item_group_code') {
                $select[] = 'item_group_attributes';
            }

            $rowsRef = $this->fetchByValues($table, $codeColumn, array_column($rows, $ref), $select)
                ->merge($this->fetchByValues($table, 'id', $this->existing->pluck($idColumn)->all(), $select));

            $this->refByCode[$ref] = $rowsRef->keyBy(fn ($r) => self::upper($r->{$codeColumn}));
            $this->refById[$ref] = $rowsRef->keyBy('id');
        }
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));
        if (! $row) {
            return null;
        }

        $values = ['item_code' => (string) $row->item_code, 'item_name' => (string) $row->item_name];
        foreach (self::REFS as $ref => [, $codeColumn, , , $idColumn]) {
            $values[$ref] = (string) ($this->refById[$ref]->get($row->{$idColumn})->{$codeColumn} ?? '');
        }
        $values['attributes'] = self::normalizeAttributes((string) ($row->attributes ?? ''));

        return ['id' => (int) $row->id, 'trashed' => $row->deleted_at !== null, 'owner' => $row->app_code, 'values' => $values];
    }

    /** Samakan format JSON atribut (nilai string) supaya perbandingan tidak tertipu spasi/tipe. */
    protected static function normalizeAttributes(string $json): string
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || $decoded === []) {
            return '';
        }

        return json_encode(array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $decoded));
    }

    /**
     * Atribut: nilai di file digabung ke atribut lama (atribut lama yang tidak ada di file tetap dipertahankan).
     */
    public function mergeValue(string $field, string $old, string $new): string
    {
        if ($field !== 'attributes') {
            return $new;
        }

        $merged = array_merge(json_decode($old, true) ?: [], json_decode($new, true) ?: []);

        return $merged ? json_encode($merged) : '';
    }

    public function check(array $data, ?array $existing): ?string
    {
        if (empty(config('MasterCrudConfig.MASTER_COMPANY_ID'))) {
            return 'Konfigurasi MasterCrudConfig.MASTER_COMPANY_ID belum diisi.';
        }

        foreach (self::REFS as $ref => [, , , , , $label]) {
            $found = $data[$ref] !== '' ? $this->refByCode[$ref]->get($data[$ref]) : null;
            if ($found && $found->deleted_at !== null) {
                return "{$label} '{$data[$ref]}' berstatus terhapus (soft delete).";
            }
        }

        return null;
    }

    public function notes(array $data): array
    {
        $notes = [];
        foreach (self::REFS as $ref => [, , , , , $label]) {
            if ($data[$ref] !== '' && ! $this->refByCode[$ref]->has($data[$ref])) {
                $notes[] = "{$label} '{$data[$ref]}' belum ada, akan dibuat otomatis.";
            }
        }

        $group = $this->refByCode['item_group_code']->get($data['item_group_code']);
        if ($group && $this->attributeKeys) {
            $missing = array_diff($this->attributeKeys, array_keys(json_decode((string) $group->item_group_attributes, true) ?: []));
            if ($missing) {
                $notes[] = "Item Group '{$data['item_group_code']}' akan ditambah atribut: ".implode(', ', $missing).'.';
            }
        }

        return $notes;
    }

    public function create(array $data): array
    {
        $logs = [];
        $model = new ItemCode;
        $model->item_code = $data['item_code'];
        $model->item_name = $data['item_name'];
        $model->remarks = null;
        $model->setAttribute('attributes', $data['attributes'] !== '' ? $data['attributes'] : null);
        $model->app_code = $this->appCode();
        $model->company_id = config('MasterCrudConfig.MASTER_COMPANY_ID');
        $this->assignRefs($model, $data, array_keys(self::REFS), $logs);

        $logs[] = $this->saveNew($model);

        return $logs;
    }

    public function update(int $id, array $changes, array $data): array
    {
        $logs = [];
        $model = ItemCode::findOrFail($id);

        $refChanges = array_intersect(array_keys($changes), array_keys(self::REFS));
        $this->assignRefs($model, $data, $refChanges, $logs);
        if (isset($changes['item_name'])) {
            $model->item_name = $changes['item_name']['new'];
        }
        if (isset($changes['attributes'])) {
            $model->setAttribute('attributes', $changes['attributes']['new'] !== '' ? $changes['attributes']['new'] : null);
        }

        if ($log = $this->saveChanges($model, [])) {
            $logs[] = $log;
        }

        return $logs;
    }

    /**
     * Isi kolom id referensi; buat referensi yang belum ada dan tambah atribut item group.
     *
     * @param  list<string>  $refs
     * @param  list<array>  $logs
     */
    protected function assignRefs(ItemCode $model, array $data, array $refs, array &$logs): void
    {
        foreach ($refs as $ref) {
            [$table, $codeColumn, $nameColumn, $class, $idColumn] = self::REFS[$ref];
            $code = $data[$ref];

            if ($code === '') {
                $model->{$idColumn} = 0; // PCA opsional, sama seperti import lama

                continue;
            }

            $row = $this->refByCode[$ref]->get($code);
            if (! $row) {
                $refModel = new $class;
                $refModel->{$codeColumn} = $code;
                $refModel->{$nameColumn} = $code;
                $refModel->app_code = $this->appCode();
                if ($ref === 'item_group_code') {
                    $refModel->item_group_attributes = json_encode(array_fill_keys(array_values($this->attributeKeys), null));
                }
                $logs[] = $this->saveNew($refModel);

                $row = (object) ['id' => $refModel->id, $codeColumn => $code, 'deleted_at' => null, 'item_group_attributes' => $refModel->item_group_attributes ?? null];
                $this->refByCode[$ref]->put($code, $row);
                $this->refById[$ref]->put($refModel->id, $row);
            } elseif ($ref === 'item_group_code' && $this->attributeKeys) {
                $current = json_decode((string) $row->item_group_attributes, true) ?: [];
                $missing = array_diff($this->attributeKeys, array_keys($current));
                if ($missing) {
                    $group = ItemGroup::findOrFail($row->id);
                    $merged = json_encode(array_merge($current, array_fill_keys(array_values($missing), null)));
                    if ($log = $this->saveChanges($group, ['item_group_attributes' => $merged])) {
                        $logs[] = $log;
                    }
                    $row->item_group_attributes = $merged;
                }
            }

            $model->{$idColumn} = $row->id;
        }
    }
}
