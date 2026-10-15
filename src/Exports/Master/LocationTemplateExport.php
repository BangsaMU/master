<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LocationTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new LocationTemplate,
        ];
    }
}

class LocationTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - Location';
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
            ['LOC-001', 'Head Office Jakarta', 'office'],
            ['LOC-002', 'Central Warehouse Balaraja', 'warehouse'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Location Code',
            'Location Name',
            'Group Type',
        ];
    }
}
