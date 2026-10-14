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

class VendorTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new VendorTemplate,
        ];
    }
}

class VendorTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Vendor Import Template';
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
                'VEND001',
                'PT. Example Vendor Indonesia',
                'Jl. Jenderal Sudirman No. 123, Jakarta',
                '021-5551234',
                '021-5555678',
                'info@examplevendor.com',
                'John Doe',
                '08123456789',
                'johndoe@examplevendor.com',
                '021-5559999',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Vendor Code',
            'Vendor Name',
            'Vendor Address',
            'Vendor Phone',
            'Vendor Fax',
            'Vendor Email',
            'Vendor Contact Name',
            'Vendor Contact Phone',
            'Vendor Contact Email',
            'Vendor Contact Fax',
        ];
    }
}
