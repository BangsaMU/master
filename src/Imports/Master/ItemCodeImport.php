<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\MasterCategory;
use Bangsamu\Master\Models\MasterItemCode;
use Bangsamu\Master\Models\MasterItemGroup;
use Bangsamu\Master\Models\MasterPca;
use Bangsamu\Master\Models\MasterUom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemCodeImport implements ToCollection, WithMultipleSheets
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
        $user = config('SsoConfig.main.APP_CODE');
        $app_code = config('SsoConfig.main.APP_CODE');
        $headers = $rows[0];
        foreach ($rows as $key => $row) {
            $row_index = $key;
            if ($row->filter()->isNotEmpty() && $key > 0) {

                //fix column
                $item_code = $row[0];
                $item_name = $row[1];
                $uom_code = $row[2];
                $pca_code = $row[3];
                $category_code = $row[4];
                $item_group_code = $row[5];

                $item_code_exist = DB::table('master_item_code')
                    ->where('item_code', $item_code)
                    ->first();

                // if(!$item_code_exist){
                if (true) {
                    if (empty($item_code)) {
                        $text = "Row " . $row_index . " Item Code : field is required.";
                        array_push($this->error, $text);
                    } else if (empty($item_name)) {
                        $text = "Row " . $row_index . " Item Name : field is required.";
                        array_push($this->error, $text);
                    } else if (empty($uom_code)) {
                        $text = "Row " . $row_index . " UoM Code : field is required.";
                        array_push($this->error, $text);
                    }
                    // else if (empty($row['pca_code'])) {
                    //     $text = "Row ".$row_index." PCA Code : field is required.";
                    //     array_push($this->error,$text);
                    // }
                    else if (empty($category_code)) {
                        $text = "Row " . $row_index . " Category Code : field is required.";
                        array_push($this->error, $text);
                    } else if (empty($item_group_code)) {
                        $text = "Row " . $row_index . " Item Group Code : field is required.";
                        array_push($this->error, $text);
                    } else {
                        try {
                            $uom = MasterUom::select('id')->where('uom_code', $uom_code)->first();
                            if (!empty($uom)) {
                                $uom_id = $uom->id;
                            } else {
                                $create_uom = MasterUom::create([
                                    'uom_code' => $uom_code,
                                    'uom_name' => $uom_code,
                                    'app_code' => $app_code,
                                ]);

                                $uom_id = $create_uom->id;
                            }

                            $pca = MasterPca::select('id')->where('pca_code', $pca_code)->first();
                            $pca_id = 0;
                            if (!empty($pca_code)) {
                                if (!empty($pca)) {
                                    $pca_id = $pca->id;
                                } else {
                                    $create_pca = MasterPca::create([
                                        'pca_code' => $pca_code,
                                        'pca_name' => $pca_code,
                                        'app_code' => $app_code,
                                    ]);

                                    $pca_id = $create_pca->id;
                                }
                            }

                            $category = MasterCategory::select('id')->where('category_code', $category_code)->first();
                            $category_id = 0;
                            if (!empty($category_code)) {
                                if (!empty($category)) {
                                    $category_id = $category->id;
                                } else {
                                    $create_category = MasterCategory::create([
                                        'category_code' => $category_code,
                                        'category_name' => $category_code,
                                        'app_code' => $app_code,
                                    ]);

                                    $category_id = $create_category->id;
                                }
                            }

                            $item_group = MasterItemGroup::where('item_group_code', $item_group_code)->first();
                            $item_group_attributes = [];
                            foreach ($headers as $key => $header_val) {
                                if ($key > 5) {
                                    $header_val = strtolower(str_replace(' ', '_', $header_val));
                                    $item_group_attributes[$header_val] = null;
                                }
                            }
                            $item_group_attributes_json = json_encode($item_group_attributes);
                            if (!empty($item_group)) {
                                $item_group_id = $item_group->id;
                                $item_group_attributes_curr = $item_group->item_group_attributes;
                                if ($item_group_attributes_curr != $item_group_attributes_json) {
                                    $item_group->update([
                                        'item_group_attributes' => $item_group_attributes_json,
                                    ]);
                                }
                            } else {
                                $create_item_group = MasterItemGroup::create([
                                    'item_group_code' => $item_group_code,
                                    'item_group_name' => $item_group_code,
                                    'item_group_attributes' => $item_group_attributes_json,
                                    'app_code' => $app_code,
                                ]);

                                $item_group_id = $create_item_group->id;
                            }

                            //attribute column
                            $attributes = (object) [];
                            foreach ($headers as $key => $header_val) {
                                if ($key > 5) {
                                    $header_val = strtolower(str_replace(' ', '_', $header_val));
                                    $attributes->$header_val = $row[$key];
                                }
                            }

                            $filteredAttributes = array_filter((array) $attributes, function ($value) {
                                return !is_null($value);
                            });

                            $json = !empty($filteredAttributes) ? json_encode($filteredAttributes) : null;

                            $data = [
                                'item_code' => $item_code,
                                'item_name' => $item_name,
                                'uom_id' => $uom_id,
                                'pca_id' => $pca_id,
                                'category_id' => $category_id,
                                'group_id' => $item_group_id,
                                'remarks' => null,
                                'attributes' => $json,
                            ];

                            $masterItemCode = MasterItemCode::updateOrCreate(
                                [
                                    'item_code' => $item_code,
                                    'app_code' => $app_code,
                                ],
                                $data
                            );

                            $text = "Row " . $row_index . " : " . $item_code . " has been imported successfully.";
                            array_push($this->success, $text);
                        } catch (\Throwable $th) {
                            $text = "Row " . $row_index . ": Item Code created failed!" . $th->getMessage();
                            array_push($this->error, $text);
                        }
                    }
                } else {
                    $text = "Row " . $row_index . ": Item Code already exists!";
                    array_push($this->error, $text);
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
