<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Bangsamu\Master\Services\VendorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Unik: vendor_code. Penyimpanan lewat VendorService (sinkron location vendor + DB master).
 */
class VendorImportDefinition extends MasterImportDefinition
{
    protected const VENDOR_FIELDS = ['vendor_code', 'vendor_description', 'vendor_address', 'vendor_phone', 'vendor_fax', 'vendor_email'];

    protected const CONTACT_FIELDS = ['vendor_contact_name', 'vendor_contact_phone', 'vendor_contact_email', 'vendor_contact_fax'];

    protected Collection $existing;

    public function __construct(protected VendorService $vendorService) {}

    public function entity(): string
    {
        return 'vendor';
    }

    public function label(): string
    {
        return 'Vendor';
    }

    public function table(): string
    {
        return 'master_vendor';
    }

    public function permission(): string
    {
        return 'vendor';
    }

    public function keyLabel(): string
    {
        return 'Vendor Code';
    }

    public function fields(): array
    {
        return [
            'vendor_code' => 'Vendor Code',
            'vendor_description' => 'Vendor Name',
            'vendor_address' => 'Address',
            'vendor_phone' => 'Phone',
            'vendor_fax' => 'Fax',
            'vendor_email' => 'Email',
            'vendor_contact_name' => 'Contact Name',
            'vendor_contact_phone' => 'Contact Phone',
            'vendor_contact_email' => 'Contact Email',
            'vendor_contact_fax' => 'Contact Fax',
        ];
    }

    public function updatableFields(): array
    {
        return array_values(array_diff(array_keys($this->fields()), ['vendor_code']));
    }

    public function normalize(array $raw): array
    {
        return [
            'vendor_code' => self::upper(preg_replace('/[\x00-\x1F\x7F\xA0]/u', ' ', $this->str($raw, ['vendor_code']))),
            'vendor_description' => $this->str($raw, ['vendor_name', 'vendor_description']),
            'vendor_address' => $this->str($raw, ['vendor_address']),
            'vendor_phone' => $this->str($raw, ['vendor_phone']),
            'vendor_fax' => $this->str($raw, ['vendor_fax']),
            'vendor_email' => $this->str($raw, ['vendor_email']),
            'vendor_contact_name' => $this->str($raw, ['vendor_contact_name', 'contact_name']),
            'vendor_contact_phone' => $this->str($raw, ['vendor_contact_phone', 'contact_phone']),
            'vendor_contact_email' => $this->str($raw, ['vendor_contact_email', 'contact_email']),
            'vendor_contact_fax' => $this->str($raw, ['vendor_contact_fax', 'contact_fax']),
        ];
    }

    public function validate(array $data): ?string
    {
        foreach (['vendor_phone', 'vendor_fax', 'vendor_email', 'vendor_contact_name', 'vendor_contact_phone', 'vendor_contact_email', 'vendor_contact_fax'] as $field) {
            if (mb_strlen($data[$field]) > 100) {
                return $this->fields()[$field].' melebihi 100 karakter.';
            }
        }

        return match (true) {
            $data['vendor_code'] === '' => 'Vendor Code wajib diisi.',
            mb_strlen($data['vendor_code']) > 15 => 'Vendor Code melebihi 15 karakter.',
            $data['vendor_description'] === '' => 'Vendor Name wajib diisi.',
            $data['vendor_address'] === '' => 'Vendor Address wajib diisi.',
            mb_strlen($data['vendor_description']) > 255 => 'Vendor Name melebihi 255 karakter.',
            mb_strlen($data['vendor_address']) > 255 => 'Vendor Address melebihi 255 karakter.',
            default => null,
        };
    }

    public function key(array $data): string
    {
        return $data['vendor_code'];
    }

    public function prepare(array $rows): void
    {
        $vendors = $this->fetchByValues('master_vendor', 'vendor_code', array_column($rows, 'vendor_code'), array_merge(['id', 'deleted_at'], self::VENDOR_FIELDS))
            ->sortBy(fn ($row) => $row->deleted_at === null ? 1 : 0) // data aktif menimpa yang soft delete
            ->keyBy(fn ($row) => self::upper($row->vendor_code));

        $contacts = $this->fetchByValues('master_vendor_contact', 'vendor_id', $vendors->pluck('id')->all(), array_merge(['vendor_id', 'deleted_at'], self::CONTACT_FIELDS))
            ->whereNull('deleted_at')
            ->keyBy('vendor_id');

        $this->existing = $vendors->map(fn ($v) => ['vendor' => $v, 'contact' => $contacts->get($v->id)]);
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));
        if (! $row) {
            return null;
        }

        $values = [];
        foreach (self::VENDOR_FIELDS as $field) {
            $values[$field] = (string) ($row['vendor']->{$field} ?? '');
        }
        foreach (self::CONTACT_FIELDS as $field) {
            $values[$field] = (string) ($row['contact']->{$field} ?? '');
        }

        return ['id' => (int) $row['vendor']->id, 'trashed' => $row['vendor']->deleted_at !== null, 'owner' => null, 'values' => $values];
    }

    public function notes(array $data): array
    {
        return ["Location vendor '{$data['vendor_code']}' ikut disinkronkan."];
    }

    public function create(array $data): array
    {
        $result = Model::withoutEvents(fn () => $this->vendorService->saveVendor(
            $this->pick($data, self::VENDOR_FIELDS),
            $this->pick($data, self::CONTACT_FIELDS),
        ));

        $vendor = $result['vendor'];

        return [[
            'model' => $vendor,
            'action' => 'created',
            'before' => [],
            'after' => array_merge($vendor->attributesToArray(), $this->pick($data, self::CONTACT_FIELDS)),
        ]];
    }

    public function update(int $id, array $changes, array $data): array
    {
        $current = $this->existing->get($this->key($data));
        $merged = $this->existing($data)['values'];
        foreach ($changes as $field => $change) {
            $merged[$field] = $change['new'];
        }

        $result = Model::withoutEvents(fn () => $this->vendorService->saveVendor(
            $this->pick($merged, self::VENDOR_FIELDS),
            $this->pick($merged, self::CONTACT_FIELDS),
            (int) $current['vendor']->id,
        ));

        return [[
            'model' => $result['vendor'],
            'action' => 'updated',
            'before' => array_map(fn ($c) => $c['old'], $changes),
            'after' => array_map(fn ($c) => $c['new'], $changes),
        ]];
    }

    /**
     * @return array<string, string|null>
     */
    protected function pick(array $data, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = ($data[$field] ?? '') !== '' ? $data[$field] : null;
        }

        return $result;
    }
}
