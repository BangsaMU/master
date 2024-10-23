<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Exports\DataExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function export($table)
    {
        $table_name = ucwords(str_replace('_', ' ', $table));

        switch ($table) {
            case 'master_project':
                $table_name = 'Project';
                break;
        }


        $file_name = $table_name . '.xlsx';

        $export = new DataExport;
        $export->setTable($table);

        return Excel::download($export, $file_name);
    }
}
