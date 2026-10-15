<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UomTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new UomTemplate,
        ];
    }
}

class UomTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - UOM';
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
            ['PCS', 'Pieces'],
            ['KG',  'Kilogram'],
            ['LTR', 'Liter'],
            ['MTR', 'Meter'],
        ]);
    }

    public function headings(): array
    {
        return [
            'UOM Code',
            'UOM Name',
        ];
    }
}
