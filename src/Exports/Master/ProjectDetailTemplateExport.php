<?php

namespace Bangsamu\Master\Exports\Master;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectDetailTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProjectDetailTemplate,
        ];
    }
}

class ProjectDetailTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Import Template - Project Detail';
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
            [
                'PRJ-001',          // project_code    — must exist in master_project
                'CLIENT-CODE-001',  // project_code_client
                'Client Project Name Here',  // project_name_client
                'ME',               // company_code    — must exist in master_company
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Project Code',
            'Project Code Client',
            'Project Name Client',
            'Company Code',
        ];
    }
}
