<?php

namespace Bangsamu\Master\Imports;

use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Models\ItemCodePicture;
use Bangsamu\Master\Models\UoM;
use Bangsamu\Master\Models\Pca;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use \Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ItemCodeImport implements ToCollection, WithCalculatedFormulas, WithMultipleSheets, WithHeadingRow, WithChunkReading
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

        // Eager load existing database records into memory to prevent N+1 queries and deadlocks
        $existingItemCodes = DB::table('master_item_code')
            ->select('id', 'item_code', 'deleted_at')
            ->get()
            ->keyBy('item_code');

        $existingUoms = UoM::withTrashed()
            ->select('id', 'uom_code', 'deleted_at')
            ->get()
            ->keyBy(function ($uom) {
                return strtoupper($uom->uom_code);
            });

        $existingPcas = Pca::withTrashed()
            ->select('id', 'pca_code', 'deleted_at')
            ->get()
            ->keyBy(function ($pca) {
                return strtoupper($pca->pca_code);
            });

        $existingCategories = Category::withTrashed()
            ->select('id', 'category_code', 'deleted_at')
            ->get()
            ->keyBy(function ($cat) {
                return strtoupper($cat->category_code);
            });

        $existingItemGroups = ItemGroup::withTrashed()
            ->select('id', 'item_group_code', 'deleted_at')
            ->get()
            ->keyBy(function ($group) {
                return strtoupper($group->item_group_code);
            });

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

                    $item_code = $existingItemCodes->get($row['item_code']);

                    if ($item_code && !empty($item_code->deleted_at)) {
                        $text = "Row ".$row_index.": Item Code '".$row['item_code']."' already exists with soft delete status.";
                        array_push($this->error,$text);
                        continue;
                    }

                    if(!$item_code){
                        try {
                            $uom_key = strtoupper($row['uom_code']);
                            $uom = $existingUoms->get($uom_key);
                            if(!empty($uom)){
                                if ($uom->deleted_at !== null) {
                                    $text = "Row ".$row_index.": UoM Code '".$row['uom_code']."' already exists with soft delete status.";
                                    array_push($this->error,$text);
                                    continue;
                                }
                                $uom_id = $uom->id;
                            }else{
                                $create_uom = UoM::create([
                                    'uom_code' => strtoupper($row['uom_code']),
                                    'uom_name' => $row['uom_name'] ?? $row['uom_code'],
                                ]);

                                $uom_id = $create_uom->id;
                                $existingUoms->put($uom_key, $create_uom);
                            }

                            $pca_key = strtoupper($row['pca_code']);
                            $pca = $existingPcas->get($pca_key);
                            if(!empty($pca)){
                                if ($pca->deleted_at !== null) {
                                    $text = "Row ".$row_index.": PCA Code '".$row['pca_code']."' already exists with soft delete status.";
                                    array_push($this->error,$text);
                                    continue;
                                }
                                $pca_id = $pca->id;
                            }else{
                                $create_pca = Pca::create([
                                    'pca_code' => strtoupper($row['pca_code']),
                                    'pca_name' => $row['pca_name'] ?? $row['pca_code'],
                                ]);

                                $pca_id = $create_pca->id;
                                $existingPcas->put($pca_key, $create_pca);
                            }

                            $cat_key = strtoupper($row['category_code']);
                            $category = $existingCategories->get($cat_key);
                            if(!empty($category)){
                                if ($category->deleted_at !== null) {
                                    $text = "Row ".$row_index.": Category Code '".$row['category_code']."' already exists with soft delete status.";
                                    array_push($this->error,$text);
                                    continue;
                                }
                                $category_id = $category->id;
                            }else{
                                $create_category = Category::create([
                                    'category_code' => strtoupper($row['category_code']),
                                    'category_name' => $row['category_name'] ?? $row['category_code'],
                                ]);

                                $category_id = $create_category->id;
                                $existingCategories->put($cat_key, $create_category);
                            }

                            $item_group_key = strtoupper($row['item_group_code']);
                            $item_group = $existingItemGroups->get($item_group_key);
                            if(!empty($item_group)){
                                if ($item_group->deleted_at !== null) {
                                    $text = "Row ".$row_index.": Item Group Code '".$row['item_group_code']."' already exists with soft delete status.";
                                    array_push($this->error,$text);
                                    continue;
                                }
                                $item_group_id = $item_group->id;
                            }else{
                                $create_item_group = ItemGroup::create([
                                    'item_group_code' => strtoupper($row['item_group_code']),
                                    'item_group_name' => $row['item_group_name'] ?? $row['item_group_code'],
                                ]);

                                $item_group_id = $create_item_group->id;
                                $existingItemGroups->put($item_group_key, $create_item_group);
                            }

                            $data = ItemCode::create([
                                'item_code' => strtoupper($row['item_code']),
                                'item_name' => $row['item_name'],
                                'uom_id' => $uom_id,
                                'pca_id' => $pca_id,
                                'category_id' => $category_id,
                                'group_id' => $item_group_id,
                                'remarks' => $row['remarks'],
                                'created_at' => now(),
                            ]);

                            $existingItemCodes->put($row['item_code'], (object)[
                                'id' => $data->id,
                                'item_code' => $row['item_code'],
                                'deleted_at' => null
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

    public function chunkSize(): int
    {
        return 1000;
    }
}
