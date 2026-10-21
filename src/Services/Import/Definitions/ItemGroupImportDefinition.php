<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Services\Import\SimpleCodeImportDefinition;

class ItemGroupImportDefinition extends SimpleCodeImportDefinition
{
    public function entity(): string
    {
        return 'item-group';
    }

    public function label(): string
    {
        return 'Item Group';
    }

    public function table(): string
    {
        return 'master_item_group';
    }

    public function permission(): string
    {
        return 'itemgroup';
    }

    protected function keyField(): string
    {
        return 'item_group_code';
    }

    protected function modelClass(): string
    {
        return ItemGroup::class;
    }

    protected function columns(): array
    {
        return [
            'item_group_code' => ['label' => 'Item Group Code', 'aliases' => ['itemgroupcode', 'group_code', 'item_group'], 'max' => 25, 'required' => true],
            'item_group_name' => ['label' => 'Item Group Name', 'aliases' => ['itemgroupname', 'group_name'], 'max' => 55, 'required' => true],
        ];
    }
}
