<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Services\Import\SimpleCodeImportDefinition;

class CategoryImportDefinition extends SimpleCodeImportDefinition
{
    public function entity(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'Category';
    }

    public function table(): string
    {
        return 'master_category';
    }

    public function permission(): string
    {
        return 'category';
    }

    protected function keyField(): string
    {
        return 'category_code';
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function columns(): array
    {
        return [
            'category_code' => ['label' => 'Category Code', 'aliases' => ['categorycode', 'code'], 'max' => 55, 'required' => true],
            'category_name' => ['label' => 'Category Name', 'aliases' => ['categoryname', 'name'], 'max' => 110, 'required' => true],
            'remark' => ['label' => 'Remark', 'aliases' => ['description'], 'max' => 255, 'required' => false],
        ];
    }
}
