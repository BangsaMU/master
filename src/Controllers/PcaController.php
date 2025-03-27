<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;

use Bangsamu\Master\Imports\Master\PcaImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\Pca;

class PcaController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - PCA'; //nama label untuk FE
    protected $sheet_slug = 'pca'; //nama routing (slug)
    protected $view_tabel_index = array(
        'mp.id AS No',
        // '"action" AS action',
        'mp.pca_code AS pca_code',
        'mp.pca_name AS pca_name',
        'mp.app_code AS app_code',
        '"action" AS action',
    );
    protected $view_tabel = array(
        'mp.id AS id',
        'mp.pca_code AS pca_code',
        'mp.pca_name AS pca_name',
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
        $data['page']['list'] = route('master.pca.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('read_pca')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = '';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning far fa-copy " style="color:#6c757d';
            $data['datatable']['btn']['sync']['act'] = "syncFn('pca')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_pca'))) {
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

        if ((checkPermission('is_admin') || checkPermission('read_pca'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = url('master/getmaster_pca/export');
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.pca.import');
        $data['page']['import']['template'] = url('/templates/PCAImportTemplate.xlsx');

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

        $totalData = DB::table('master_pca as mp')->whereNull('mp.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_pca as mp')
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
            $datatb_request = DB::table('master_pca as mp')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && $row->app_code == config('SsoConfig.main.APP_CODE')) {
                    if (checkPermission('is_admin') || checkPermission('update_pca')) {
                        $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                    };
                    if (checkPermission('is_admin') || checkPermission('delete_pca')) {
                        $btn .= '<a href="' . route('master.' . $sheet_slug . '.destroy', $row->No) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                    }
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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['list'] = route('master.' . $sheet_slug . '.index');
        $data['page']['readonly'] = false;
        $data['page']['title'] = $sheet_name;
        $param = null;

        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pca_code' => 'required|unique:master_' . $this->sheet_slug . ',pca_code' . ($request->id ? ',' . $request->id : ''),
            'pca_name' => 'required',
        ]);

        if ($request->id) {
            // Update existing pca
            $pca = Pca::findOrFail($request->id);
            $update = $pca->update([
                'pca_code' => $request->pca_code,
                'pca_name' => $request->pca_name,
            ]);

            if ($update && $pca->wasChanged()) {
                /*sync callback*/
                $id =  $pca->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $pca->toArray();
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
            DB::table('master_' . $this->sheet_slug)->insert([
                'pca_code' => $request->pca_code,
                'pca_name' => $request->pca_name,
                'app_code' => config('SsoConfig.main.APP_CODE'),
                'created_at' => now(),
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
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_' . $this->sheet_slug)->where('id', $id)->first();

        return view('master::master.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function destroy($id)
    {
        DB::table('master_' . $this->sheet_slug)->where('id', $id)->delete();

        return redirect()->route('master.' . $this->sheet_slug . '.index')->with('success', $this->sheet_slug . ' deleted successfully');
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file|max:2048|mimes:xls,xlsx,txt'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $import = new PcaImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.pca.index')->with('error_message', $error)->with('success_message', $success);
        }
    }
}
