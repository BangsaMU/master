<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Exports\Master\ItemCodeExport;
use Bangsamu\Master\Exports\Master\ItemCodeTemplateExport;
use App\Http\Controllers\Controller;

use Bangsamu\Master\Imports\Master\ItemCodeImport;
use Bangsamu\Master\Models\ItemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;


class ItemCodeController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - Item Code'; //nama label untuk FE
    protected $sheet_slug = 'item_code'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mic.id AS No',
        'mic.item_code AS item_code',
        'mic.item_name AS description',
        'mu.uom_name AS uom',
        'mp.pca_name AS pca',
        'mc.category_name AS category',
        'mig.item_group_name AS item_group',
        'mic.attributes AS attributes',
        'mic.app_code AS app_code',
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size_1')) AS size_1",
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size_2')) AS size_2",
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.unit_weight')) AS unit_weight",
        '"action" AS action',
    );
    protected $view_tabel = array(
        'mic.id AS id',
        'mic.item_code AS item_code',
        'mic.item_name AS description',
        'mu.uom_name AS uom',
        'mp.pca_name AS pca',
        'mc.category_name AS category',
        'mig.item_group_name AS item_group',
        '"action" AS action',
    );

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        // parent::__construct($request);
    }

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['module']['folder'] = 'module';
        $data['ajax']['url_prefix'] = $data['module']['folder'] . '.' . $sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.item-code.create');

        $data['page']['js_list'][] = 'js.master-data';

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'] . '.mastermodal';

        return $data;
    }

    public function index(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        if ($request->ajax()) {
            return self::dataJson($request);
        }
        $page_var['view_tabel_index'] = $view_tabel_index;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['list'] = route('master.item-code.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = '';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning far fa-copy " style="color:#6c757d';
            $data['datatable']['btn']['sync']['act'] = "syncFn('item_code')";

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true || checkPermission('create_master_item_code')) {
                $data['datatable']['btn']['create']['id'] = 'create';
                $data['datatable']['btn']['create']['title'] = 'Create';
                $data['datatable']['btn']['create']['icon'] = 'btn-primary';
                $data['datatable']['btn']['create']['url'] = route('master.item-code.create');

                $data['datatable']['btn']['import']['id'] = 'importitem';
                $data['datatable']['btn']['import']['title'] = 'Import Item';
                $data['datatable']['btn']['import']['icon'] = 'btn-primary';
                $data['datatable']['btn']['import']['url'] = '#';
                $data['datatable']['btn']['import']['act'] = 'importFn()';
            }
        }

        if (checkPermission('is_admin') || checkPermission('read_master_data')) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.item-code.export');
        }

        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.item-code.import');
        $data['page']['import']['template'] = route('master.item-code.importtemplate');

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    public function dataJson(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        $limit = strpos('A|-1||', '|' . @$request->input('length') . '|') > 0 ? 10 : $request->input('length');
        $start = $request->input('start') ?? 0;

        $request_columns = $request->columns;
        $jml_char_nosearch = strlen(print_r($request_columns, true)); //0

        $char_nosearch = 0;
        $search = $request->input('search.value');

        $id = $request->id ?? 0;

        $offest = 0;
        $user_id = Auth::user()->id ?? 0;

        if ($request->input('order.0.column')) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $view_tabel[$request->input('order.0.column')]);
            $order = $colom_filed[0] ?? 'id';
        } else {
            $order = 'mic.id';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('master_item_code as mic')->whereNull('mic.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_item_code as mic')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
                ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
                ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
                ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
                ->whereNull('mic.deleted_at')
                ->groupby('mic.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mic.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_item_code as mic')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
                ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
                ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
                ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
                ->whereNull('mic.deleted_at')
                ->groupby('mic.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start);

            $datatb_request = $datatb_request->get();

            $data_tabel = $datatb_request;
        }

        // $mapping_json[11] = 'action';
        foreach ($view_tabel_index as $keyC => $valC) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $valC);
            $c_filed = $colom_filed[1] ?? $colom_filed[0];
            $name = $mapping_json[$keyC] ?? $c_filed;
            $columnsHeader[$keyC] = $c_filed;
            $columns[$keyC] = [
                'data' => $name,
                'name' => ucwords(str_replace('_', ' ', $name)),
                'visible' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
                'filter' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
            ];

            if ($name == "uom") {
                $columns[$keyC]['name'] = 'Unit (UoM)';
            }
        }

        $data = array();
        if (!empty($data_tabel)) {

            $DT_RowIndex = $start + 1;
            foreach ($data_tabel as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {

                    /*remove alias*/
                    $colom_filed = explode(" AS ", $valC);
                    $c_filed = $colom_filed[1] ?? $colom_filed[0];

                    $nestedData[$c_filed] = @$row->$c_filed;
                }
                $nestedData['No'] = $DT_RowIndex;

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && checkPermission('is_admin') && $row->app_code == config('SsoConfig.main.APP_CODE')) {
                    $btn .= '<a href="' . route('master.item-code.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                    $btn .= '<a href="' . route('master.item-code.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                } else {
                    $btn .= '<a href="' . route('master.item-code.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }

                $nestedData['attributes'] = $nestedData['attributes']
                    ? implode('<br>', array_map(function ($key, $value) {
                        // Change underscores to spaces and capitalize the first letter
                        $formattedKey = ucfirst(str_replace('_', ' ', $key));
                        return "$formattedKey: $value";
                    }, array_keys(json_decode($nestedData['attributes'], true)), json_decode($nestedData['attributes'], true)))
                    : '';


                $nestedData['action'] = @$btn;

                $data[] = $nestedData;
                $DT_RowIndex++;
            }
        }

        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
            "columns" => $columns,
        );
        return response()->json($json_data);
    }

    public function create()
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.item-code.store');
        $data['page']['list'] = route('master.item-code.index');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = false;
        $data['page']['attributes'] = '{"size_1":null,"size_2":null,"unit_weight":null}';
        $param = null;

        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'item_code' => 'required|unique:master_' . $this->sheet_slug . ',item_code' . ($request->id ? ',' . $request->id : ''),
            'item_name' => 'required',
            'uom_id' => 'required',
            // 'pca_id' => 'required',
            // 'category_id' => 'required',
            'group_id' => 'required',
        ]);
        $item_group_attributes = DB::table('master_item_group as mig')
            ->where('id', $request->group_id)
            ->select('item_group_attributes')
            ->pluck('item_group_attributes')
            ->first();

        if ($request->id) {
            // Update existing item code
            $item_code_attributes = json_decode($item_group_attributes) ?? (object) [];
            $indexI = 0;
            $attributes = $request->input('attributes');
            if ($attributes) {
                foreach ($item_code_attributes as $key => $detail) {
                    $item_code_attributes->$key = $attributes[$key];
                    // $indexI++;
                }
            }
            $item_code_attributes = json_encode($item_code_attributes);

            $item_code = ItemCode::findOrFail($request->id);
            // dd($request->all());
            $update = $item_code->update([
                'item_code' => $request->item_code,
                'item_name' => $request->item_name,
                'uom_id' => $request->uom_id,
                'pca_id' => $request->pca_id,
                'category_id' => $request->category_id,
                'group_id' => $request->group_id,
                'attributes' => $item_code_attributes,
            ]);

            if ($update && $item_code->wasChanged()) {
                /*sync callback*/
                $id =  $item_code->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $item_code->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && config('database.connections.db_master.database') !== 'meindo_master') {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message = $this->sheet_name . ' updated successfully';
            } else {
                $message = $this->sheet_name . ' no data changed';
            }
        } else {
            // Create new category

            $item_code_attributes = json_decode($item_group_attributes) ?? (object) [];
            $indexI = 0;
            $attributes = $request->input('attributes');
            foreach ($item_code_attributes as $key => $detail) {
                $item_code_attributes->$key = $attributes[$indexI];
                $indexI++;
            }
            $item_code_attributes = json_encode($item_code_attributes);

            DB::table('master_' . $this->sheet_slug)->insert([
                'item_code' => $request->item_code,
                'item_name' => $request->item_name,
                'uom_id' => $request->uom_id,
                'pca_id' => $request->pca_id,
                'category_id' => $request->category_id,
                'group_id' => $request->group_id,
                'attributes' => $item_code_attributes,
                'created_at' => now(),
            ]);

            $message = $this->sheet_name . ' created successfully';
        }

        return redirect()->route('master.item-code.index')->with('success_message', $message);
    }

    public function show($id)
    {
        $this->readonly = true;
        return self::edit($id);
    }

    public function edit($id)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.item-code.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_' . $this->sheet_slug)->where('id', $id)->first();

        $param->uom_name = DB::table('master_uom')->where('id', $param->uom_id)->value('uom_name');
        $param->pca_name = DB::table('master_pca')->where('id', $param->pca_id)->value('pca_name');
        $param->category_name = DB::table('master_category')->where('id', $param->category_id)->value('category_name');
        $param->item_group_name = DB::table('master_item_group')->where('id', $param->group_id)->value('item_group_name');
        // dd($param);
        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function destroy($id)
    {
        DB::table('master_' . $this->sheet_slug)->where('id', $id)->delete();

        return redirect()->route('master.item-code.index')->with('success', $this->sheet_name . ' deleted successfully');
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file|max:2048|mimes:xls,xlsx,txt'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $import = new ItemCodeImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.item-code.index')->with('error_message', $error)->with('success_message', $success);
        }
    }

    public function importtemplate()
    {
        return Excel::download(new ItemCodeTemplateExport, 'ItemCodeTemplateExport.xlsx');
    }

    public function export()
    {
        $file_name = 'Master Item Code.xlsx';

        $export = new ItemCodeExport;

        return Excel::download($export, $file_name);
    }

    public function getJsonData(Request $request)
    {
        $sheet_slug = $this->sheet_slug;
        $view_tabel_index = $this->view_tabel_index;

        $limit = strpos('A|-1||', '|' . @$request->input('length') . '|') > 0 ? 10 : $request->input('length');
        $start = $request->input('start') ?? 0;
        $search = $request->input('search.value');

        if ($request->input('order.0.column')) {
            /*remove alias*/
            $column_field = explode(" AS ", $view_tabel_index[$request->input('order.0.column')]);
            $order = $column_field[0] ?? 'id';
        } else {
            $order = 'mic.id';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Base query for data
        $query = DB::table('master_item_code as mic')
            ->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
            ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
            ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
            ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
            ->select(
                DB::raw(implode(',', $view_tabel_index))
            );

        $totalRecords = DB::table('master_item_code as mic')->count();
        $totalFiltered = $totalRecords;

        if ($search) {
            $search = '%' . $search . '%';

            foreach ($view_tabel_index as $valC) {
                $column_field = explode(' AS ', $valC)[0];

                if ($column_field != '"action"') {
                    // if (strpos($column_field, 'JSON_EXTRACT') !== false) {
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.size_1')) LIKE ?", [$search]);
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.size_2')) LIKE ?", [$search]);
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.unit_weight')) LIKE ?", [$search]);
                    // } else {
                    $query->orWhere($column_field, 'LIKE', $search);
                    // }
                }
            }

            $totalFiltered = $query->count();
        }

        $data_get = $query
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        foreach ($view_tabel_index as $keyC => $valC) {
            /*remove alias*/
            $column_field = explode(" AS ", $valC);
            $c_filed = $column_field[1] ?? $column_field[0];
            $name = $mapping_json[$keyC] ?? $c_filed;
            $columnsHeader[$keyC] = $c_filed;
            $columns[$keyC] = [
                'data' => $name,
                'name' => ucwords(str_replace('_', ' ', $name)),
                'visible' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
                'filter' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
            ];
        }

        $data = array();
        if (!empty($data_get)) {

            $DT_RowIndex = $start + 1;
            foreach ($data_get as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {
                    /*remove alias*/
                    $column_field = explode(" AS ", $valC);
                    $c_filed = $column_field[1] ?? $column_field[0];
                    $nestedData[$c_filed] = @$row->$c_filed;
                }
                $nestedData['item_name'] = 'WELD NECK FLANGE  WN RJ #1500 SCH/WT XXS A 105 ASME B16.5';

                $nestedData['No'] = $DT_RowIndex;

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true) {
                    if (checkPermission('is_admin') || checkPermission('update_item_code')) {
                        $btn .= '<a href="' . route('master.item-code.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                    }
                    if (checkPermission('is_admin') || checkPermission('delete_itemgroup')) {
                        $btn .= '<a href="' . route('master.item-code.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                    }
                } else {
                    $btn .= '<a href="' . route('master.item-code.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }

                $nestedData['attributes'] = $nestedData['attributes']
                    ? implode('<br>', array_map(function ($key, $value) {
                        return "$key: $value";
                    }, array_keys(json_decode($nestedData['attributes'], true)), json_decode($nestedData['attributes'], true)))
                    : '';


                $nestedData['action'] = @$btn;

                $data[] = $nestedData;
                $DT_RowIndex++;
            }
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
            "columns" => $columns,
        ]);
    }
}
