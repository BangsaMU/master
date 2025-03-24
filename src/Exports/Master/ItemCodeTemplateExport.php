<?php

namespace Bangsamu\Master\Exports\Master;

use Bangsamu\Master\Models\MasterCategory;
use Bangsamu\Master\Models\MasterItemGroup;
use Bangsamu\Master\Models\MasterPca;
use Bangsamu\Master\Models\MasterUom;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemCodeTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ItemCodeTemplate(),
            new UomList(),
            new PcaList(),
            new CategoryList(),
            new ItemGroupList(),
        ];
    }
}

class ItemCodeTemplate implements ShouldAutoSize, FromCollection, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function collection()
    {
        return collect([
            [
                'HYG-0004',
                'Insecticides, SERUNI 100 EC @ 1 Ltr, Metro Chemical',
                'BOT',
                // 'Bottle',
                'PCA-6300.008',
                // 'VESSEL GENERAL MATERIAL',
                'A04',
                // 'A04',
                'HSE-HYG',
                // 'HSE-HYGINE'
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Item Code',
            'Item Name',
            'UoM Code',
            'PCA Code',
            'Category Code',
            'Item Group Code',
            'Size 1',
            'Size 2',
            'Unit Weight',
        ];
    }
}

class UomList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
{
    public function title(): string
    {
        return 'Unit of Measure (UoM) Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        return [
            'UoM Code',
            'UoM Name',
        ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $datas = MasterUom::select('uom_code', 'uom_name')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $collections = [];

        foreach($datas as $key => $item){
            // Create the collection
            $collections[$key] = [
                'uom_code' => $item->uom_code,
                'uom_name' => $item->uom_name,
            ];
        }

        return collect($collections);
    }
}

class PcaList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
{
    public function title(): string
    {
        return 'PCA Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        return [
            'PCA Code',
            'PCA Name',
        ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $datas = MasterPca::select('pca_code', 'pca_name')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $collections = [];

        foreach($datas as $key => $item){
            // Create the collection
            $collections[$key] = [
                'pca_code' => $item->pca_code,
                'pca_name' => $item->pca_name,
            ];
        }

        return collect($collections);
    }
}

class CategoryList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
{
    public function title(): string
    {
        return 'Category Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        return [
            'Category Code',
            'Category Name',
        ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $datas = MasterCategory::select('category_code', 'category_name')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $collections = [];

        foreach($datas as $key => $item){
            // Create the collection
            $collections[$key] = [
                'category_code' => $item->category_code,
                'category_name' => $item->category_name,
            ];
        }

        return collect($collections);
    }
}

class ItemGroupList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
{
    public function title(): string
    {
        return 'Item Group Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        return [
            'Item Group Code',
            'Item Group Name',
        ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $datas = MasterItemGroup::select('item_group_code', 'item_group_name')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $collections = [];

        foreach($datas as $key => $item){
            // Create the collection
            $collections[$key] = [
                'item_group_code' => $item->item_group_code,
                'item_group_name' => $item->item_group_name,
            ];
        }

        return collect($collections);
    }
}
