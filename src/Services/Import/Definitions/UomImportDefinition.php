<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Uom;
use Bangsamu\Master\Services\Import\SimpleCodeImportDefinition;
use Illuminate\Support\Collection;

class UomImportDefinition extends SimpleCodeImportDefinition
{
    /** UOM existing keyed by nama upper (uom_name juga unik). */
    protected Collection $existingNames;

    public function entity(): string
    {
        return 'uom';
    }

    public function label(): string
    {
        return 'UOM';
    }

    public function table(): string
    {
        return 'master_uom';
    }

    public function permission(): string
    {
        return 'uom';
    }

    protected function keyField(): string
    {
        return 'uom_code';
    }

    protected function modelClass(): string
    {
        return Uom::class;
    }

    protected function columns(): array
    {
        return [
            'uom_code' => ['label' => 'UOM Code', 'aliases' => ['uomcode', 'code'], 'max' => 10, 'required' => true],
            'uom_name' => ['label' => 'UOM Name', 'aliases' => ['uomname', 'name'], 'max' => 25, 'required' => true],
        ];
    }

    public function prepare(array $rows): void
    {
        parent::prepare($rows);

        $this->existingNames = $this->fetchByValues('master_uom', 'uom_name', array_map(fn ($r) => $r['uom_name'], $rows), ['id', 'uom_code', 'uom_name'])
            ->keyBy(fn ($row) => self::upper($row->uom_name));
    }

    public function check(array $data, ?array $existing): ?string
    {
        $owner = $this->existingNames->get(self::upper($data['uom_name']));
        if ($owner && self::upper($owner->uom_code) !== $data['uom_code']) {
            return "UOM Name '{$data['uom_name']}' sudah dipakai UOM Code '{$owner->uom_code}'.";
        }

        return null;
    }
}
