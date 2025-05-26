<?php

namespace Bangsamu\Master\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

use Illuminate\Support\Collection;
use DB;
use Carbon\Carbon;

class ProcurementItemExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProcurementItemTemplate(),
            // new ProcurementItemList(),
        ];
    }
}

class ProcurementItemTemplate implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
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
        // Fetch data for your first sheet here (e.g., from a database query)
        return collect([
            ['OFFC-EQP-00001', '2024-08-15','P1','2,5','Your remarks here...','Your Scope of works here..'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Item Code',
            'Required Date',
            'Priority',
            'Quantity',
            'Remark',
            'Scope of Work',
        ];
    }
}

class ProcurementItemList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle
{
    public function title(): string
    {
        return 'Item Code Sheet';
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
            // 'No',
            'Item Code',
            'Item Name',
            'UoM',
            'PCA',
            'Category',
            'Item Group',
        ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $datas = DB::table('master_item_code as mic')
            ->select(
                'mic.id as no',
                'mic.item_code as item_code',
                'mic.item_name as item_name',
                'mu.uom_name as uom',
                'mp.pca_name as pca',
                'mc.category_name as category',
                'mig.item_group_name as item_group'
            )
            ->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
            ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
            ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
            ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
            ->whereNull('mic.deleted_at')
            ->groupBy('mic.id')
            ->orderByDesc('mic.created_at')
            ->get();

        $collections = [];

        foreach($datas as $key => $item){
            // Create the collection
            $collections[$key] = [
                // 'no' => $item->no,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'uom' => $item->uom,
                'pca' => $item->pca,
                'category' => $item->category,
                'item_group' => $item->item_group,
            ];
        }

        return collect($collections);

    }
}
