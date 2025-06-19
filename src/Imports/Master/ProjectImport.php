<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\MasterProject as ModelsMasterProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class ProjectImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                    if (empty($row['project_code'])) {
                        $text = "Row ".$row_index." project Code : field is required.";
                        array_push($this->error,$text);
                    } else if (empty($row['project_name'])) {
                        $text = "Row ".$row_index." project Name : field is required.";
                        array_push($this->error,$text);
                    } else {
                        $project = DB::table('master_project')
                                        ->where('project_code', $row['project_code'])
                                        // ->where('project_name', $row['project_name'])
                                        ->first();

                        if(!$project){
                            $data = new ModelsMasterProject();
                            $data->project_code = strtoupper($row['project_code']);
                            $data->project_name = $row['project_name'];
                            $data->internal_external = $row['project_type'];
                            $data->project_start_date = $this->fixDate($row['project_start_date']);
                            $data->project_complete_date = $this->fixDate($row['project_complete_date']);
                            $data->save();

                            $text = "Row ".$row_index." : ".$row['project_code']." has been imported successfully.";
                            array_push($this->success,$text);
                        }else{
                            $text = "Row ".$row_index.": project already exists!";
                            array_push($this->error,$text);
                        }
                    }

            }
        }
    }


    public function fixDate($date){
        $date = (int) $date;
        return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date))->format('Y-m-d');
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getSuccess(): array
    {
        return $this->success;
    }
}
