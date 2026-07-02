<?php

namespace Bangsamu\Master\Exports;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
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

    public function collection()
    {
        $databaseConfig = 'db_master'; // Sesuaikan dengan konfigurasi database Anda
        $headers = [];
        $table_columns = json_decode(LibraryClayController::get_filed_toJson($this->table,$databaseConfig));

        foreach ($table_columns as $key => $col) {
            if (
                $key != 'id' &&
                $key != 'created_at' &&
                $key != 'updated_at' &&
                $key != 'deleted_at'
            ) {
                array_push($headers, $key);
            }
        }

        $query = DB::connection($databaseConfig)
            ->table($this->table)
            ->select($headers)
            ->whereNull('deleted_at');

        $filters = $this->getDynamicFilters($databaseConfig);

        if (!empty($filters)) {
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
        $headers = [];
        $table_columns = json_decode(LibraryClayController::get_filed_toJson($this->table,'db_master'));

        foreach ($table_columns as $key => $col) {
            if (
                $key != 'id' &&
                $key != 'created_at' &&
                $key != 'updated_at' &&
                $key != 'deleted_at'
            ) {
                array_push($headers, $key);
            }
        }

        return $headers;
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

            if (!empty($column) && !empty($values) && Schema::connection($databaseConfig)->hasColumn($this->table, $column)) {
                $filters[] = [
                    'column' => $column,
                    'values' => $values,
                ];
            }
        }

        return $filters;
    }
}
