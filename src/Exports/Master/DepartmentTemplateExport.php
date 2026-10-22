<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepartmentTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DepartmentTemplate,
        ];
    }
}

class DepartmentTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - Department';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function collection()
    {
        // app_code tidak diisi di file: otomatis memakai aplikasi yang mengimport
        return collect([
            ['ACC', 'Accounting Department'],
            ['IT', 'Information Technology Department'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Department Code',
            'Department Name',
        ];
    }
}
