<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Illuminate\Support\Collection;

/**
 * Unik: loc_code + group_type.
 */
class LocationImportDefinition extends MasterImportDefinition
{
    public const GROUP_TYPES = ['office', 'warehouse', 'vendor', 'clinic', 'ohih', 'mcu', 'hrd'];

    protected Collection $existing;

    public function entity(): string
    {
        return 'location';
    }

    public function label(): string
    {
        return 'Location';
    }

    public function table(): string
    {
        return 'master_location';
    }

    public function permission(): string
    {
        return 'location';
    }

    public function keyLabel(): string
    {
        return 'Location Code + Group Type';
    }

    public function fields(): array
    {
        return [
            'loc_code' => 'Location Code',
            'loc_name' => 'Location Name',
            'group_type' => 'Group Type',
        ];
    }

    public function updatableFields(): array
    {
        return ['loc_name'];
    }

    public function normalize(array $raw): array
    {
        $group = strtolower($this->str($raw, ['group_type', 'grouptype']));

        return [
            'loc_code' => self::upper($this->str($raw, ['location_code', 'loc_code'])),
            'loc_name' => $this->str($raw, ['location_name', 'loc_name']),
            'group_type' => $group === '' ? 'office' : $group,
        ];
    }

    public function validate(array $data): ?string
    {
        return match (true) {
            $data['loc_code'] === '' => 'Location Code wajib diisi.',
            mb_strlen($data['loc_code']) > 15 => 'Location Code melebihi 15 karakter.',
            $data['loc_name'] === '' => 'Location Name wajib diisi.',
            mb_strlen($data['loc_name']) > 50 => 'Location Name melebihi 50 karakter.',
            ! in_array($data['group_type'], self::GROUP_TYPES, true) => "Group Type '{$data['group_type']}' tidak valid. Pilihan: ".implode(', ', self::GROUP_TYPES).'.',
            default => null,
        };
    }

    public function key(array $data): string
    {
        return $data['loc_code'].'|'.$data['group_type'];
    }

    public function keyDisplay(array $data): string
    {
        return $data['loc_code'].' / '.$data['group_type'];
    }

    public function prepare(array $rows): void
    {
        $this->existing = $this->fetchByValues('master_location', 'loc_code', array_column($rows, 'loc_code'), ['id', 'loc_code', 'loc_name', 'group_type', 'deleted_at'])
            ->keyBy(fn ($row) => self::upper($row->loc_code).'|'.strtolower(trim((string) $row->group_type)));
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));

        return $row ? [
            'id' => (int) $row->id,
            'trashed' => $row->deleted_at !== null,
            'owner' => null,
            'values' => ['loc_code' => (string) $row->loc_code, 'loc_name' => (string) $row->loc_name, 'group_type' => (string) $row->group_type],
        ] : null;
    }

    public function create(array $data): array
    {
        $model = new Location;
        $model->loc_code = $data['loc_code'];
        $model->loc_name = $data['loc_name'];
        $model->group_type = $data['group_type'];

        return [$this->saveNew($model)];
    }

    public function update(int $id, array $changes, array $data): array
    {
        $log = $this->saveChanges(Location::findOrFail($id), array_map(fn ($c) => $c['new'], $changes));

        return $log ? [$log] : [];
    }
}
