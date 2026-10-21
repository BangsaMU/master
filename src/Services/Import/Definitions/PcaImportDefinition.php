<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Services\Import\SimpleCodeImportDefinition;

class PcaImportDefinition extends SimpleCodeImportDefinition
{
    public function entity(): string
    {
        return 'pca';
    }

    public function label(): string
    {
        return 'PCA';
    }

    public function table(): string
    {
        return 'master_pca';
    }

    public function permission(): string
    {
        return 'pca';
    }

    protected function keyField(): string
    {
        return 'pca_code';
    }

    protected function modelClass(): string
    {
        return Pca::class;
    }

    protected function columns(): array
    {
        return [
            'pca_code' => ['label' => 'PCA Code', 'aliases' => ['pcacode'], 'max' => 20, 'required' => true],
            'pca_name' => ['label' => 'PCA Name', 'aliases' => ['pcaname'], 'max' => 150, 'required' => true],
        ];
    }
}
