<?php

namespace Bangsamu\Master\Imports;

use Bangsamu\Master\Models\ItemGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemGroupImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                if (empty($row['item_group_code'])) {
                    $text = "Row ".$row_index." Item Group Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['item_group_name'])) {
                    $text = "Row ".$row_index." Item Group Name : field is required.";
                    array_push($this->error,$text);
                } else {
                    $item_group = DB::table('master_item_group')
                                    ->where('item_group_code', $row['item_group_code'])
                                    ->where('item_group_name', $row['item_group_name'])
                                    ->first();


                    if(!$item_group){
                        $data = new ItemGroup();
                        $data->item_group_code = strtoupper($row['item_group_code']);
                        $data->item_group_name = $row['item_group_name'];
                        $data->save();

                        $text = "Row ".$row_index." : ".$row['item_group_code']." has been imported successfully.";
                        array_push($this->success,$text);

                    }else{
                        $text = "Row ".$row_index.": Item Group already exists!";
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
