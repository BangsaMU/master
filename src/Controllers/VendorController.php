<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;

use Bangsamu\Master\Imports\Master\VendorImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class VendorController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - Vendor'; //nama label untuk FE
    protected $sheet_slug = 'vendor'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mp.id AS No',
        // '"action" AS action',
        'mp.vendor_code AS vendor_code',
        'mp.vendor_description AS vendor_description',
        '"action" AS action',
    );
    protected $view_tabel = array(
        'mp.id AS id',
        'mp.vendor_code AS vendor_code',
        'mp.vendor_description AS vendor_description',
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
        $data['page']['new']['url'] = route('master.' . $sheet_slug . '.create');

        $data = configDefAction($id, $data);

        $data['page']['js_list'][] = 'js.master-data';

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
        $data['page']['list'] = route('master.vendor.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_vendor')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('vendor,vendor_contact')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_vendor'))) {
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

        if ((checkPermission('is_admin') || checkPermission('read_vendor'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = url('master/getmaster_vendor/export');
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.vendor.import');
        $data['page']['import']['template'] = url('/templates/VendorImportTemplate.xlsx');

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
            $order = 'mp.id';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $totalData = DB::table('master_vendor as mp')->whereNull('mp.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_vendor as mp')
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
            $datatb_request = DB::table('master_vendor as mp')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('update_vendor'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }
                if ((checkPermission('is_admin') || checkPermission('delete_vendor'))) {
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
        $data['page']['list'] = route('master.vendor.index');
        $data['page']['readonly'] = false;
        $data['page']['title'] = $sheet_name;
        $param = null;

        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_code' => 'required|unique:master_' . $this->sheet_slug . ',vendor_code' . ($request->id ? ',' . $request->id : ''),
            'vendor_description' => 'required',
            'vendor_address' => 'required',
        ]);

        if ($request->id) {
            // Update existing vendor
            $modelClass = LibraryClayController::resolveModelFromSheetSlug($this->sheet_slug); // misalnya "Vendor"
            $model = $modelClass::find($request->id);

            if ($model) {
                $update = $model->update([
                    'vendor_code' => $request->vendor_code,
                    'vendor_description' => $request->vendor_description,
                    'vendor_address' => $request->vendor_address,
                    'vendor_phone' => $request->vendor_phone,
                    'vendor_fax' => $request->vendor_fax,
                    'vendor_email' => $request->vendor_email,
                    'updated_at' => now(),
                ]); // ini akan trigger Loggable
            }else {
                abort(404, 'Model not found');
            }


            // DB::table('master_' . $this->sheet_slug)
            //     ->where('id', $request->id)
            //     ->update([
            //         'vendor_code' => $request->vendor_code,
            //         'vendor_description' => $request->vendor_description,
            //         'vendor_address' => $request->vendor_address,
            //         'vendor_phone' => $request->vendor_phone,
            //         'vendor_fax' => $request->vendor_fax,
            //         'vendor_email' => $request->vendor_email,
            //         'updated_at' => now(),
            //     ]);

            // Update existing vendor_contact
            $modelClass = LibraryClayController::resolveModelFromSheetSlug('vendor_contact'); // misalnya "Vendor"
            $model1 = $modelClass::find($request->id);

            if ($model1) {
                $update1 = $model1->updateOrCreate(
                    ['vendor_id' => $request->id],
                    [
                        'vendor_contact_name'  => $request->vendor_contact_name,
                        'vendor_contact_phone' => $request->vendor_contact_phone,
                        'vendor_contact_email' => $request->vendor_contact_email,
                        'vendor_contact_fax'   => $request->vendor_contact_fax,
                    ]
                ); // ini akan trigger Loggable

                if ($update1 && $model1->wasChanged()) {
                    /*sync callback*/
                    $id =  $model->id;
                    $sync_tabel = 'master_vendor_contact';
                    $sync_id = $id;
                    $sync_row = $model->toArray();
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
                
            }else {
                abort(404, 'Model not found');
            }

            // DB::table('master_vendor_contact')->updateOrInsert(
            //     ['vendor_id' => $request->id],
            //     [
            //         'vendor_contact_name' => $request->vendor_contact_name,
            //         'vendor_contact_phone' => $request->vendor_contact_phone,
            //         'vendor_contact_email' => $request->vendor_contact_email,
            //         'vendor_contact_fax' => $request->vendor_contact_fax,
            //     ]
            // );
 



            if ($update && $model->wasChanged()) {
                /*sync callback*/
                $id =  $model->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $model->toArray();
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
            // Create new vendor
            $vendor_id = DB::table('master_' . $this->sheet_slug)->insertGetId([
                'vendor_code' => $request->vendor_code,
                'vendor_description' => $request->vendor_description,
                'vendor_address' => $request->vendor_address,
                'vendor_phone' => $request->vendor_phone,
                'vendor_fax' => $request->vendor_fax,
                'vendor_email' => $request->vendor_email,
                'created_at' => now(),
            ]);

            $modelClass = LibraryClayController::resolveModelFromSheetSlug('vendor_contact');

            $modelClass::updateOrCreate(
                ['vendor_id' => $vendor_id], // kondisi pencarian
                [
                    'vendor_contact_name' => $request->vendor_contact_name,
                    'vendor_contact_phone' => $request->vendor_contact_phone,
                    'vendor_contact_email' => $request->vendor_contact_email,
                    'vendor_contact_fax' => $request->vendor_contact_fax,
                ]
            );

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                // Create new location
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_'.$this->sheet_slug);

                $modelClass::create([
                    'vendor_code' => $request->vendor_code,
                    'vendor_description' => $request->vendor_description,
                    'vendor_address' => $request->vendor_address,
                    'vendor_phone' => $request->vendor_phone,
                    'vendor_fax' => $request->vendor_fax,
                    'vendor_email' => $request->vendor_email,
                    'created_at' => now(),
                ]);


                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_vendor_contact');

                $modelClass::updateOrCreate(
                    ['vendor_id' => $vendor_id], // kondisi pencarian
                    [
                        'vendor_contact_name' => $request->vendor_contact_name,
                        'vendor_contact_phone' => $request->vendor_contact_phone,
                        'vendor_contact_email' => $request->vendor_contact_email,
                        'vendor_contact_fax' => $request->vendor_contact_fax,
                    ]
                );
                
            }


            // DB::table('master_vendor_contact')->updateOrInsert(
            //     ['vendor_id' => $vendor_id],
            //     [
            //         'vendor_contact_name' => $request->vendor_contact_name,
            //         'vendor_contact_phone' => $request->vendor_contact_phone,
            //         'vendor_contact_email' => $request->vendor_contact_email,
            //         'vendor_contact_fax' => $request->vendor_contact_fax,
            //     ]
            // );

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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_' . $this->sheet_slug . ' as mv')
            ->select(
                'mv.*',
                'mvc.vendor_contact_name',
                'mvc.vendor_contact_phone',
                'mvc.vendor_contact_email',
                'mvc.vendor_contact_fax',
            )
            ->leftJoin('master_vendor_contact as mvc', 'mv.id', 'mvc.vendor_id')
            ->where('mv.id', $id)->first();

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

            $import = new VendorImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.vendor.index')->with('error_message', $error)->with('success_message', $success);
        }
    }
}
