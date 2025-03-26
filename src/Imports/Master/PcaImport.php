<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Models\MasterPca;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PcaImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                $pca = DB::table('master_pca')
                                ->where('pca_code', $row['pca_code'])
                                ->where('pca_name', $row['pca_name'])
                                ->first();

                if(!$pca){
                    if (empty($row['pca_code'])) {
                        $text = "Row ".$row_index." PCA Code : field is required.";
                        array_push($this->error,$text);
                    } else if (empty($row['pca_name'])) {
                        $text = "Row ".$row_index." PCA Name : field is required.";
                        array_push($this->error,$text);
                    } else {
                        $data = new Pca();
                        $data->pca_code = $row['pca_code'];
                        $data->pca_name = $row['pca_name'];
                        $data->save();

                        $text = "Row ".$row_index." : ".$row['pca_code']." has been imported successfully.";
                        array_push($this->success,$text);
                    }
                }else{
                    $text = "Row ".$row_index.": PCA already exists!";
                    array_push($this->error,$text);
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
