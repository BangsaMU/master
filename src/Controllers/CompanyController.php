<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Bangsamu\Master\Models\Company;
use Bangsamu\Master\Models\Gallery;
use Bangsamu\Master\Models\FileManager;
use App\Models\HseIndicatorMethod;
use Bangsamu\Master\Models\Employee;
use Bangsamu\Master\Models\Setting;
use App\Http\Controllers\ApiAttachmentsHse;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Illuminate\Support\Facades\Log;

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
        $data['page']['list'] = route('master.' . $sheet_slug . '.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_company')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('company,gallery')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_company'))) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.' . $sheet_slug . '.create');

            // $data['datatable']['btn']['import']['id'] = 'importitem';
            // $data['datatable']['btn']['import']['title'] = 'Import';
            // $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            // $data['datatable']['btn']['import']['url'] = '#';
            // $data['datatable']['btn']['import']['act'] = 'importFn()';
        }

        if ((checkPermission('is_admin') || checkPermission('read_company'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = url('master/getmaster_company/export');
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

        $list_company = setting::where('name', 'list_include')
            ->where('category', 'master_company')
            ->value('value'); // Ambil langsung satu nilai

        // Konversi string ke array, lalu filter elemen kosong
        $list_company = array_filter(explode(",", $list_company));

        // Jika array kosong setelah difilter, set ke null
        $list_company = !empty($list_company) ? $list_company : null;

        $totalData = DB::table('master_company as mc')
            ->whereNull('mc.deleted_at')
            ->when($list_company, function ($query, $codes) {
                return $query->whereIn('mc.company_code', $codes);
            })
            ->count();

        // $totalData = DB::table('master_company as mc')->whereNull('mc.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_company as mc')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->whereNull('mc.deleted_at')
                ->when($list_company, function ($query, $codes) {
                    return $query->whereIn('mc.company_code', $codes);
                })
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
                ->when($list_company, function ($query, $codes) {
                    return $query->whereIn('mc.company_code', $codes);
                })
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('update_company'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }
                if ((checkPermission('is_admin') || checkPermission('delete_company'))) {
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
        $data['page']['readonly'] = $this->readonly;
        $param = null;

        // return view('master::master.company.form', compact('data', 'param'));
        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_code' => 'required|unique:master_company,company_code,' . ($request->id ?? 'NULL'),
            'company_name' => 'required',
        ]);
        $message = '';

        if ($request->id) {
            // Update existing company
            $company = Company::findOrFail($request->id);
            $update = $company->update([
                'company_code' => $request->company_code,
                'company_name' => $request->company_name,
                'company_short' => $request->company_short,
                'company_attention' => $request->company_attention,
                'company_address' => $request->company_address,
            ]);

            if ($request->file('company_logo') && $company->company_logo_id) {
                $existingGallery = Gallery::find($company->company_logo_id);
                if ($existingGallery) {
                    $existingPath = $existingGallery->path . '/' . $existingGallery->filename;

                    if (Storage::disk(config('app.storage_disk_master'))->exists($existingPath)) {
                        Storage::disk(config('app.storage_disk_master'))->delete($existingPath);
                        $existingGallery->delete();
                    }
                }
            }

            if ($update && $company->wasChanged()) {
                /*sync callback*/
                $id =  $company->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $company->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message .= ' '.$this->sheet_name . ' updated successfully';
            } else {
                $message .= ' '.$this->sheet_name . ' no data changed';
            }
        } else {
            // Create new company
            $company = Company::create([
                'company_code' => $request->company_code,
                'company_name' => $request->company_name,
                'company_short' => $request->company_short,
                'company_attention' => $request->company_attention,
                'company_address' => $request->company_address,
            ]);

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                // Create new company
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_'.$this->sheet_slug);

                $modelClass::create([
                    'company_code' => $request->company_code,
                    'company_name' => $request->company_name,
                    'company_short' => $request->company_short,
                    'company_attention' => $request->company_attention,
                    'company_address' => $request->company_address,
                    'created_at' => now(),
                ]);
            }

            $message .= ' '.$this->sheet_name . ' created successfully';
        }

        if ($request->file('company_logo')) {
            $file = $request->file('company_logo');
            $user_id = auth()->user()->id;

            $prefix = $file->getClientOriginalExtension();
            $caption = $file->getClientOriginalName();
            $url = null;
            $path = 'company/' . $prefix;
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
            
            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                // Create new gallery
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_gallery');

                $modelClass::create([
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
                    'created_at' => now(),
                ]);
            }

            $filename = $gallery->id . "-" . $file->getClientOriginalName();
            $filePath = $file->storeAs('company/' . $prefix, $filename, config('app.storage_disk_master'));
            // dd($filePath);
            if ($filePath && Storage::disk(config('app.storage_disk_master'))->exists($filePath)) {
                // Upload sukses dan file ada di storage
                // Log::info("Upload sukses: " . $filePath);

                Log::info('user: sys url: ' . url()->current() . ' message: Upload sukses json:' . json_encode($filePath));
            } else {
                // Gagal upload atau file tidak ada
                // Log::error("Gagal upload file ke: " . $filePath);
                Log::error('user: sys url: ' . url()->current() . ' message: Upload Gagal json:' . json_encode($filePath));
                abort(500, "Upload gagal atau file tidak ditemukan.");
            }


            $gallery->filename = $filename;
            $gallery->url = Storage::disk(config('app.storage_disk_master'))->url($path . '/' . $filename);
            $gallery->save();

            $company->company_logo_id = $gallery->id;
            $company->save();

            if ($gallery->wasChanged()) {
                /*sync callback gallery*/
                $id =  $gallery->id;
                $sync_tabel = 'master_gallery';
                $sync_id = $id;
                $sync_row = $gallery->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    // dd(2,compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message .= ' '.'Gallery updated successfully';
            } else {
                $message .= ' '.'Gallery no data changed';
            }

            if ($company->wasChanged()) {
                /*sync callback*/
                $id =  $company->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $company->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message .= ' Logo '.$this->sheet_name . ' updated successfully';
            } else {
                $message .= ' Logo '.$this->sheet_name . ' no data changed';
            }

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
        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
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
        $data['tab-menu']['title'] = Str::headline('Master ' . $sheet_name);

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
