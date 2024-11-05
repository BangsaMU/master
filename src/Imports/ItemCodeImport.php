<?php

namespace App\Imports;

use App\Models\ItemCode;
use App\Models\ItemGroup;
use App\Models\Category;
use App\Models\ItemCodePicture;
use App\Models\UoM;
use App\Models\Pca;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use \Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemCodeImport implements ToCollection, WithCalculatedFormulas, WithMultipleSheets, WithHeadingRow
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
        // $i = 1;
        // foreach($rows as $key => $row){
        //     if($key >= 1 && ($row != null)){
                
        //         $item_code = $row[0];
        //         $item_name = $row[1];
        //         $remarks = $row[10];
        //         $folder_url = $row[11];

        //         if($row[2]){
        //             $check_uom = UoM::where('uom_code',$row[2])->first();
        //             if (!$check_uom){
        //                 $text = "Row ".$i." : UOM ".$row[2]." not found. Please check the data.";
        //                 array_push($this->error,$text);
        //             }else{
        //                 $uom = $check_uom->id;
        //             }
        //         }

        //         if($row[4]){
        //             $check_pca = Pca::where('pca_code',$row[4])->first();
        //             if (!$check_pca){
        //                 $text = "Row ".$i." : PCA ".$row[4]." not found. Please check the data.";
        //                 array_push($this->error,$text);
        //             }else{
        //                 $pca = $check_pca->id; 
        //             }
        //         }

        //         if($row[6]){
        //             $check_category = Category::where('category_name',$row[6])->first();
        //             if (!$check_category){
        //                 $text = "Row ".$i." : Category ".$row[6]." not found. Please check the data.";
        //                 array_push($this->error,$text);
        //             }else{
        //                 $category = $check_category->id; 
        //             }
        //         }

        //         if($row[8]){
        //             $check_item_group = ItemGroup::where('item_group_code',$row[8])->first();
        //             if (!$check_item_group){
        //                 $text = "Row ".$i." : Item Group ".$row[8]." not found. Please check the data.";
        //                 array_push($this->error,$text);
        //             }else{
        //                 $item_group = $check_item_group->id; 
        //             }
        //         }

        //         if($item_code){
        //             $check_item_code = ItemCode::where('item_code', $item_code)->first();

        //             // pengecekan item code ada == update
        //             if($check_item_code){

        //                 $check_item_code->item_name = $item_name;
        //                 $uom ?? $check_item_code->uom_id = @$uom;
        //                 $pca ?? $check_item_code->pca_id = @$pca;
        //                 $category ?? $check_item_code->category_id = @$category;
        //                 $item_group ?? $check_item_code->group_id = @$item_group;
        //                 $check_item_code->remarks = $remarks;
        //                 $check_item_code->save();

        //                 if ($check_item_code->folder_url) {
        //                     $itemcodepicture = ItemCodePicture::firstOrNew(['item_code_id' => $check_item_code->id]);
        //                     $itemcodepicture->folder_url = $folder_url;
        //                     $itemcodepicture->save();
        //                 }

        //                 $text = "Row ".$i." : Item Code ".$row[0]." has been updated successfully.";
        //                 array_push($this->success,$text);
        //             // pengecekan item code ada != create
        //             }else{
        //                 $itemCode = ItemCode::create([
        //                     'item_code' => $item_code,
        //                     'item_name' => $item_name,
        //                     'uom_id' => @$uom,
        //                     'pca_id' => @$pca,
        //                     'category_id' => @$category,
        //                     'group_id' => @$item_group,
        //                     'remarks' => $remarks
        //                 ]);

        //                 if ($folder_url) {
        //                     $itemcodepicture = ItemCodePicture::firstOrNew(['item_code_id' => $itemCode->id]);
        //                     $itemcodepicture->folder_url = $folder_url;
        //                     $itemcodepicture->save();
        //                 }

        //                 $text = "Row ".$i." : Item Code ".$row[0]." has been imported successfully.";
        //                 array_push($this->success,$text);
        //             }
        //         }
        //         $i++;
        //     }
        // }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if($row->filter()->isNotEmpty()) {
                
                if (empty($row['item_code'])) {
                    $text = "Row ".$row_index." Item Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['item_name'])) {
                    $text = "Row ".$row_index." Item Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['uom_code'])) {
                    $text = "Row ".$row_index." UoM Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['uom_name'])) {
                    $text = "Row ".$row_index." UoM Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['pca_code'])) {
                    $text = "Row ".$row_index." PCA Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['pca_name'])) {
                    $text = "Row ".$row_index." PCA Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['category_code'])) {
                    $text = "Row ".$row_index." Category Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['category_name'])) {
                    $text = "Row ".$row_index." Category Name : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['item_group_code'])) {
                    $text = "Row ".$row_index." Item Group Code : field is required.";
                    array_push($this->error,$text);
                } else if (empty($row['item_group_name'])) {
                    $text = "Row ".$row_index." Item Group Name : field is required.";
                    array_push($this->error,$text);
                } else {

                    $item_code = DB::table('master_item_code')
                        ->where('item_code', $row['item_code'])
                        ->first();

                    if(!$item_code){
                        try {
                            $uom = UoM::select('id')->where('uom_code', $row['uom_code'])->first();
                            if(!empty($uom)){
                                $uom_id = $uom->id;
                            }else{
                                $create_uom = UoM::create([
                                    'uom_code' => $row['uom_code'],
                                    'uom_name' => $row['uom_name'] ?? $row['uom_code'],
                                ]);
    
                                $uom_id = $create_uom->id;
                            }
    
                            $pca = Pca::select('id')->where('pca_code', $row['pca_code'])->first();
                            if(!empty($pca)){
                                $pca_id = $pca->id;
                            }else{
                                $create_pca = Pca::create([
                                    'pca_code' => $row['pca_code'],
                                    'pca_name' => $row['pca_name'] ?? $row['pca_code'],
                                ]);
    
                                $pca_id = $create_pca->id;
                            }
    
                            $category = Category::select('id')->where('category_code', $row['category_code'])->first();
                            if(!empty($category)){
                                $category_id = $category->id;
                            }else{
                                $create_category = Category::create([
                                    'category_code' => $row['category_code'],
                                    'category_name' => $row['category_name'] ?? $row['category_code'],
                                ]);
    
                                $category_id = $create_category->id;
                            }
    
                            $item_group = ItemGroup::select('id')->where('item_group_code', $row['item_group_code'])->first();
                            if(!empty($item_group)){
                                $item_group_id = $item_group->id;
                            }else{
                                $create_item_group = ItemGroup::create([
                                    'item_group_code' => $row['item_group_code'],
                                    'item_group_name' => $row['item_group_name'] ?? $row['item_group_code'],
                                ]);
    
                                $item_group_id = $create_item_group->id;
                            }

                            $data = ItemCode::create([
                                'item_code' => $row['item_code'],
                                'item_name' => $row['item_name'],
                                'uom_id' => $uom_id,
                                'pca_id' => $pca_id,
                                'category_id' => $category_id,
                                'group_id' => $item_group_id,
                                'remarks' => $row['remarks'],
                                'created_at' => now(),
                            ]);

                            if (@$row['url']) {
                                $itemcodepicture = ItemCodePicture::firstOrNew(['item_code_id' => $data->id]);
                                $itemcodepicture->folder_url = $row['url'];
                                $itemcodepicture->save();
                            }
    
                            $text = "Row ".$row_index." : ".$row['item_code']." has been imported successfully.";
                            array_push($this->success,$text);
                        } catch (\Throwable $th) {
                            $text = "Row ".$row_index.": Item Code created failed!";
                            array_push($this->error,$text);
                        }
                    }else{
                        $text = "Row ".$row_index.": Item Code already exists!";
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

    public function checkDate($date){
        if(is_string($date)){
            $return = Carbon::createFromFormat('d/m/Y',$date)->format('Y-m-d H:i');
        }else if(is_int($date)){
            $return = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date);
        }

        return $return;
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
