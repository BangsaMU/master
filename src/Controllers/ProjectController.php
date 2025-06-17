<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;

// use App\Imports\Master\ProjectImport;
use Bangsamu\Master\Imports\Master\ProjectImport;
use Bangsamu\Master\Models\ProjectDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\Company;

class ProjectController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - Project'; //nama label untuk FE
    protected $sheet_slug = 'project'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mp.id AS No',
        // '"action" AS action',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'null AS action',
    );
    protected $view_tabel = array(
        'mp.id AS id',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'mp.project_start_date AS project_start_date',
        'mp.project_complete_date AS project_complete_date',
        'mp.project_remarks AS project_remarks',
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

        $view_form['parent_id'] = [
            'field' => 'parent_id',
            'name' => 'parent_id',
            'label' => 'Parent',
            'type' => 'select2',
            'select2_minimum' => 0, //[1-5]
            'select2_tags' => false, //[true.false]
            'select2_search' => [["name"]], //[field]
            'select2_url' => url('api/getmaster_mcubyparams?set[field][]=name&set[text]=name&ap_token=' . $api_token . ''),
            // 'select2_url' => url('api/getmaster_projectbyparams?set[id]=id&set[text]=name&ap_token=' . api_token($id) . ''),
            'multi' => false,
            'col' => 'col-12 col-md-3 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
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
        $data['page']['list'] = route('master.project.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_project') == true) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('project,company,project_detail')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_project'))) {
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

        if ((checkPermission('is_admin') || checkPermission('read_project'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_project']);
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.project.import');
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
            $order = 'mp.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('master_project as mp')->whereNull('mp.deleted_at')->count();

        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_project as mp')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('mp.deleted_at')
                ->groupby('mp.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mp.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_project as mp')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('mp.deleted_at')
                ->groupby('mp.id')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('update_project'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }
                if ((checkPermission('is_admin') || checkPermission('delete_project'))) {
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
        $param = null;

        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_code' => 'required|string|max:10|unique:master_' . $this->sheet_slug . ',project_code' . ($request->id ? ',' . $request->id : ''),
            'project_name' => 'required|string|max:100',
        ]);

        if ($request->id) {
            // Update existing Project
            $project = Project::findOrFail($request->id);
            $update = $project->update([
                'project_code' => $request->project_code,
                'project_name' => $request->project_name,
                'internal_external' => $request->internal_external,
                'project_remarks' => $request->project_remarks,
                'project_start_date' => $request->project_start_date,
                'project_complete_date' => $request->project_complete_date,
            ]);

            if ($update && $project->wasChanged()) {
                /*sync callback*/
                $id =  $project->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $project->toArray();
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
            // Create new Project
            $project = Project::create([
                'project_code' => $request->project_code,
                'project_name' => $request->project_name,
                'internal_external' => $request->internal_external,
                'project_remarks' => $request->project_remarks,
                'project_start_date' => $request->project_start_date,
                'project_complete_date' => $request->project_complete_date,
            ]);

            // auto add project detail
            $company = Company::where('company_code', 'ME')->first();
            $modelClass = LibraryClayController::resolveModelFromSheetSlug('project_detail');
            $modelClass::create([
                'project_id' => $project->id,
                'project_code_client' => $request->project_code,
                'project_name_client' => $request->project_name,
                'company_id' => $company->id,
            ]); // ini akan trigger Loggable

            // DB::table('master_project_detail')->insert([
            //     'project_id' => $project->id,
            //     'project_code_client' => $request->project_code,
            //     'project_name_client' => $request->project_name,
            //     'company_id' => $company->id,
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


        $view_form_list = null;
        $view_form_listDetail = ProjectDetail::select('project_id', 'project_code_client', 'project_name_client', 'company_code', 'company_name', 'master_project_detail.id as view')->where('project_id', $id)
            ->join('master_project as mp', 'mp.id', '=', 'master_project_detail.project_id')
            ->join('master_company as mc', 'mc.id', '=', 'master_project_detail.company_id')
            ->get()->toArray();
        // dd($view_form_listDetail);
        foreach ($view_form_listDetail as $keyPD => $valPD) {
            foreach ($valPD as $keyD => $valD) {

                // dd($view_form_listDetail, $keyPD, $valPD,$keyD,$valD);
                if ($keyD == 'view') {
                    $view_form_list[$keyPD][] = [
                        'field' => $keyD,
                        'name' => $keyD,
                        'label' => Str::headline($keyD),
                        'type' => 'button',
                        'col' => 'col-12 col-md mb-1',
                        'url' => route($this->readonly ? 'master.project-detail.show' : 'master.project-detail.edit', $valD),
                    ];
                } elseif (strpos('A|project_id', $keyD) === false) {
                    $view_form_list[$keyPD][] = [
                        'field' => $keyD,
                        'name' => $keyD,
                        'label' => Str::headline($keyD),
                        'type' => 'label',
                        'col' => 'col-12 col-md mb-1',
                    ];
                }
            }
        }

        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');

        // return view('master::layouts.dashboard.request', $page_var);
        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param', 'view_form_list', 'view_form_listDetail'));
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

            $import = new ProjectImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.project.index')->with('error_message', $error)->with('success_message', $success);
        }
    }
}
