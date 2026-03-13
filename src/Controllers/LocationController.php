<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;

use Bangsamu\Master\Imports\Master\LocationImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\Location;
use Illuminate\Support\Str;
use Bangsamu\Master\Models\Setting;

class LocationController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - location'; //nama label untuk FE
    protected $sheet_slug = 'location'; //nama routing (slug)
    protected $view_tabel_index = array(
        'ml.id AS No',
        // '"action" AS action',
        'ml.loc_code AS loc_code',
        'ml.loc_name AS loc_name',
        'ml.group_type AS group_type',
        'null AS action',
    );
    protected $view_tabel = array(
        'ml.id AS id',
        'ml.loc_code AS loc_code',
        'ml.loc_name AS loc_name',
        'ml.group_type AS group_type',
        'null AS action',
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
        $data['page']['new']['url'] = route('master.' . $sheet_slug . '.create');

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'] . '.mastermodal';

        $data['page']['js_list'][] = 'js.master-data';

        return $data;
    }
    public function view_form($data)
    {
        $readonly = $this->readonly;
        extract($data);
        // dd($getRequisition->version,$id,$getRequisition->status);
        $global_disable = $readonly == false && checkPermission('is_admin') && $id ? false : true;
        // dd($readonly,$global_disable);
        // dd($global_disable);
        $revisi_disable = @$getRequisition->version > 0 ? true : false;
        // dd($global_disable);
        if (@$default_approval_id) {
            extract($default_approval_id);
        }
        if (@$default_for_info_id) {
            extract($default_for_info_id);
        }

        $api_token = LibraryClayController::api_token(null);

        // $view_form['parent_id'] = [
        //     'field' => 'parent_id',
        //     'name' => 'parent_id',
        //     'label' => 'Parent',
        //     'type' => 'select2',
        //     'select2_minimum' => 0, //[1-5]
        //     'select2_tags' => false, //[true.false]
        //     'select2_search' => [["name"]], //[field]
        //     'select2_url' => url('api/getmaster_mcubyparams?set[field][]=name&set[text]=name&ap_token=' . api_token($id) . ''),
        //     // 'select2_url' => url('api/getmaster_projectbyparams?set[id]=id&set[text]=name&ap_token=' . api_token($id) . ''),
        //     'multi' => false,
        //     'col' => 'col-12 col-md-3 mb-2',
        //     'disabled' => $global_disable || $revisi_disable ? true : false,
        // ];
        $view_form['name'] = [
            'field' => 'name',
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['project_id'] = [
            'field' => 'project_id',
            'name' => 'project_id',
            'label' => 'Project*',
            'type' => 'custom',
            'file' => 'components.select2-custom',
            // 'select2_minimum' => 0, //[1-5]
            // 'select2_tags' => false, //[true.false]
            // 'select2_search' => "project_code", //[field]
            'select2_search' => [["project_code"], ['|' => "project_name"]], //[field]
            // 'select2_url' => url('api/getmaster_projectbyparams?set[text]=project_code&ap_token=' . api_token($id) . ''),
            'select2_url' => url('api/getmaster_projectbyparams?set[field][]=project_code&set[field][]=project_name&set[text]=project_code&set[text][|]=project_code&set[text][]=project_name&ap_token=' . $api_token . ''),
            // 'multi' => false,
            'col' => 'col-12 col-md-6 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];

        $view_form[90] = [
            'field' => 'space',
            'type' => 'space',
            'col' => 'col-12 col-md-12 mb-2',
        ];

        $view_form[] = [
            'field' => 'save',
            'name' => 'save',
            'label' => 'Save',
            'type' => 'submit',
            'col' => 'col-12 col-md-12 mb-2',
            'disabled' => $global_disable ? true : false,
        ];

        return $view_form;
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
        $data['page']['list'] = route('master.location.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_location') == true) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('location')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_location'))) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.' . $sheet_slug . '.create');

            $data['datatable']['btn']['import']['id'] = 'importitem';
            $data['datatable']['btn']['import']['title'] = 'Import Item';
            $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            $data['datatable']['btn']['import']['url'] = '#';
            $data['datatable']['btn']['import']['act'] = 'importFn()';
        }

        if ((checkPermission('is_admin') || checkPermission('read_location'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_location']);
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.location.import');
        $data['page']['import']['template'] = url('/templates/projectImportTemplate.xlsx');

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
            $order = 'ml.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $list_location = Setting::where('name', 'group_type')
            ->where('category', 'master_location')
            ->value('value'); // Ambil langsung satu nilai

        // Konversi string ke array, lalu filter elemen kosong
        $list_location = array_filter(explode(",", $list_location));

        // Jika array kosong setelah difilter, set ke null
        $list_location = !empty($list_location) ? $list_location : null;

        $totalData = DB::table('master_location as ml')
            ->whereNull('ml.deleted_at')
            ->when($list_location, function ($query, $group_type) {
                return $query->whereIn('ml.group_type', $group_type);
            })
            ->count();

        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_location as ml')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('ml.deleted_at')
                ->when($list_location, function ($query, $group_type) {
                    return $query->whereIn('ml.group_type', $group_type);
                })
                ->groupby('ml.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('ml.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_location as ml')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('ml.deleted_at')
                ->when($list_location, function ($query, $group_type) {
                    return $query->whereIn('ml.group_type', $group_type);
                })
                ->groupby('ml.id')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('update_location'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }
                if ((checkPermission('is_admin') || checkPermission('delete_location'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $nestedData['action'] = $btn;

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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['list'] = route('master.' . $sheet_slug . '.index');
        $data['page']['readonly'] = false;
        $data['page']['title'] = $sheet_name;

        // Ambil semua enum dari kolom group_type
        $data['page']['list_group_type'] = LibraryClayController::get_enum_values('master_location', 'group_type');

        // Ambil setting
        $list_location = Setting::where('name', 'group_type')
            ->where('category', 'master_location')
            ->value('value');

        // Konversi string ke array dan filter kosong
        $list_location = array_filter(explode(",", $list_location));

        // Jika kosong, jadikan null
        $list_location = !empty($list_location) ? $list_location : null;

        // Cocokkan hanya yang ada di kedua array
        if ($list_location !== null) {
            $data['page']['list_group_type'] = array_values(array_intersect(
                $data['page']['list_group_type'],
                $list_location
            ));
        }

        $param = null;

        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'loc_code' => 'required|unique:master_' . $this->sheet_slug . ',loc_code' . ($request->id ? ',' . $request->id : ''),
            'loc_name' => 'required',
        ]);

        if ($request->id) {
            // Update existing location
            $location = Location::findOrFail($request->id);
            $update = $location->update([
                'loc_code' => strtoupper($request->loc_code),
                'loc_name' => $request->loc_name,
                'group_type' => $request->group_type,
            ]);

            if ($update && $location->wasChanged()) {
                /*sync callback*/
                $id =  $location->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $location->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message = $this->sheet_name . ' updated successfully';
            } else {
                $message = $this->sheet_name . ' no data changed';
            }
        } else {
            // Create new location
            $modelClass = LibraryClayController::resolveModelFromSheetSlug($this->sheet_slug);

            $modelClass::create([
                'loc_code' => strtoupper($request->loc_code),
                'loc_name' => $request->loc_name,
                'group_type' => $request->group_type,
                'created_at' => now(),
            ]);

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                // Create new location
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_'.$this->sheet_slug);

                $modelClass::create([
                    'loc_code' => strtoupper($request->loc_code),
                    'loc_name' => $request->loc_name,
                    'group_type' => $request->group_type,
                    'created_at' => now(),
                ]);
            }
            
            // DB::table('master_' . $this->sheet_slug)->insert([
            //     'loc_code' => $request->loc_code,
            //     'loc_name' => $request->loc_name,
            //     'group_type' => $request->group_type,
            //     'created_at' => now(),
            // ]);

            $message = $this->sheet_name . ' created successfully';
        }

        return redirect()->route('master.' . $this->sheet_slug . '.index')->with('success_message', $message);
    }

    public function show($id)
    {
        $this->readonly = true;
        return self::edit($id);
    }

    public function edit($id)
    {
        $form_row_type = 'multi'; /*single / multi kalo single cek detail kosong redirect*/
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;

        // Ambil semua enum dari kolom group_type
        $data['page']['list_group_type'] = LibraryClayController::get_enum_values('master_location', 'group_type');

        // Ambil setting
        $list_location = Setting::where('name', 'group_type')
            ->where('category', 'master_location')
            ->value('value');

        // Konversi string ke array dan filter kosong
        $list_location = array_filter(explode(",", $list_location));

        // Jika kosong, jadikan null
        $list_location = !empty($list_location) ? $list_location : null;

        // Cocokkan hanya yang ada di kedua array
        if ($list_location !== null) {
            $data['page']['list_group_type'] = array_values(array_intersect(
                $data['page']['list_group_type'],
                $list_location
            ));
        }


        $param = DB::table('master_' . $this->sheet_slug)->where('id', $id)->first();

        /**
         * formdata
         * data harus type multi array
         */
        $formdata = DB::table('master_' . $this->sheet_slug)->where('id', $id)->get();

        /*redirect jika bukan multi insert*/
        if (empty($formdata)) {
            if ($form_row_type == 'single') {
                return redirect()->route('module.' . $sheet_slug . '.index')->with('error_message', 'Data not found with id ' . $id . ' not found in database.');
            } else {
                abort(403, 'data not found');
            }
        }

        $formdata_multi = $formdata;
        $formdata = $formdata[0];
        /**
         * foreing_key buat input type hiden
         */
        $foreing_key['id'] = $id;

        $view_form = self::view_form(compact('id'));

        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');

        // return view('master::layouts.dashboard.request', $page_var);
        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function destroy($id)
    {
        // DB::table('master_' . $this->sheet_slug)->where('id', $id)->delete();
        $modelClass = 'Bangsamu\\Master\\Models\\Master' . Str::studly($this->sheet_slug);

        if (class_exists($modelClass)) {
            $modelClass::findOrFail($id)->delete(); // akan melakukan soft delete
        }else{
            abort(403,'Gagal hapus:: '.$modelClass . class_exists($modelClass));
        }

        return redirect()->route('master.' . $this->sheet_slug . '.index')->with('success', $this->sheet_slug . ' deleted successfully');
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file|max:2048|mimes:xls,xlsx,txt'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $import = new LocationImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.location.index')->with('error_message', $error)->with('success_message', $success);
        }
    }
}
