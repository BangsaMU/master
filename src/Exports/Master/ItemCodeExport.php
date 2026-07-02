<?php

namespace Bangsamu\Master\Exports\Master;

use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemCodeExport implements FromCollection, ShouldAutoSize, WithHeadings
{

    public function collection()
    {
        $query = DB::table('master_item_code as mic')
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
            ->whereNull('mic.deleted_at');

        $settings = Setting::where('category', 'master_item_code')->get();

        if ($settings->isEmpty()) {
            $settings = DashboardSettings::where('group', 'master_item_code')->get();
        }

        if (!$settings->isEmpty()) {
            $query->where(function ($q) use ($settings) {
                foreach ($settings as $setting) {
                    $column = $setting->name ?? $setting->key;
                    $values = array_values(array_filter(array_map('trim', explode(',', (string) ($setting->value ?? '')))));

                    if (!empty($column) && !empty($values) && Schema::hasColumn('master_item_code', $column)) {
                        $q->orWhereIn("mic.$column", $values);
                    }
                }
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        $headers = ['Item Code', 'Item Name', 'UoM', 'PCA', 'Category', 'Item Group'];

        return $headers;
    }
}
