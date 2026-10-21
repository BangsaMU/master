<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Definisi import untuk master sederhana: satu kolom kode unik + beberapa kolom teks.
 * Nama kolom ter-normalisasi = nama kolom tabel.
 */
abstract class SimpleCodeImportDefinition extends MasterImportDefinition
{
    /** Kolom kode unik di tabel, contoh: 'category_code'. */
    abstract protected function keyField(): string;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /**
     * Spesifikasi kolom: field => [label, aliases, max, required].
     *
     * @return array<string, array{label:string, aliases:list<string>, max:int, required:bool}>
     */
    abstract protected function columns(): array;

    /** Existing keyed by kode upper. */
    protected Collection $existing;

    public function fields(): array
    {
        return array_map(fn ($c) => $c['label'], $this->columns());
    }

    public function updatableFields(): array
    {
        return array_values(array_diff(array_keys($this->columns()), [$this->keyField()]));
    }

    public function keyLabel(): string
    {
        return $this->columns()[$this->keyField()]['label'];
    }

    public function ownershipColumn(): ?string
    {
        return 'app_code';
    }

    public function masterSyncKey(): ?string
    {
        return $this->keyField();
    }

    public function normalize(array $raw): array
    {
        $data = [];
        foreach ($this->columns() as $field => $spec) {
            $data[$field] = $this->str($raw, array_merge([$field], $spec['aliases']));
        }
        $data[$this->keyField()] = self::upper($data[$this->keyField()]);

        return $data;
    }

    public function validate(array $data): ?string
    {
        foreach ($this->columns() as $field => $spec) {
            if ($spec['required'] && $data[$field] === '') {
                return "{$spec['label']} wajib diisi.";
            }
            if (mb_strlen($data[$field]) > $spec['max']) {
                return "{$spec['label']} melebihi {$spec['max']} karakter.";
            }
        }

        return null;
    }

    public function key(array $data): string
    {
        return $data[$this->keyField()];
    }

    public function prepare(array $rows): void
    {
        $model = new ($this->modelClass());
        $columns = array_unique(array_merge(['id', 'deleted_at'], array_keys($this->columns()), (array) $this->ownershipColumn()));

        $this->existing = $this->fetchByValues($model->getTable(), $this->keyField(), array_map(fn ($r) => $this->key($r), $rows), $columns)
            ->keyBy(fn ($row) => self::upper($row->{$this->keyField()}));
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));
        if (! $row) {
            return null;
        }

        $values = [];
        foreach (array_keys($this->columns()) as $field) {
            $values[$field] = (string) ($row->{$field} ?? '');
        }

        return [
            'id' => (int) $row->id,
            'trashed' => $row->deleted_at !== null,
            'owner' => $this->ownershipColumn() ? ($row->{$this->ownershipColumn()} ?? null) : null,
            'values' => $values,
        ];
    }

    public function create(array $data): array
    {
        $class = $this->modelClass();
        $model = new $class;
        foreach (array_keys($this->columns()) as $field) {
            $model->{$field} = $data[$field] !== '' ? $data[$field] : null;
        }
        if ($this->ownershipColumn()) {
            $model->{$this->ownershipColumn()} = $this->appCode();
        }

        return [$this->saveNew($model)];
    }

    public function update(int $id, array $changes, array $data): array
    {
        $class = $this->modelClass();
        $model = $class::findOrFail($id);
        $log = $this->saveChanges($model, array_map(fn ($c) => $c['new'], $changes));

        return $log ? [$log] : [];
    }
}
