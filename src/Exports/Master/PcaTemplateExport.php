<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PcaTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PcaTemplate,
        ];
    }
}

class PcaTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - PCA';
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
            ['PCA-001', 'Project Cost Account 001'],
            ['PCA-002', 'Project Cost Account 002'],
        ]);
    }

    public function headings(): array
    {
        return [
            'PCA Code',
            'PCA Name',
        ];
    }
}
