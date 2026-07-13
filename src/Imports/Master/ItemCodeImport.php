<?php

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Models\Uom;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class ItemCodeImport implements ToCollection, WithMultipleSheets, WithChunkReading
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

        // Eager load existing database records into memory to prevent N+1 queries and deadlocks
        $existingItemCodes = DB::table('master_item_code')
            ->select('id', 'item_code', 'deleted_at')
            ->get()
            ->keyBy('item_code');

        $existingUoms = Uom::withTrashed()
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
            ->select('id', 'item_group_code', 'item_group_attributes', 'deleted_at')
            ->get()
            ->keyBy(function ($group) {
                return strtoupper($group->item_group_code);
            });

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

                $item_code_exist = $existingItemCodes->get($item_code);

                if ($item_code_exist) {
                    if ($item_code_exist->deleted_at !== null) {
                        $text = "Row " . $row_index . ": Item Code '" . $item_code . "' already exists with soft delete status.";
                    } else {
                        $text = "Row " . $row_index . ": Item Code '" . $item_code . "' already exists.";
                    }
                    array_push($this->error, $text);
                    continue;
                }

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
                    else if (empty($category_code)) {
                        $text = "Row " . $row_index . " Category Code : field is required.";
                        array_push($this->error, $text);
                    } else if (empty($item_group_code)) {
                        $text = "Row " . $row_index . " Item Group Code : field is required.";
                        array_push($this->error, $text);
                    } else {
                        // validate that config values required for import are present
                        $company_id = config('MasterCrudConfig.MASTER_COMPANY_ID');
                        if (empty($app_code)) {
                            $text = "Row " . $row_index . " APP CODE : configuration is missing.";
                            array_push($this->error, $text);
                            continue;
                        }
                        if (empty($company_id)) {
                            $text = "Row " . $row_index . " COMPANY ID : configuration is missing.";
                            array_push($this->error, $text);
                            continue;
                        }

                        try {
                            $uom_key = strtoupper($uom_code);
                            $uom = $existingUoms->get($uom_key);
                            if (!empty($uom)) {
                                if ($uom->deleted_at !== null) {
                                    $text = "Row " . $row_index . ": UoM Code '" . $uom_code . "' already exists with soft delete status.";
                                    array_push($this->error, $text);
                                    continue;
                                }
                                $uom_id = $uom->id;
                            } else {
                                $create_uom = Uom::create([
                                    'uom_code' => strtoupper($uom_code),
                                    'uom_name' => $uom_code,
                                    'app_code' => strtoupper($app_code),
                                ]);

                                $uom_id = $create_uom->id;
                                $existingUoms->put($uom_key, $create_uom);
                                                
                                /*sync callback master DB*/
                                $sync_tabel = 'master_uom';
                                $sync_id = $uom_id;
                                $sync_row = $create_uom->toArray();
                                // $sync_row['deleted_at'] = null;
                                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                                //update ke master DB saja
                                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                                }
                            }

                            $pca_id = 0;
                            if (!empty($pca_code)) {
                                $pca_key = strtoupper($pca_code);
                                $pca = $existingPcas->get($pca_key);
                                if (!empty($pca)) {
                                    if ($pca->deleted_at !== null) {
                                        $text = "Row " . $row_index . ": PCA Code '" . $pca_code . "' already exists with soft delete status.";
                                        array_push($this->error, $text);
                                        continue;
                                    }
                                    $pca_id = $pca->id;
                                } else {
                                    $create_pca = Pca::create([
                                        'pca_code' => strtoupper($pca_code),
                                        'pca_name' => $pca_code,
                                        'app_code' => strtoupper($app_code),
                                    ]);

                                    $pca_id = $create_pca->id;
                                    $existingPcas->put($pca_key, $create_pca);

                                    /*sync callback master DB*/
                                    $sync_tabel = 'master_pca';
                                    $sync_id = $pca_id;
                                    $sync_row = $create_pca->toArray();
                                    // $sync_row['deleted_at'] = null;
                                    $sync_list_callback = config('AppConfig.CALLBACK_URL');
                                    //update ke master DB saja
                                    if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                                        $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                                    }
                                }
                            }

                            $category_id = 0;
                            if (!empty($category_code)) {
                                $cat_key = strtoupper($category_code);
                                $category = $existingCategories->get($cat_key);
                                if (!empty($category)) {
                                    if ($category->deleted_at !== null) {
                                        $text = "Row " . $row_index . ": Category Code '" . $category_code . "' already exists with soft delete status.";
                                        array_push($this->error, $text);
                                        continue;
                                    }
                                    $category_id = $category->id;
                                } else {
                                    $create_category = Category::create([
                                        'category_code' => strtoupper($category_code),
                                        'category_name' => $category_code,
                                        'app_code' => strtoupper($app_code),
                                    ]);

                                    $category_id = $create_category->id;
                                    $existingCategories->put($cat_key, $create_category);

                                    /*sync callback master DB*/
                                    $sync_tabel = 'master_category';
                                    $sync_id = $category_id;
                                    $sync_row = $create_category->toArray();
                                    // $sync_row['deleted_at'] = null;
                                    $sync_list_callback = config('AppConfig.CALLBACK_URL');
                                    //update ke master DB saja
                                    if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                                        $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                                    }
                                }
                            }

                            $item_group_key = strtoupper($item_group_code);
                            $item_group = $existingItemGroups->get($item_group_key);
                            $item_group_attributes = [];
                            foreach ($headers as $key => $header_val) {
                                if ($key > 5) {
                                    $header_val = strtolower(str_replace(' ', '_', $header_val));
                                    $item_group_attributes[$header_val] = null;
                                }
                            }

                            $item_group_attributes_json = json_encode($item_group_attributes);
                            $need_sync_group = false;
                            if (!empty($item_group)) {
                                if ($item_group->deleted_at !== null) {
                                    $text = "Row " . $row_index . ": Item Group Code '" . $item_group_code . "' already exists with soft delete status.";
                                    array_push($this->error, $text);
                                    continue;
                                }
                                $item_group_id = $item_group->id;
                                $item_group_attributes_curr = $item_group->item_group_attributes;
                                if ($item_group_attributes_curr != $item_group_attributes_json) {
                                    $item_group->update([
                                        'item_group_attributes' => $item_group_attributes_json,
                                    ]);
                                    $need_sync_group = true;
                                }
                            } else {
                                $item_group = ItemGroup::create([
                                    'item_group_code' => $item_group_code,
                                    'item_group_name' => $item_group_code,
                                    'item_group_attributes' => $item_group_attributes_json,
                                    'app_code' => $app_code,
                                ]);

                                $item_group_id = $item_group->id;
                                $existingItemGroups->put($item_group_key, $item_group);
                                $need_sync_group = true;
                            }

                            if ($need_sync_group) {
                                /*sync callback master DB*/
                                $sync_tabel = 'master_item_group';
                                $sync_id = $item_group_id;
                                $sync_row = $item_group->toArray();
                                // $sync_row['deleted_at'] = null;
                                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                                //update ke master DB saja
                                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                                }
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

                            // ensure uniqueness across item_code, app_code, and company_id
                            $masterItemCode = ItemCode::updateOrCreate(
                                [
                                    'item_code' => $item_code,
                                    'app_code' => $app_code,
                                    'company_id' => $company_id,
                                ],
                                $data
                            );

                            $need_sync_code = false;
                            if (!$item_code_exist) {
                                $existingItemCodes->put($item_code, (object)[
                                    'id' => $masterItemCode->id,
                                    'item_code' => $item_code,
                                    'deleted_at' => null
                                ]);
                                $need_sync_code = true;
                            } else if ($masterItemCode->wasChanged()) {
                                $need_sync_code = true;
                            }
                                                        
                            if ($need_sync_code) {
                                /*sync callback master DB*/
                                $sync_tabel = 'master_item_code';
                                $sync_id = $masterItemCode->id;
                                $sync_row = $masterItemCode->toArray();

                                // $sync_row['deleted_at'] = null;
                                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                                //update ke master DB saja
                                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                                }
                            }

                            $text = "Row " . $row_index . " : " . $item_code . " has been imported successfully.";
                            array_push($this->success, $text);
                        } catch (\Throwable $th) {
                            $errorMessage = $th->getMessage();
                            if (str_contains($errorMessage, '1062') || str_contains($errorMessage, 'Duplicate entry')) {
                                $text = "Row " . $row_index . ": Item Code '" . $item_code . "' already exists.";
                            } else {
                                $text = "Row " . $row_index . ": Item Code created failed!" . $errorMessage;
                            }
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

    public function chunkSize(): int
    {
        return 1000;
    }
}
