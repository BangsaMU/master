<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use DB;

class EmployeeImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                if (empty($row['employee_name'])) {
                    $text = "Row ".$row_index." Employee Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['employee_job_title'])) {
                    $text = "Row ".$row_index." Employee Job Title : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['employee_email'])) {
                    $text = "Row ".$row_index." Employee Email : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['employee_phone'])) {
                    $text = "Row ".$row_index." Employee Phone : field is required.";
                    array_push($this->error,$text);
                } else {
                    $employee = DB::table('master_employee')
                                    ->where('employee_email', $row['employee_email'])
                                    ->first();

                    if(!$employee){
                        $data = new Employee();
                        $data->employee_name = $row['employee_name'];
                        $data->employee_job_title = $row['employee_job_title'];
                        $data->employee_email = $row['employee_email'];
                        $data->employee_phone = $row['employee_phone'];
                        $data->save();

                        $text = "Row ".$row_index." : ".$row['employee_name']." has been imported successfully.";
                        array_push($this->success,$text);

                    }else{
                        $text = "Row ".$row_index.": Employee already exists!";
                        array_push($this->error,$text);
                    }
                }
            }
        }
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
