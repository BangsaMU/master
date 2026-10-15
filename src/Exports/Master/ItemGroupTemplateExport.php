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

class ItemGroupTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ItemGroupTemplate,
        ];
    }
}

class ItemGroupTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - Item Group';
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
            ['GRP001', 'Raw Materials'],
            ['GRP002', 'Consumables'],
            ['GRP003', 'Tools & Equipment'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Item Group Code',
            'Item Group Name',
        ];
    }
}
