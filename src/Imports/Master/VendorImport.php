<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\MasterVendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VendorImport implements ToCollection, WithHeadingRow
{
    private $error = [];
    private $success = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                $pca = DB::table('master_vendor')
                                ->where('vendor_code', $row['vendor_code'])
                                ->where('vendor_description', $row['vendor_description'])
                                ->first();

                if(!$pca){
                    if (empty($row['vendor_code'])) {
                        $text = "Row ".$row_index." Vendor Code : field is required.";
                        array_push($this->error,$text);
                    } else if (empty($row['vendor_description'])) {
                        $text = "Row ".$row_index." Vendor Description : field is required.";
                        array_push($this->error,$text);
                    } else {
                        $data = MasterVendor::create([
                            'vendor_code' => strtoupper($row['vendor_code']),
                            'vendor_description' => $row['vendor_description'],
                        ]);

                        $text = "Row ".$row_index." : ".$row['vendor_code']." has been imported successfully.";
                        array_push($this->success,$text);
                    }
                }else{
                    $text = "Row ".$row_index.": Vendor already exists!";
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
