<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Models\Company;
use App\Models\Gallery;
use App\Models\FileManager;
use App\Models\HseIndicatorMethod;
use Bangsamu\Master\Models\Employee;
use App\Http\Controllers\ApiAttachmentsHse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Company'; //nama label untuk FE
    protected $sheet_slug = 'company'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mc.id AS No',
        'mc.company_code AS company_code',
        'mc.company_name AS company_name',
        '"action" AS action',
    );
    protected $view_tabel = array(
        'mc.id AS id',
        'mc.brand_code AS brand_code',
        'mc.brand_description AS brand_description',
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
        $data['page']['new']['url'] = route('master.' . $sheet_slug . '.create');

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
        $data['page']['list'] = route('master.' . $sheet_slug . '.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin')) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.' . $sheet_slug . '.create');

            // $data['datatable']['btn']['import']['id'] = 'importitem';
            // $data['datatable']['btn']['import']['title'] = 'Import';
            // $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            // $data['datatable']['btn']['import']['url'] = '#';
            // $data['datatable']['btn']['import']['act'] = 'importFn()';

            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = url('getmaster_company/export');
        }

        // $data['page']['import']['layout'] = 'layouts.import.form';
        // $data['page']['import']['post'] = route('company.import');
        // $data['page']['import']['template'] = url('/templates/BrandImportTemplate.xlsx');

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
            $order = 'mc.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('master_company as mc')->whereNull('mc.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_company as mc')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('mc.deleted_at')
                ->groupby('mc.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mc.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_company as mc')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('mc.deleted_at')
                ->groupby('mc.id')
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
                'visible' => ($c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
                'filter' => ($c_filed === 'id' || strpos($c_filed, "_id") > 0 ? false : true),
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

                if (checkPermission('is_admin')) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $param = null;

        // return view('master::master.company.form', compact('data', 'param'));
        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_code' => 'required|unique:master_company,company_code,' . ($request->id ?? 'NULL'),
            'company_name' => 'required',
        ]);

        if ($request->id) {
            // Update existing employee
            $company = Company::findOrFail($request->id);
            $company->update([
                'company_code' => $request->company_code,
                'company_name' => $request->company_name,
                'company_short' => $request->company_short,
                'company_attention' => $request->company_attention,
                'company_address' => $request->company_address,
            ]);

            if ($request->file('company_logo') && $company->company_logo_id) {
                $existingGallery = Gallery::find($company->company_logo_id);
                if ($existingGallery) {
                    $existingPath = str_replace(url('/storage/'), '', $existingGallery->url);
                    Storage::disk('public')->delete($existingPath);
                    $existingGallery->delete();
                }
            }

            $message = $this->sheet_name . ' updated successfully';
        } else {
            // Create new employee
            $company = Company::create([
                'company_code' => $request->company_code,
                'company_name' => $request->company_name,
                'company_short' => $request->company_short,
                'company_attention' => $request->company_attention,
                'company_address' => $request->company_address,
            ]);

            $message = $this->sheet_name . ' created successfully';
        }

        if ($request->file('company_logo')) {
            $file = $request->file('company_logo');
            $user_id = auth()->user()->id;

            $prefix = $file->getClientOriginalExtension();
            $caption = $file->getClientOriginalName();
            $url = null;
            $path = 'app/public/gallery/' . $prefix;
            $filename = null;
            $size = $file->getSize();
            $header_type = $file->getClientMimeType();
            $file_group = null;
            $hash_file = null;
            $file_type = $prefix;

            $gallery = Gallery::create([
                'user_id' => $user_id,
                'caption' => $caption,
                'url' => $url,
                'path' => $path,
                'filename' => $filename,
                'size' => $size,
                'header_type' => $header_type,
                'file_group' => $file_group,
                'hash_file' => $hash_file,
                'file_type' => $file_type,
            ]);

            $filename = $gallery->id . "-" . $file->getClientOriginalName();
            $filePath = $file->storeAs('public/gallery/' . $prefix, $filename, 'local');

            $gallery->filename = $filename;
            $gallery->url = url('storage/gallery/' . $prefix . '/' . $filename);
            $gallery->save();

            $company->company_logo_id = $gallery->id;
            $company->save();
        }

        /*sync callback*/
        // $id =  $company->id;
        // $sync_tabel = 'company';
        // $sync_id = $id;
        // $sync_row = $company->toArray();
        // $sync_row['deleted_at'] = null;
        // $sync_list_callback = config('AppConfig.CALLBACK_URL');
        // $a = callbackSyncMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));

        return redirect()->route('company.index')->with('success_message', $message);
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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_' . $this->sheet_slug . ' as tbl1')
            ->leftJoin('master_gallery as tbl2', 'tbl2.id', '=', 'tbl1.company_logo_id')
            ->where('tbl1.id', $id)
            ->select(
                'tbl1.*',
                'tbl2.url as company_logo_url'
            )
            ->first();

        // dd($param);
        // return view('company.form', compact('data', 'param'));
        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function insertNew(Request $request, $query, $id = null)
    {

        $auto_insert = false;
        $data_config = self::config($id);
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;


        if ($auto_insert) {
            $company_code = $request->company_code;
            $company_name = $request->company_name;
            $internal_external = $request->internal_external;
            $company_start_date = $request->company_start_date;
            $company_complete_date = $request->company_complete_date;
            $company_remarks = $request->company_remarks;
            $user_id = auth()->user()->id;

            $data_insert[] = [
                'company_code' => $company_code,
                'company_name' => $company_name,
                'internal_external' => $internal_external,
                'company_start_date' => $company_start_date,
                'company_complete_date' => $company_complete_date,
                'company_remarks' => $company_remarks,
                'user_id' => $user_id,
            ];

            if (isset($data_insert)) {
                $Company = Company::insert($data_insert);
            }
            // dd($formdata);
        } else {
            $query = '
                select
                mc.id AS company_id
                ,mc.company_code AS company_code
                ,mc.company_name AS company_name
                ,mc.company_remarks AS company_remarks
                ,mc.company_start_date AS company_start_date
                ,mc.company_complete_date AS company_complete_date
                from master_company  as mp
                where mc.id="' . $id . '"
                limit 1
            ';
        }

        return DB::select($query);
    }

    public function requestDetail(Request $request, $id = null)
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

        $user_id = auth()->user()->id;
        $form_row_type = 'multi';/*single / multi kalo single cek detail kosong redirect*/
        $id = $id;
        // $indicator_method_id = 45; dinamic dari tabel hse_indicator_method
        $type = $this->sheet_name;

        $data_config = self::config($id);
        if (!is_numeric($id) && $id !== null && $id !== 'new') {
            return redirect()->route($data_config['ajax']['url_prefix'] . '.index')->with('status', 'Please created/save Form request!');
        }

        $formdata = null;
        $view_form[1] = [
            'field' => 'company_code',
            'name' => 'company_code',
            'label' => 'Company Code',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
            // 'disabled' => true,
        ];
        $view_form[2] = [
            'field' => 'company_name',
            'name' => 'company_name',
            'label' => 'Name',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[3] = [
            'field' => 'company_short',
            'name' => 'company_short',
            'label' => 'Company Short',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[4] = [
            'field' => 'company_logo',
            'name' => 'company_logo',
            'label' => 'Logo',
            'type' => 'image',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[41] = [
            'field' => 'company_logo_id',
            'name' => 'company_logo_id',
            'label' => 'Logo',
            'type' => 'attachment',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[5] = [
            'field' => 'company_attention',
            'name' => 'company_attention',
            'label' => 'Attention',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[6] = [
            'field' => 'company_address',
            'name' => 'company_address',
            'label' => 'Address',
            'type' => 'text',
            'col' => 'col-12 col-md-12 mc-2',
        ];
        // $view_form[7] = [
        //     'field' => 'save',
        //     'name' => 'save',
        //     'label' => 'Save',
        //     'type' => 'submit',
        //     'col' => 'col-12 col-md-2 mc-2',
        // ];


        $query = '
            select
                ' . implode(',', $view_tabel) . '
            from master_company as mc
            left join master_file_manager mfm on mc.company_logo_id=mfm.id
            left join master_gallery mg on mg.id=mfm.wgallery_id
            where mc.id = "' . $id . '"
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
        $foreing_key = null;
        $foreing_key['id'] = $id;

        $data = self::config($id, $data);

        $view_form_list = null;
        $view_form_listDetail = [];
        // $view_form_listDetail = ProjectDetail::select('company_id', 'company_code_client', 'company_name_client', 'company_code', 'company_name', 'master_company_detail.id as view')->where('company_id', $id)
        //     ->join('master_company as mc', 'mc.id', '=', 'master_company_detail.company_id')
        //     ->join('master_company as mc', 'mc.id', '=', 'master_company_detail.company_id')
        //     ->get()->toArray();
        // dd($view_form_listDetail);
        // foreach ($view_form_listDetail as $keyPD => $valPD) {
        //     foreach ($valPD as $keyD => $valD) {

        //         // dd($view_form_listDetail, $keyPD, $valPD,$keyD,$valD);
        //         if ($keyD == 'view') {
        //             $view_form_list[$keyPD][] = [
        //                 'field' => $keyD,
        //                 'name' => $keyD,
        //                 'label' => \Str::headline($keyD),
        //                 'type' => 'button',
        //                 'col' => 'col-12 col-md mc-1',
        //                 'url' => route('master/project-detail', ['company_id' => $id, 'id' => $valD])
        //             ];
        //         } elseif (strpos('A|company_id', $keyD) === false) {
        //             $view_form_list[$keyPD][] = [
        //                 'field' => $keyD,
        //                 'name' => $keyD,
        //                 'label' => \Str::headline($keyD),
        //                 'type' => 'label',
        //                 'col' => 'col-12 col-md mc-1',
        //             ];
        //         }
        //     }
        // }
        // dd($formdata);
        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form', 'view_form_list', 'view_form_listDetail');
        // dd($page_var);
        // return view('hse-dar.' . $sheet_slug . '.request', $page_var);
        return view('master::layouts.request', $page_var);
    }

    // public function config($id = null, $data = null)
    // {
    //     $sheet_name = $this->sheet_name;
    //     $sheet_slug = $this->sheet_slug;

    //     $data['page']['folder'] = 'master';
    //     $data['ajax']['url_prefix'] = $data['page']['folder'] . '/' . $sheet_slug;
    //     $data['page']['sheet_slug'] = $sheet_slug;
    //     $data['page']['sheet_name'] = $sheet_name;
    //     $data['page']['form_number'] = @$tabel->form_number;
    //     $data['page']['file']['document'] = '.pdf,.doc,.docx,.xml,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    //     $data['page']['file']['audio'] = 'audio/*';
    //     $data['page']['file']['video'] = 'video/*';
    //     $data['page']['file']['image'] = 'image/*';

    //     // $data = configTabActionHsePlan($id, $data);

    //     $data['page']['id'] = $id;
    //     $data['modal']['view_path'] = $data['page']['folder'] . '.mastermodal';

    //     return $data;
    // }

    public function storeJson(Request $request, $json = true)
    {
        // dd($request->all());
        return self::store($request, $json);
    }

    public function uploadFile($request, $id)
    {
        $request->validate([
            'app' => 'required',
            'attachment' => 'required',
        ]);

        $app = $request->input('app');
        $id_group = $request->input('id_group');
        $attachment_type = 'action';
        $return_id = [];
        $return_name = [];
        $user_id = auth()->user()->id;

        foreach ($request->file('attachment') as $file) {
            $prefix = $file->getClientOriginalExtension();

            $caption = $file->getClientOriginalName();
            $url = null;
            $path = 'app/public/gallery/' . $prefix;
            $filename = null;
            $size = $file->getSize();
            $header_type = $file->getClientMimeType();
            $file_group = null;
            $hash_file = null;
            $file_type = $prefix;

            $Gallery = Gallery::create([
                'user_id' => $user_id,
                'caption' => $caption,
                'url' => $url,
                'path' => $path,
                'filename' => $filename,
                'size' => $size,
                'header_type' => $header_type,
                'file_group' => $file_group,
                'hash_file' => $hash_file,
                'file_type' => $file_type,
            ]);

            $filename = $Gallery->id . "-" . $file->getClientOriginalName();
            $filePath = $file->storeAs('public/gallery/' . $prefix, $filename, 'local');

            $Gallery->filename = $filename;
            $Gallery->url = config('app.url') . '/storage/gallery/' . $prefix . '/' . $filename;
            $Gallery->save();

            array_push($return_id, $Gallery->id);
            array_push($return_name, $Gallery->filename);
        }


        foreach ($return_id as $wgallery_id) {
            $list_data[] = FileManager::create([
                'tfr_id' => $id,
                'tfrs_id' => $id_group,
                'app' => $app,
                'wgallery_id' => $wgallery_id,
                'user_id' => $user_id,
                'attachment_type' => $attachment_type,
            ]);
        }

        $response[] = [
            'success' => true,
            'data' => @$list_data,
            'message' => count($return_id) . ' Attachment berhasil di submit!',
        ];
        return $response;
    }

    public function storeOld(Request $request, $json = false)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        // dd($request->file());
        $request->validate([
            'id' => 'required', //hanya bisa update cretaed dari detail
            'company_code' => 'required',
            'company_name' => 'required',
            // 'internal_external' => 'required',
        ]);
        // dd($request);
        $id = $request->id;
        $company_code = $request->company_code;
        $company_name = $request->company_name;
        $company_short = $request->company_short;
        $company_attention = $request->company_attention;
        $company_address = $request->company_address;
        // $company_remarks = $request->company_remarks;

        $user_id = auth()->user()->id;

        if ($request->file('attachment')) {
            // dd('upload file');

            //API uploader
            // $ApiAttachmentsHse = new ApiAttachmentsHse;
            $request['id_group'] = $id;
            $gallery = self::uploadFile($request, $id);
            // dd(9,$ApiAttachmentsHse);
            $list_attachment = $gallery;
            $company_logo_id = $gallery[0]['data'][0]->id;
            // dd($list_attachment[0]['data'][0]->id);
        }
        //cek hse_indicator_method_id
        if (!is_numeric(@$indicator_method_id) && isset($indicator_method_id)) {
            // dd($indicator_method_id);
            $data[$id]['employee_name'] = $indicator_method_id;
            $HseIndicatorMethod = HseIndicatorMethod::create(
                [
                    'type' => $sheet_name,
                    'description' => $indicator_method_id
                ]
            );
            $indicator_method_id = $HseIndicatorMethod->id;
            // HseIndicatorMethod::find($indicator_method_id)->update(['nik' => $indicator_method_id]);
        }

        //cek employee
        if (!is_numeric(@$employee_id) && isset($employee_id)) {
            // dd($employee_id);
            $data[$id]['employee_name'] = $employee_id;
            $Employee = Employee::create(
                ['employee_name' => $employee_id]
            );
            $employee_id = $Employee->id;
            Employee::find($employee_id)->update(['nik' => $employee_id]);
        }
        //cek position
        if (@$position) {
            Employee::find($employee_id)->update(['employee_job_title' => $position]);
        }

        $data_config = self::config($id);

        if (is_numeric($id)) {
            $Company = Company::find($id);

            try {
                $code = 200;
                // dd(2,@$success_message,$company_code,$company_name,$internal_external,$company_start_date,$company_complete_date,$company_remarks);

                $FileManager = FileManager::find(@$company_logo_id);
                $Gallery = Gallery::find(@$FileManager->wgallery_id);
                // $Company['company_logo_url'] = $Gallery->url;

                $Company->update([
                    'company_code' => $company_code,
                    'company_name' => $company_name,
                    'company_short' => $company_short,
                    'company_attention' => $company_attention,
                    'company_address' => $company_address,
                    'company_logo_id' => @$company_logo_id,
                    'company_logo' => @$Gallery->url,
                ]);




                $success_message = 'Form  <b>' . $sheet_name . '</b> updated successfully';
            } catch (Exception $e) {
                // dd($e->getmessage());
                $code = 400;
                $success_message = @$e->getprevious()->errorInfo[2] ?? $e->getmessage();
            }

            if ($json) {
                $json_data = array(
                    "code" => $code,
                    "success_message" => $success_message,
                    "redirect"    => route($data_config['ajax']['url_prefix'] . '', ['id' => $id]),
                    "data"            => @$Company
                );
                return response()->json($json_data);
            } else {
                return redirect()->route($data_config['ajax']['url_prefix'] . '', ['id' => $id])
                    ->with('success_message', $success_message);
            }
            // dd($Company);

        } else {
            try {
                $code = 200;
                $Company = Company::create([
                    'company_code' => $company_code,
                    'company_name' => $company_name,
                    'internal_external' => $internal_external,
                    'company_start_date' => $company_start_date,
                    'company_complete_date' => $company_complete_date,
                    'company_remarks' => $company_remarks,
                ]);
                $id = $Company->id;
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
                    "redirect"    => route($data_config['ajax']['url_prefix'] . '', ['id' => $id]),
                    "data"            => @$Company ?? [],
                );
                return response()->json($json_data);
            } else {
                return redirect()->route($data_config['ajax']['url_prefix'] . '', ['id' => $id])
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
        return view('hse-dar.attachment', $page_var); //default view attachment ke layouts.attachment
    }

    public function destroy(Request $request, $id = null)
    {
        $company = Company::find($id);

        $gallery = Gallery::find($company->company_logo_id);

        if (@$gallery->url) {
            $path = str_replace(url('/storage/'), '', $gallery->url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $gallery->delete();
        }
        $company->delete();

        if ($company) {
            return redirect()->route($this->sheet_slug . '.index')->with('success', $this->sheet_slug . ' deleted successfully');
        } else {
            return redirect()->route($this->sheet_slug . '.index')->with('error', $this->sheet_slug . ' failed to delete');
        }
    }
}
