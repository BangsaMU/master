<?php
namespace Bangsamu\Master\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Controller;
use App\Models\User;

class CrudTabelController extends Controller
{
    protected $table='';
    protected $readonly = false;
    protected $sheet_name = 'Master - Employee'; //nama label untuk FE
    protected $sheet_slug = 'employee'; //nama routing (slug)

    protected $columns = '';
    protected $view_tabel_index = array(
        'm_crud.id AS No',
        'null AS action',
        'm_crud.no_id_karyawan AS no_id_karyawan',
        'm_crud.employee_name AS nama',
        'm_crud.employee_job_title AS posisi',
        'm_crud.no_ktp AS no_ktp',
        'm_l2.loc_name AS POH',
        'm_s.status AS status',
        'm_crud.tanggal_join AS tanggal_join',
        'm_crud.tanggal_akhir_kontrak AS tanggal_akhir_kontrak',
        'COALESCE(m_crud.tanggal_akhir_kerja,"AKTIF") AS tanggal_akhir_kerja',
        'm_crud.keterangan AS keterangan',
        '
        CASE
            WHEN FIND_IN_SET(m_crud.app_code, (
                SELECT s.value
                FROM setting s
                WHERE s.name = "employee_internal"
                AND s.category = "master_employee"
                AND s.deleted_at IS NULL
                LIMIT 1
            )) > 0 THEN "internal"
            WHEN FIND_IN_SET(m_crud.app_code, (
                SELECT s.value
                FROM setting s
                WHERE s.name = "employee_external"
                AND s.category = "master_employee"
                AND s.deleted_at IS NULL
                LIMIT 1
            )) > 0 THEN "external"
        ELSE "-"
        END AS employee_type',
    );

    protected $view_tabel = array(
        'm_crud.id AS No',
        'm_crud.no_id_karyawan AS no_id_karyawan',
        'm_crud.employee_name AS nama',
        'm_crud.employee_job_title AS posisi',
        'm_crud.no_ktp AS no_ktp',
        'm_crud.hire_id AS hire',
        'm_crud.status_id AS status',
        'm_crud.tanggal_join AS tanggal_join',
        'm_crud.tanggal_akhir_kontrak AS tanggal_akhir_kontrak',
        'm_crud.tanggal_akhir_kerja AS tanggal_akhir_kerja',
        'm_crud.keterangan AS keterangan',
        'm_crud.app_code AS employee_type',
        'null AS action',
    );


    public function __construct(Request $request)
    {
        $this->table = $request->route('table'); // table dari URL
        $this->sheet_slug = $request->route('table'); // table dari URL
        $this->columns = Schema::getColumnListing($this->table);
    }

    public function indexX()
    {
        $data = DB::table($this->table)->get();

        return view('master::crud.index', ['table' => $this->table, 'data' => $data]);
    }

        public function view_form($data)
    {
        $readonly = $this->readonly;
        extract($data);
        // dd($getRequisition->version,$id,$getRequisition->status);
        $global_disable = $readonly == false && checkPermission('is_admin') && $id ? false : true;
        $revisi_disable = @$getRequisition->version > 0 ? true : false;

        $columns = $this->columns??[];

        foreach ($columns as $key => $column) {
            $view_form[$key] = [
                'field' => $column,
                'name' => $column,
                'label' => ucwords(str_replace('_', ' ', $column)),
                'type' => 'text',
                'col' => 'col-12 col-md-4 mb-2',
                'disabled' => $global_disable || $revisi_disable ? true : false,
            ];
        }
        // dd($columns,$view_form);




        // dd($readonly,$global_disable);
        // dd($global_disable);
        // dd($global_disable);
        if (@$default_approval_id) {
            extract($default_approval_id);
        }
        if (@$default_for_info_id) {
            extract($default_for_info_id);
        }
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

        $view_form['employee_name'] = [
            'field' => 'employee_name',
            'name' => 'employee_name',
            'label' => 'Nama Lengkap',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];

        $view_form['employee_email'] = [
            'field' => 'employee_email',
            'name' => 'employee_email',
            'label' => 'Email',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];

        $view_form['corporate_email'] = [
            'field' => 'corporate_email',
            'name' => 'corporate_email',
            'label' => 'Email Corporate',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['no_ktp'] = [
            'field' => 'no_ktp',
            'name' => 'no_ktp',
            'label' => 'No KTP',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['status_id'] = [
            'field' => 'status_id',
            'name' => 'status_id',
            'label' => 'Status',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['employee_job_title'] = [
            'field' => 'employee_job_title',
            'name' => 'employee_job_title',
            'label' => 'Posisi (Jabatan)',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['hire_id'] = [
            'field' => 'hire_id',
            'name' => 'hire_id',
            'label' => 'Hire Lokasi',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['tanggal_join'] = [
            'field' => 'tanggal_join',
            'name' => 'tanggal_join',
            'label' => 'Tanggal Join',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['tanggal_akhir_kerja'] = [
            'field' => 'tanggal_akhir_kerja',
            'name' => 'tanggal_akhir_kerja',
            'label' => 'Tanggal Akhir Kerja',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['tanggal_akhir_kontrak'] = [
            'field' => 'tanggal_akhir_kontrak',
            'name' => 'tanggal_akhir_kontrak',
            'label' => 'Tanggal Akhir Kontrak',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['work_location_id'] = [
            'field' => 'work_location_id',
            'name' => 'work_location_id',
            'label' => 'Lokasi Kerja',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['keterangan'] = [
            'field' => 'keterangan',
            'name' => 'keterangan',
            'label' => 'Keterangan',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['no_id_karyawan'] = [
            'field' => 'no_id_karyawan',
            'name' => 'no_id_karyawan',
            'label' => 'No ID Karyawan',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
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
        $data['page']['list'] = url('crud/'.$this->table);

        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_employee') == true) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('employee')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_employee'))) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('crud.create',['table' => $this->table]);

            if (checkPermission('is_admin')) {
                $data['datatable']['btn']['import']['id'] = 'importitem';
                $data['datatable']['btn']['import']['title'] = 'Import Item';
                $data['datatable']['btn']['import']['icon'] = 'btn-primary';
                $data['datatable']['btn']['import']['url'] = '#';
                $data['datatable']['btn']['import']['act'] = 'importFn()';
            }
        }
/*
        if ((checkPermission('is_admin') || checkPermission('read_employee'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('crud.table.export', ['table' => 'master_employee']);
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('crud.employee.import');
        $data['page']['import']['template'] = url('/template/form_import_hrd.xlsx');
*/
        $errors = new \Illuminate\Support\ViewErrorBag;
        $page_var = compact('data','errors');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
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
        $data['page']['new']['url'] = route('crud.create',['table' => $this->table]);

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'] . '.mastermodal';

        $data['page']['js_list'][] = 'js.master-data';

        return $data;
    }

    public function dataJson(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;
        $tabel = $this->tabel??$request->route('table');

        $columns = Schema::getColumnListing($this->table);

        // misalnya kamu ingin 'id' selalu pertama, kemudian sisanya sesuai urutan abjad:
        $id = ['id']; // yang harus di awal
        $remaining = array_diff($columns, $id);
        sort($remaining);
        $columns = array_merge($id, $remaining);

        // $columns[]= 'NO.'; //tambah kolom action
        // Tambah di awal array
        array_unshift($columns, 'null AS action');
        $view_tabel_index = $columns;

        $limit = strpos('A|-1||', '|' . @$request->input('length') . '|') > 0 ? 10 : $request->input('length');
        $start = $request->input('start') ?? 0;

        $request_columns = $request->columns;
        $jml_char_nosearch = strlen(print_r($request_columns, true)); //0

        $char_nosearch = 0;
        $search = $request->input('search.value');

        $id = $request->id ?? 0;

        $offest = 0;
        $user_id = Auth::user()->id ?? 0;
        $user = User::find($user_id);

        if (method_exists(User::class, 'details')) {
            $details = User::details($user_id);
            if($details){
                $user_location_id = $details->location_id;
            }else {
                $user_location_id = '';
            }
        } else {
            $user_location_id = '';
        }

        if ($request->input('order.0.column')) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $view_tabel[$request->input('order.0.column')]);
            $order = $colom_filed[0] ?? 'id';
        } else {
            $order = 'm_crud.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        // dd($columns,$array_data_maping);
        // APP11 = HRD app
        $totalData = DB::table($this->table.' as m_crud')
            ->where(function ($query) use ($user_id,$user_location_id) {
                if (checkPermission('is_admin')) {
                    //bisa liat semua employee
                } else {
                    //hanya app hrd meindo
                    // $query
                    // ->where('m_crud.app_code', 'APP11')
                    // ->whereIn('hire_id', explode(',', $user_location_id))
                    // ;
                }
            })->whereNull('m_crud.deleted_at')->count();


        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table($this->table.' as m_crud')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                // ->leftJoin('master_status as m_s', 'm_crud.status_id', '=', 'm_s.id')
                // ->leftJoin('master_location as m_l', 'm_l.id', '=', 'm_crud.work_location_id')
                // ->leftJoin('master_location as m_l2', 'm_l2.id', '=', 'm_crud.hire_id')
                ->where(function ($query) use ($user_id,$user_location_id) {
                    if (checkPermission('is_admin')) {
                        //bisa liat semua employee
                    } else {
                        //hanya app hrd meindo
                        // $query
                        // ->where('m_crud.app_code', 'APP11')
                        // ->whereIn('hire_id', explode(',', $user_location_id))
                        // ;
                    }
                })->whereNull('m_crud.deleted_at');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('m_crud.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table($this->table.' as m_crud')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                // ->leftJoin('master_status as m_s', 'm_crud.status_id', '=', 'm_s.id')
                // ->leftJoin('master_location as m_l', 'm_l.id', '=', 'm_crud.work_location_id')
                // ->leftJoin('master_location as m_l2', 'm_l2.id', '=', 'm_crud.hire_id')
                ->where(function ($query) use ($user_id,$user_location_id) {
                    if (checkPermission('is_admin')) {
                        //bisa liat semua employee
                    } else {
                        //hanya app hrd meindo
                        // $query
                        // ->where('m_crud.app_code', 'APP11')
                        // ->whereIn('hire_id', explode(',', $user_location_id))
                        // ;
                    }
                })->whereNull('m_crud.deleted_at')
                ->groupby('m_crud.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start);

            $datatb_request = $datatb_request->get();

            $data_tabel = $datatb_request;
        }

        // $mapping_json[11] = 'action';
        // dd($view_tabel_index);
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
        // dd($data_tabel);
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
                // dd(1,$this->table,$row,checkPermission('is_admin'));
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && checkPermission('is_admin') ) {

                    // $btn .=  route('crud.edit',['id'=>$row->id,'table'=>$tabel]);

                    // $btn .= '<a href="' . route('crud.edit',['id'=>$row->id,'tabel'=>$tabel]) . '" class="btn btn-primary btn-sm">Update</a> ';
                    $btn .= '<a href="' . route('crud.edit',['id'=>$row->id,'table'=>$tabel]) . '" class="btn btn-primary btn-sm">Update</a>';
                    $btn .= '<a href="' . route('crud.destroy',['id'=>$row->id,'table'=>$tabel]) . '" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                } else {
                    $btn .= '<a href="' . route('crud.show',['id'=>$row->id,'table'=>$tabel]) . '" class="btn btn-primary btn-sm">View</a>';
                    // $btn .= '<a href="' . route('crud.show',['id'=>$row->id,'tabel'=>$tabel]) . '" class="btn btn-primary btn-sm">View</a>';
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
        return view('master::crud.create', ['table' => $this->table]);
    }

    public function store(Request $request)
    {
        $input = $request->except(['_token']);
        DB::table($this->table)->insert($input);
        return redirect()->route('master::crud.index', ['table' => $this->table]);
    }

    public function show(Request $request,$tabel,$id)
    {
        // $item = DB::table($this->table)->find($id);
        // return view('master::crud.show', ['table' => $this->table, 'item' => $item]);

        $this->readonly = true;
        return self::edit($request,$tabel,$id);
    }

    public function edit(Request $request,$tabel,$id)
    {
        if(empty($this->table) || empty($id)){
            abort(404, 'Tabel tidak ditemukan');
        }
        $form_row_type = 'single'; /*single / multi kalo single cek detail kosong redirect*/
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('crud.store',['table' => $this->table]);
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;

        $formdata = DB::table($this->table)->where('id',$id)->get();


        $formdata_multi = $formdata;

        $formdata = $formdata[0];
        /**
         * foreing_key buat input type hiden
         */
        $foreing_key['id'] = $id;

        $view_form = self::view_form(compact('id'));

        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');

        return view('master::layouts.dashboard.request', $page_var);
        // return view('master::crud.edit', ['table' => $this->table, 'data' => $item]);
    }

    public function update(Request $request, $id)
    {
        $input = $request->except(['_token', '_method']);
        DB::table($this->table)->where('id',$id)->update($input);
        return redirect()->route('master::crud.index', ['table' => $this->table]);
    }

    public function destroy($id)
    {
        DB::table($this->table)->where('id',$id)->delete();
        return redirect()->route('master::crud.index', ['table' => $this->table]);
    }
}
