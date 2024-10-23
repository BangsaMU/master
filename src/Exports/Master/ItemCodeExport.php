<?php

namespace App\Exports\Master;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemCodeExport implements FromCollection, ShouldAutoSize, WithHeadings
{

    public function collection()
    {
        $data = DB::table('master_item_code as mic')
                    ->leftJoin('master_uom as mu', 'mu.id', 'mic.uom_id')
                    ->leftJoin('master_pca as mp', 'mp.id', 'mic.pca_id')
                    ->leftJoin('master_category as mc', 'mc.id', 'mic.category_id')
                    ->leftJoin('master_item_group as mig', 'mig.id', 'mic.group_id')
                    ->select(
                        'mic.item_code',
                        'mic.item_name',
                        'mu.uom_name',
                        'mp.pca_name',
                        'mc.category_name',
                        'mig.item_group_name',
                    )
                    ->whereNull('mic.deleted_at')
                    ->get();
                                
        return $data;
    }

    public function headings(): array
    {
        $headers = ['Item Code', 'Item Name', 'UoM', 'PCA', 'Category', 'Item Group'];

        return $headers;
    }
}
