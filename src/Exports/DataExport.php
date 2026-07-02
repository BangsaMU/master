<?php

namespace Bangsamu\Master\Exports;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\Setting;
use Illuminate\Support\Facades\DB;
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

        if ($this->table === 'master_location') {
            $groupTypes = $this->getAllowedGroupTypes();

            if (!empty($groupTypes)) {
                $query->whereIn('group_type', $groupTypes);
            }
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

    protected function getAllowedGroupTypes(): array
    {
        $listLocation = Setting::where('name', 'group_type')
            ->where('category', 'master_location')
            ->value('value');

        if (empty($listLocation)) {
            $listLocation = DashboardSettings::where('key', 'group_type')
                ->where('group', 'master_location')
                ->value('value');
        }

        $listLocation = array_filter(array_map('trim', explode(',', (string) $listLocation)));

        return !empty($listLocation) ? array_values($listLocation) : [];
    }
}
