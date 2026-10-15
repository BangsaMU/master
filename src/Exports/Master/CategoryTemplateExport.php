<?php

declare(strict_types=1);

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CategoryTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new CategoryTemplate,
        ];
    }
}

class CategoryTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - Category';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function collection()
    {
        return collect([
            ['CAT001', 'Electrical', 'Electrical components and wiring'],
            ['CAT002', 'Mechanical', 'Mechanical parts and fittings'],
            ['CAT003', 'Civil', 'Civil works and materials'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Category Code',
            'Category Name',
            'Remark',
        ];
    }
}
