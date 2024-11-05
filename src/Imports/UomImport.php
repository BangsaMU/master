<?php

namespace App\Imports;

use App\Models\UoM;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UomImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                if (empty($row['uom_code'])) {
                    $text = "Row ".$row_index." UOM Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['uom_name'])) {
                    $text = "Row ".$row_index." UOM Name : field is required.";
                    array_push($this->error,$text);
                } else {
                    $uom = DB::table('master_uom')
                                    ->where('uom_code', $row['uom_code'])
                                    ->where('uom_name', $row['uom_name'])
                                    ->first();
                    if(!$uom){
                        $data = new UoM();
                        $data->uom_code = $row['uom_code'];
                        $data->uom_name = $row['uom_name'];
                        $data->save();

                        $text = "Row ".$row_index." : ".$row['uom_code']." has been imported successfully.";
                        array_push($this->success,$text);
                    }else{
                        $text = "Row ".$row_index.": UOM already exists!";
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
