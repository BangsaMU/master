<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Models\MasterLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LocationImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    private $error = [];
    private $success = [];

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                    if (empty($row['location_code'])) {
                        $text = "Row ".$row_index." Location Code : field is required.";
                        array_push($this->error,$text);
                    } else if (empty($row['location_name'])) {
                        $text = "Row ".$row_index." Location Name : field is required.";
                        array_push($this->error,$text);
                    } else {
                        $location = DB::table('master_location')
                                        ->where('loc_code', $row['location_code'])
                                        ->where('loc_name', $row['location_name'])
                                        ->first();

                        if(!$location){
                            $data = new Location();
                            $data->loc_code = strtoupper($row['location_code']);
                            $data->loc_name = $row['location_name'];
                            $data->save();

                            $text = "Row ".$row_index." : ".$row['location_code']." has been imported successfully.";
                            array_push($this->success,$text);

                        }else{
                            $text = "Row ".$row_index.": Location already exists!";
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
