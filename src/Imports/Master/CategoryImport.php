<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                if (empty($row['category_code'])) {
                    $text = "Row ".$row_index." Category Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['category_name'])) {
                    $text = "Row ".$row_index." Category Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['remark'])) {
                    $text = "Row ".$row_index." Remark : field is required.";
                    array_push($this->error,$text);
                } else {
                    $category = DB::table('master_category')
                                    ->where('category_code', $row['category_code'])
                                    ->where('category_name', $row['category_name'])
                                    ->first();

                    if(!$category){
                        $data = new Category();
                        $data->category_code = $row['category_code'];
                        $data->category_name = $row['category_name'];
                        $data->remark = $row['remark'];
                        $data->save();

                        $text = "Row ".$row_index." : ".$row['category_code']." has been imported successfully.";
                        array_push($this->success,$text);

                    }else{
                        $text = "Row ".$row_index.": Category already exists!";
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
