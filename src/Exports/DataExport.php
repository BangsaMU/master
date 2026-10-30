<?php

namespace Bangsamu\Master\Exports;

use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Bangsamu\Master\Services\TableAccessPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    protected $table;

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function collection(): Collection
    {
        $databaseConfig = 'db_master'; // Sesuaikan dengan konfigurasi database Anda

        $query = DB::connection($databaseConfig)
            ->table($this->table)
            ->select($this->exportColumns($databaseConfig))
            ->whereNull('deleted_at');

        $filters = $this->getDynamicFilters($databaseConfig);

        if (! empty($filters)) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters as $filter) {
                    $q->orWhereIn($filter['column'], $filter['values']);
                }
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return $this->exportColumns('db_master');
    }

    /**
     * Semua kolom tabel kecuali id/timestamp dan kolom rahasia TableAccessPolicy (password, remember_token, api_token,
     * MasterCrudConfig.SELECT2_HIDDEN_COLUMNS).
     *
     * @return list<string>
     */
    protected function exportColumns(string $databaseConfig): array
    {
        $policy = app(TableAccessPolicy::class);

        return array_values(array_filter(
            Schema::connection($databaseConfig)->getColumnListing($this->table),
            fn ($column) => ! in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true) && ! $policy->isHiddenColumn($column)
        ));
    }

    protected function getDynamicFilters(string $databaseConfig): array
    {
        $settings = Setting::where('category', $this->table)->get();

        if ($settings->isEmpty()) {
            $settings = DashboardSettings::where('group', $this->table)->get();
        }

        $filters = [];

        foreach ($settings as $setting) {
            $column = $setting->name ?? $setting->key;
            $values = array_values(array_filter(array_map('trim', explode(',', (string) ($setting->value ?? '')))));

            if (! empty($column) && ! empty($values) && Schema::connection($databaseConfig)->hasColumn($this->table, $column)) {
                $filters[] = [
                    'column' => $column,
                    'values' => $values,
                ];
            }
        }

        return $filters;
    }
}
