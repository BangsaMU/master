<?php

namespace Bangsamu\Master\Controllers;

use Illuminate\Http\Request;
use App\Models\TelitiGallery;
use App\Models\Project;
use App\Models\HseIndicatorMethod;
use Bangsamu\Master\Models\Empdloyee;
use App\Http\Controllers\ApiAttachmentsHse;
use App\Http\Controllers\Controller;
use Bangsamu\Master\Models\ProjectDetail;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectDetailController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Project Detail'; //nama label untuk FE
    protected $sheet_slug = 'project-detail'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mpd.id AS No',
        'mp.internal_external AS project_type',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'mc.company_code AS company_code',
        'mc.company_name AS company_name',
        'mpd.project_code_client AS project_code_client',
        '"action" AS action',
    );
    protected $view_tabel = array(
        'mpd.id AS id',
        'mpd.loc_code AS location_code',
        'mpd.loc_name AS location_name',
        '"action" AS action',
    );

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['ajax']['url_prefix'] = $sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.' . $this->sheet_slug . '.create');

        $data['page']['js_list'][] = 'js.master-data';

        $data['page']['id'] = $id;

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
        $data['page']['list'] = route('master.' . $this->sheet_slug . '.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') == true) {
            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == false) {
                $data['datatable']['btn']['sync']['id'] = 'sync';
                $data['datatable']['btn']['sync']['title'] = '';
                $data['datatable']['btn']['sync']['icon'] = 'btn-warning far fa-copy " style="color:#6c757d';
                $data['datatable']['btn']['sync']['act'] = "syncFn('project_detail')";
            }

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true) {
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

            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_project_detail']);
            // $data['datatable']['btn']['export']['url'] = url('master/getmaster_project_detail/export');
        }

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
            $order = 'mpd.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('master_project_detail as mpd')->whereNull('mpd.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_project_detail as mpd')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_project as mp', 'mp.id', '=', 'mpd.project_id')
                ->leftJoin('master_company as mc', 'mc.id', '=', 'mpd.company_id')
                ->whereNull('mpd.deleted_at')
                ->groupby('mpd.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mpd.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_project_detail as mpd')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_project as mp', 'mp.id', '=', 'mpd.project_id')
                ->leftJoin('master_company as mc', 'mc.id', '=', 'mpd.company_id')
                ->whereNull('mpd.deleted_at')
                ->groupby('mpd.id')
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
                'filter' => ($c_filed === 'app_code' ||$c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
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

                switch ($row->project_type) {
                    case 'I':
                        $nestedData['project_type'] = 'Internal';
                        break;
                    case 'E':
                        $nestedData['project_type'] = 'External';
                        break;
                }

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && checkPermission('is_admin')) {
                    $btn .= '<a href="' . route('master.' . $this->sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                    $btn .= '<a href="' . route('master.' . $this->sheet_slug . '.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }


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
        $data['page']['store'] = route('master.project-detail.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = false;
        $param = null;

        return view('master::master.project-detail.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'required',
            // 'project_code_client' => 'required',
            'project_name_client' => 'required',
            'company_id' => 'required',
            // Validasi unik pada kombinasi project_id, project_code_client, dan company_id
            'project_code_client' => [
                'required',
                Rule::unique('master_project_detail')->where(function ($query) use ($request) {
                    return $query->where('project_id', $request->project_id)
                        ->where('company_id', $request->company_id);
                }),
            ],
        ]);

        if ($request->id) {
            // Update existing category
            DB::table('master_project_detail')
                ->where('id', $request->id)
                ->update([
                    'project_id' => $request->project_id,
                    'project_code_client' => $request->project_code_client,
                    'project_name_client' => $request->project_name_client,
                    'company_id' => $request->company_id,
                ]);

            $message = $this->sheet_name . ' updated successfully';
        } else {
            // Create new category
            DB::table('master_project_detail')->insert([
                'project_id' => $request->project_id,
                'project_code_client' => $request->project_code_client,
                'project_name_client' => $request->project_name_client,
                'company_id' => $request->company_id,
            ]);

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
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.' . $this->sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_project_detail as tbl1')
            ->leftJoin('master_project as tbl2', 'tbl2.id', '=', 'tbl1.project_id')
            ->leftJoin('master_company as tbl3', 'tbl3.id', '=', 'tbl1.company_id')
            ->where('tbl1.id', $id)
            ->select(
                'tbl1.*',
                'tbl2.project_code',
                'tbl2.project_name',
                'tbl2.internal_external',
                'tbl2.project_start_date',
                'tbl2.project_complete_date',
                'tbl3.company_code',
                'tbl3.company_name',
            )
            ->first();
        // dd($param);
        return view('master::master.project-detail.form', compact('data', 'param'));
    }

    public function insertNew(Request $request, $query, $id = null)
    {
        $auto_insert = false;
        $data_config = self::config($id);
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $project_id = $request->segment(3);

        if ($auto_insert) {
            $project_code_client = $request->project_code_client;
            $company_id = $request->company_id;
            $user_id = auth()->user()->id;

            if ($project_id && $project_code_client && $company_id) {
                $data_insert[] = [
                    'project_id' => $project_id,
                    'project_code_client' => $project_code_client,
                    'company_id' => $company_id,
                ];
            }

            if (isset($data_insert)) {
                $ProjectDetail = ProjectDetail::insert($data_insert);
            }
        } else {
            $query = '
                select
                mp.id AS project_id
                ,mp.project_code AS project_code
                ,mp.project_name AS project_name
                ,mp.project_remarks AS project_remarks
                ,mp.project_start_date AS project_start_date
                ,mp.project_complete_date AS project_complete_date
                from master_project  as mp
                where mp.id="' . $project_id . '"
                limit 1
            ';
        }
        return DB::select($query);
        // dd($formdata);
    }

    public function requestDetail(Request $request, $project_id, $id = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;
        $data = self::config($id);
        $data['page']['slug'] = 'dttable_master';
        $data['page']['title'] = 'Master ' . $sheet_name;
        $data['page']['sheet_name'] = $sheet_name;
        $data['tab-menu']['title'] = \Str::headline('Master ' . $sheet_name);

        // $data['route']['back'] = route($data['ajax']['url_prefix'] . '', []);

        $user_id = auth()->user()->id;
        $form_row_type = 'multi';/*single / multi kalo single cek detail kosong redirect*/
        $id = $id;
        // $indicator_method_id = 45; dinamic dari tabel hse_indicator_method
        $type = $this->sheet_name;

        $data_config = self::config($id);
        if (!is_numeric($id) && $id !== null && $id !== 'new') {
            return redirect()->route($data_config['ajax']['url_prefix'] . '.index')->with('error_message', 'Error: Data not found');
        }
        // dd($project_id, $project_id !== 'new');
        $formdata = null;
        if ($project_id !== 'new') {
            $view_form[1] = [
                'field' => 'project_code',
                'name' => 'project_code',
                'label' => 'Project Code',
                'type' => 'label',
                'col' => 'col-12 col-md-4 mb-2',
                // 'disabled' => true,
            ];
            $view_form[2] = [
                'field' => 'project_name',
                'name' => 'project_name',
                'label' => 'Name',
                'type' => 'label',
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[3] = [
                'field' => 'internal_external',
                'name' => 'internal_external',
                'label' => 'Project Type',
                'type' => 'select',
                'readonly' => true,
                'option' => ['I' => 'Internal', 'E' => 'External'],
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[4] = [
                'field' => 'project_start_date',
                'name' => 'project_start_date',
                'label' => 'Start Date',
                'type' => 'date',
                'disabled' => true,
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[5] = [
                'field' => 'project_complete_date',
                'name' => 'project_complete_date',
                'label' => 'Compdlete Date',
                'type' => 'date',
                'disabled' => true,
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[6] = [
                'field' => 'project_remarks',
                'name' => 'project_remarks',
                'label' => 'Remarks',
                'type' => 'label',
                'col' => 'col-12 col-md-12 mb-2',
            ];
        }

        if ($id === 'new') {
            $project_id_disabled = true;
        }
        $view_form[7] = [
            'field' => 'project_id',
            'name' => 'project_id',
            'label' => 'Relate to Project',
            'type' => 'select2',
            'disabled' => @$project_id_disabled,
            'col' => 'col ',
        ];

        $view_form[8] = [
            'field' => 'project_code_client',
            'name' => 'project_code_client',
            'label' => 'Project Code Client',
            'type' => 'text',
            'col' => 'col ',
        ];
        $view_form[9] = [
            'field' => 'project_name_client',
            'name' => 'project_name_client',
            'label' => 'Project Name Client',
            'type' => 'text',
            'col' => 'col ',
        ];

        $view_form[10] = [
            'field' => 'company_id',
            'name' => 'company_id',
            'label' => 'Company Id',
            'type' => 'select2',
            // 'disabled' => true,
            'col' => 'col ',
        ];

        $query = '
            select
                ' . implode(',', $view_tabel) . '
            from master_project_detail as mpd
            left join master_project mp on mp.id=mpd.project_id
            where mpd.id = "' . $id . '"
        ';

        if ($id || $id == 0) {

            $formdata = DB::select($query);
            // dd($query,$formdata);
            // redirect jika bukan bulti insert
            if (empty($formdata) && $form_row_type == 'single') {
                return redirect()->route($data_config['ajax']['url_prefix'] . '.index')->with('error_message', 'Data not found with id ' . $id . ' not found in database.');
            }

            $data_config = self::config($id, (array)$formdata);
        }

        /*jika data detail kosong*/
        if (empty($formdata)) {
            $insertNew = self::insertNew($request, $query, $id);
            if (empty($insertNew)) {
                $formdata = [0];
            } else {
                $formdata =  $insertNew;
            }
        }

        $formdata_multi = $formdata;
        $formdata = @$formdata[0];
        $foreing_key['id'] = $id;

        $data = self::config($id, $data);
        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');
        // dd(1,$page_var);
        return view('master::layouts.request', $page_var);
    }

    public function configOld($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['page']['folder'] = 'master';
        $data['ajax']['url_prefix'] = $data['page']['folder'] . '/' . $sheet_slug;
        $data['page']['sheet_slug'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['form_number'] = @$tabel->form_number;
        $data['page']['file']['document'] = '.pdf,.doc,.docx,.xml,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $data['page']['file']['audio'] = 'audio/*';
        $data['page']['file']['video'] = 'video/*';
        $data['page']['file']['image'] = 'image/*';

        // $data = configTabActionHsePlan($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['page']['folder'] . '.mastermodal';

        return $data;
    }

    public function storeJson(Request $request, $json = true)
    {
        // dd($request->all());
        return self::store($request, $json);
    }

    public function storeOld(Request $request, $json = false)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        // dd($request->all());
        $request->validate([
            // 'id' => 'required', //hanya bisa update cretaed dari detail
            'project_code_client' => 'required',
            'company_id' => 'required',
        ]);

        $id = $request->id;
        $project_id = $request->project_id;
        $project_code_client = $request->project_code_client;
        $project_name_client = $request->project_name_client;
        $company_id = $request->company_id;

        $user_id = auth()->user()->id;

        if ($request->file('attachment')) {
            // dd('upload file');

            //API uploader
            $ApiAttachmentsHse = new ApiAttachmentsHse;
            $request['id_group'] = $id;
            $ApiAttachmentsHse->store($request, $id);
            // dd(9,$ApiAttachmentsHse);
            $list_attachment = $ApiAttachmentsHse;
            // dd($ApiAttachmentsHse);
        }
        //cek hse_indicator_method_id
        if (!is_numeric(@$indicator_method_id) && isset($indicator_method_id)) {
            // dd($indicator_method_id);
            $data[$id]['empdloyee_name'] = $indicator_method_id;
            $HseIndicatorMethod = HseIndicatorMethod::create(
                [
                    'type' => $sheet_name,
                    'description' => $indicator_method_id
                ]
            );
            $indicator_method_id = $HseIndicatorMethod->id;
            // HseIndicatorMethod::find($indicator_method_id)->update(['nik' => $indicator_method_id]);
        }

        //cek empdloyee
        if (!is_numeric(@$empdloyee_id) && isset($empdloyee_id)) {
            // dd($empdloyee_id);
            $data[$id]['empdloyee_name'] = $empdloyee_id;
            $Empdloyee = Empdloyee::create(
                ['empdloyee_name' => $empdloyee_id]
            );
            $empdloyee_id = $Empdloyee->id;
            Empdloyee::find($empdloyee_id)->update(['nik' => $empdloyee_id]);
        }
        //cek position
        if (@$position) {
            Empdloyee::find($empdloyee_id)->update(['empdloyee_job_title' => $position]);
        }

        $data_config = self::config($id);

        if (is_numeric($id)) {
            $ProjectDetail = ProjectDetail::find($id);

            try {
                $code = 200;
                $ProjectDetail->update([
                    'project_id' => $project_id,
                    'project_code_client' => $project_code_client,
                    'project_name_client' => $project_name_client,
                    'company_id' => $company_id,
                ]);

                $id = $ProjectDetail->id;
                $success_message = 'Form  <b>' . $sheet_name . '</b> updated successfully';
            } catch (Exception $e) {
                $code = 400;
                $success_message = $e->getprevious()->errorInfo[2];
            }

            if ($json) {
                $json_data = array(
                    "code" => $code,
                    "success_message" => $success_message,
                    "redirect"    => route($data_config['ajax']['url_prefix'] . '', ['project_id' => $project_id, 'id' => $id]),
                    "data"            => $ProjectDetail
                );
                return response()->json($json_data);
            } else {
                return redirect()->route($data_config['ajax']['url_prefix'] . '', ['project_id' => $project_id, 'id' => $id])
                    ->with('success_message', $success_message);
            }
        } else {
            try {
                $code = 200;
                $ProjectDetail = ProjectDetail::create([
                    'project_id' => $project_id,
                    'project_code_client' => $project_code_client,
                    'project_name_client' => $project_name_client,
                    'company_id' => $company_id,
                ]);
                $id = $ProjectDetail->id;
                $success_message = 'Form  <b>' . $sheet_name . '</b> created successfully';
            } catch (Exception $e) {
                $code = 400;
                $id = 'new';
                $success_message = $e->getprevious()->errorInfo[2];
            }

            if ($json) {
                $json_data = array(
                    "code" => $code,
                    "success_message" => $success_message,
                    "redirect"    => route($data_config['ajax']['url_prefix'] . '', ['project_id' => $project_id, 'id' => $id]),
                    "data"            => @$ProjectDetail ?? [],
                );
                return response()->json($json_data);
            } else {
                return redirect()->route($data_config['ajax']['url_prefix'] . '', ['project_id' => $project_id, 'id' => $id])
                    ->with('success_message', $success_message);
            }
        }
    }

    public function attachment(Request $request, $id = null, $filetype = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        if (!is_numeric($id)) {
            return redirect()->route('formrequests.request')->with('status', 'Please created/save Form request!');
        }

        $filetype = '*';
        $formdata = null;
        $sheet_name = $this->sheet_name;

        if ($id) {
            $formdata = DB::table('hse_plan as thp')
                ->select(
                    'thp.id AS No',
                    'thp.id AS id',
                    'thp.date AS date',
                    'thp.form_number AS incident_number',
                    'thp.description AS description',
                    'thp.id AS action',
                )
                ->where('thp.id', $id)
                ->where('thp.deleted_at', null)
                ->first();

            if (!empty($formdata->distribusi)) {
                $distribusi = explode(',', $formdata->distribusi);
            }
            if (empty($formdata)) {
                return redirect()->route('formrequests.request')->with('error_message', 'Data attachment with id ' . $id . ' not found in database.');
            }
        }
        $data['page']['title'] = 'List of Attachments';
        $data['tab-menu']['title'] = 'List of Attachments';

        if ($request->ajax()) {
            if ($filetype == 'document') {
                $arr_data = array_map('trim', explode(',', $tabel->document_file_name));
            } else if ($filetype == 'image') {
                $arr_data = array_map('trim', explode(',', $tabel->image_file_name));
            } else if ($filetype == 'video') {
                $arr_data = array_map('trim', explode(',', $tabel->video_file_name));
            } else if ($filetype == 'audio') {
                $arr_data = array_map('trim', explode(',', $tabel->voice_note_file_name));
            }
        }

        $data = self::config($id, (array)$formdata);
        $data['route']['details'] = route('hse-dar.' . $sheet_slug . '', ['id' => $id]);
        $data['route']['attachments'] = route('hse-dar.' . $sheet_slug . '.attachment', ['id' => $id]);

        $data['page']['title'] = 'Detail Form Request';
        $data['tab-menu']['title'] = 'Attachment List';
        $page_var = compact('data', 'filetype', 'id', 'formdata', 'sheet_name');
        return view('master::hse-dar.attachment', $page_var); //default view attachment ke layouts.attachment
    }
}
