<?php

namespace Bangsamu\Master\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class DataExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    protected $table;

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function collection()
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

        $data = DB::table($this->table)
                    ->select($headers)
                    ->whereNull('deleted_at')
                    ->get();

        return $data;
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
}
