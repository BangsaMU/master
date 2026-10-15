<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProjectTemplate,
        ];
    }
}

class ProjectTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template Sheet';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function collection()
    {
        return collect([
            [
                'PRJ-001',
                'Project Alpha Construction',
                'I',
                '2026-01-01',
                '2026-12-31',
                'Remark for project',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Project Code',
            'Project Name',
            'Project Type',
            'Project Start Date',
            'Project Complete Date',
            'Project Remarks',
        ];
    }
}
