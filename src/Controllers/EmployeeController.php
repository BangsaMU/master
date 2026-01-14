<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;

// use App\Imports\Master\ProjectImport;
use Bangsamu\Master\Imports\Master\EmployeeImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\Master\Models\Employee;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Illuminate\Support\Str;
use Bangsamu\Master\Models\MasterStatus;
use Carbon\Carbon;
use Bangsamu\Master\Models\MasterLocation;
use Bangsamu\Master\Models\MasterIncrement;
// use Bangsamu\Master\Models\User;
use App\Models\User;
use Bangsamu\LibraryClay\Models\ActivityLog;
use Bangsamu\Master\Models\JobPosition as HrdJobPosition;
use Bangsamu\Master\Models\Employee as HrdKaryawan;

class EmployeeController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Master - Employee'; //nama label untuk FE
    protected $sheet_slug = 'employee'; //nama routing (slug)
    protected $view_tabel_index = array(
        'm_k.id AS No',
        'null AS action',
        'm_k.no_id_karyawan AS no_id_karyawan',
        'm_k.employee_name AS nama',
        'm_k.employee_job_title AS posisi',
        'm_k.no_ktp AS no_ktp',
        'm_l2.loc_name AS POH',
        'm_s.status AS status',
        'm_k.tanggal_join AS tanggal_join',
        'm_k.tanggal_akhir_kontrak AS tanggal_akhir_kontrak',
        'COALESCE(m_k.tanggal_akhir_kerja,"AKTIF") AS tanggal_akhir_kerja',
        'm_k.keterangan AS keterangan',
        '
        CASE
            WHEN FIND_IN_SET(m_k.app_code, (
                SELECT s.value
                FROM setting s
                WHERE s.name = "employee_internal"
                AND s.category = "master_employee"
                AND s.deleted_at IS NULL
                LIMIT 1
            )) > 0 THEN "internal"
            WHEN FIND_IN_SET(m_k.app_code, (
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
        'm_k.id AS No',
        'm_k.no_id_karyawan AS no_id_karyawan',
        'm_k.employee_name AS nama',
        'm_k.employee_job_title AS posisi',
        'm_k.no_ktp AS no_ktp',
        'm_k.hire_id AS hire',
        'm_k.status_id AS status',
        'm_k.tanggal_join AS tanggal_join',
        'm_k.tanggal_akhir_kontrak AS tanggal_akhir_kontrak',
        'm_k.tanggal_akhir_kerja AS tanggal_akhir_kerja',
        'm_k.keterangan AS keterangan',
        'm_k.app_code AS employee_type',
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
        $view_form['citizenship'] = [
            'field' => 'citizenship',
            'name' => 'citizenship',
            'label' => 'citizenship',
            'type' => 'select',
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
        $data['page']['list'] = route('master.employee.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List ' . $sheet_name;

        if (checkPermission('is_admin') || checkPermission('read_employee') == true) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('employee,status,location,job_position')";
        }

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('create_employee'))) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.' . $sheet_slug . '.create');

            if (checkPermission('is_admin')) {
                $data['datatable']['btn']['import']['id'] = 'importitem';
                $data['datatable']['btn']['import']['title'] = 'Import Item';
                $data['datatable']['btn']['import']['icon'] = 'btn-primary';
                $data['datatable']['btn']['import']['url'] = '#';
                $data['datatable']['btn']['import']['act'] = 'importFn()';
            }
        }

        if ((checkPermission('is_admin') || checkPermission('read_employee'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_employee']);
        }


        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.employee.import');
        $data['page']['import']['template'] = url('/template/form_import_hrd.xlsx');


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
        $user = User::find($user_id);

        if (method_exists(User::class, 'detailsLocation')) {
            $details = User::detailsLocation($user_id);
            $user_location_id = $details->location_id;
        } else {
            $user_location_id = '';
        }

        if ($request->input('order.0.column')) {
            /*remove alias*/
            $colom_filed = explode(" AS ", $view_tabel[$request->input('order.0.column')]);
            $order = $colom_filed[0] ?? 'id';
        } else {
            $order = 'm_k.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;
        // APP11 = HRD app
        $totalData = DB::table('master_employee as m_k')
            ->where(function ($query) use ($user_id,$user_location_id) {
                if (checkPermission('is_admin')||checkPermission('hrd_all_location')) {
                    //bisa liat semua employee
                } else {
                    //hanya app hrd meindo
                    $query
                    ->where('m_k.app_code', 'APP11')
                    ->whereIn('hire_id', explode(',', $user_location_id))
                    ;
                }
            })->whereNull('m_k.deleted_at')->count();


        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table('master_employee as m_k')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_status as m_s', 'm_k.status_id', '=', 'm_s.id')
                ->leftJoin('master_location as m_l', 'm_l.id', '=', 'm_k.work_location_id')
                ->leftJoin('master_location as m_l2', 'm_l2.id', '=', 'm_k.hire_id')
                ->where(function ($query) use ($user_id,$user_location_id) {
                    if (checkPermission('is_admin')||checkPermission('hrd_all_location')) {
                        //bisa liat semua employee
                    } else {
                        //hanya app hrd meindo
                        $query
                        ->where('m_k.app_code', 'APP11')
                        ->whereIn('hire_id', explode(',', $user_location_id))
                        ;
                    }
                })->whereNull('m_k.deleted_at');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('m_k.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start)
            ;

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table('master_employee as m_k')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->leftJoin('master_status as m_s', 'm_k.status_id', '=', 'm_s.id')
                ->leftJoin('master_location as m_l', 'm_l.id', '=', 'm_k.work_location_id')
                ->leftJoin('master_location as m_l2', 'm_l2.id', '=', 'm_k.hire_id')
                ->where(function ($query) use ($user_id,$user_location_id) {
                    if (checkPermission('is_admin')||checkPermission('hrd_all_location')) {
                        //bisa liat semua employee
                    } else {
                        //hanya app hrd meindo
                        $query
                        ->where('m_k.app_code', 'APP11')
                        ->whereIn('hire_id', explode(',', $user_location_id))
                        ;
                    }
                })->whereNull('m_k.deleted_at')
                ->groupby('m_k.id')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (checkPermission('is_admin') || checkPermission('update_employee'))) {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.edit', $row->No) . '" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="' . route('master.' . $sheet_slug . '.show', $row->No) . '" class="btn btn-primary btn-sm">View</a>';
                }
                if ((checkPermission('is_admin') || checkPermission('delete_employee'))) {
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
        $param = new \stdClass();
        $statuses = MasterStatus::select('id', DB::raw('concat(kode, " - ", status) as status'))->get();
        $list_status = $statuses->pluck('status', 'id');
        $param->status = $statuses;

        $param->country_code = [
            ''=>'-',
            'ABW'=>'Aruba',
            'AFG'=>'Afghanistan',
            'AGO'=>'Angola',
            'AIA'=>'Anguilla',
            'ALA'=>'Åland Islands',
            'ALB'=>'Albania',
            'AND'=>'Andorra',
            'ARE'=>'United Arab Emirates',
            'ARG'=>'Argentina',
            'ARM'=>'Armenia',
            'ASM'=>'American Samoa',
            'ATA'=>'Antarctica',
            'ATF'=>'French Southern Territories',
            'ATG'=>'Antigua and Barbuda',
            'AUS'=>'Australia',
            'AUT'=>'Austria',
            'AZE'=>'Azerbaijan',
            'BDI'=>'Burundi',
            'BEL'=>'Belgium',
            'BEN'=>'Benin',
            'BES'=>'Bonaire, Sint Eustatius and Saba',
            'BFA'=>'Burkina Faso',
            'BGD'=>'Bangladesh',
            'BGR'=>'Bulgaria',
            'BHR'=>'Bahrain',
            'BHS'=>'Bahamas',
            'BIH'=>'Bosnia and Herzegovina',
            'BLM'=>'Saint Barthélemy',
            'BLR'=>'Belarus',
            'BLZ'=>'Belize',
            'BMU'=>'Bermuda',
            'BOL'=>'Bolivia, Plurinational State of',
            'BRA'=>'Brazil',
            'BRB'=>'Barbados',
            'BRN'=>'Brunei Darussalam',
            'BTN'=>'Bhutan',
            'BVT'=>'Bouvet Island',
            'BWA'=>'Botswana',
            'CAF'=>'Central African Republic',
            'CAN'=>'Canada',
            'CCK'=>'Cocos (Keeling) Islands',
            'CHE'=>'Switzerland',
            'CHL'=>'Chile',
            'CHN'=>'China',
            'CIV'=>'Côte d`Ivoire',
            'CMR'=>'Cameroon',
            'COD'=>'Congo, Democratic Republic of the',
            'COG'=>'Congo',
            'COK'=>'Cook Islands',
            'COL'=>'Colombia',
            'COM'=>'Comoros',
            'CPV'=>'Cabo Verde',
            'CRI'=>'Costa Rica',
            'CUB'=>'Cuba',
            'CUW'=>'Curaçao',
            'CXR'=>'Christmas Island',
            'CYM'=>'Cayman Islands',
            'CYP'=>'Cyprus',
            'CZE'=>'Czechia',
            'DEU'=>'Germany',
            'DJI'=>'Djibouti',
            'DMA'=>'Dominica',
            'DNK'=>'Denmark',
            'DOM'=>'Dominican Republic',
            'DZA'=>'Algeria',
            'ECU'=>'Ecuador',
            'EGY'=>'Egypt',
            'ERI'=>'Eritrea',
            'ESH'=>'Western Sahara',
            'ESP'=>'Spain',
            'EST'=>'Estonia',
            'ETH'=>'Ethiopia',
            'FIN'=>'Finland',
            'FJI'=>'Fiji',
            'FLK'=>'Falkland Islands (Malvinas)',
            'FRA'=>'France',
            'FRO'=>'Faroe Islands',
            'FSM'=>'Micronesia, Federated States of',
            'GAB'=>'Gabon',
            'GBR'=>'United Kingdom of Great Britain and Northern Ireland',
            'GEO'=>'Georgia',
            'GGY'=>'Guernsey',
            'GHA'=>'Ghana',
            'GIB'=>'Gibraltar',
            'GIN'=>'Guinea',
            'GLP'=>'Guadeloupe',
            'GMB'=>'Gambia',
            'GNB'=>'Guinea-Bissau',
            'GNQ'=>'Equatorial Guinea',
            'GRC'=>'Greece',
            'GRD'=>'Grenada',
            'GRL'=>'Greenland',
            'GTM'=>'Guatemala',
            'GUF'=>'French Guiana',
            'GUM'=>'Guam',
            'GUY'=>'Guyana',
            'HKG'=>'Hong Kong',
            'HMD'=>'Heard Island and McDonald Islands',
            'HND'=>'Honduras',
            'HRV'=>'Croatia',
            'HTI'=>'Haiti',
            'HUN'=>'Hungary',
            'IDN'=>'Indonesia',
            'IMN'=>'Isle of Man',
            'IND'=>'India',
            'IOT'=>'British Indian Ocean Territory',
            'IRL'=>'Ireland',
            'IRN'=>'Iran, Islamic Republic of',
            'IRQ'=>'Iraq',
            'ISL'=>'Iceland',
            'ISR'=>'Israel',
            'ITA'=>'Italy',
            'JAM'=>'Jamaica',
            'JEY'=>'Jersey',
            'JOR'=>'Jordan',
            'JPN'=>'Japan',
            'KAZ'=>'Kazakhstan',
            'KEN'=>'Kenya',
            'KGZ'=>'Kyrgyzstan',
            'KHM'=>'Cambodia',
            'KIR'=>'Kiribati',
            'KNA'=>'Saint Kitts and Nevis',
            'KOR'=>'Korea, Republic of',
            'KWT'=>'Kuwait',
            'LAO'=>'Lao People`s Democratic Republic',
            'LBN'=>'Lebanon',
            'LBR'=>'Liberia',
            'LBY'=>'Libya',
            'LCA'=>'Saint Lucia',
            'LIE'=>'Liechtenstein',
            'LKA'=>'Sri Lanka',
            'LSO'=>'Lesotho',
            'LTU'=>'Lithuania',
            'LUX'=>'Luxembourg',
            'LVA'=>'Latvia',
            'MAC'=>'Macao',
            'MAF'=>'Saint Martin (French part)',
            'MAR'=>'Morocco',
            'MCO'=>'Monaco',
            'MDA'=>'Moldova, Republic of',
            'MDG'=>'Madagascar',
            'MDV'=>'Maldives',
            'MEX'=>'Mexico',
            'MHL'=>'Marshall Islands',
            'MKD'=>'North Macedonia',
            'MLI'=>'Mali',
            'MLT'=>'Malta',
            'MMR'=>'Myanmar',
            'MNE'=>'Montenegro',
            'MNG'=>'Mongolia',
            'MNP'=>'Northern Mariana Islands',
            'MOZ'=>'Mozambique',
            'MRT'=>'Mauritania',
            'MSR'=>'Montserrat',
            'MTQ'=>'Martinique',
            'MUS'=>'Mauritius',
            'MWI'=>'Malawi',
            'MYS'=>'Malaysia',
            'MYT'=>'Mayotte',
            'NAM'=>'Namibia',
            'NCL'=>'New Caledonia',
            'NER'=>'Niger',
            'NFK'=>'Norfolk Island',
            'NGA'=>'Nigeria',
            'NIC'=>'Nicaragua',
            'NIU'=>'Niue',
            'NLD'=>'Netherlands, Kingdom of the',
            'NOR'=>'Norway',
            'NPL'=>'Nepal',
            'NRU'=>'Nauru',
            'NZL'=>'New Zealand',
            'OMN'=>'Oman',
            'PAK'=>'Pakistan',
            'PAN'=>'Panama',
            'PCN'=>'Pitcairn',
            'PER'=>'Peru',
            'PHL'=>'Philippines',
            'PLW'=>'Palau',
            'PNG'=>'Papua New Guinea',
            'POL'=>'Poland',
            'PRI'=>'Puerto Rico',
            'PRK'=>'Korea, Democratic People`s Republic of',
            'PRT'=>'Portugal',
            'PRY'=>'Paraguay',
            'PSE'=>'Palestine, State of',
            'PYF'=>'French Polynesia',
            'QAT'=>'Qatar',
            'REU'=>'Réunion',
            'ROU'=>'Romania',
            'RUS'=>'Russian Federation',
            'RWA'=>'Rwanda',
            'SAU'=>'Saudi Arabia',
            'SDN'=>'Sudan',
            'SEN'=>'Senegal',
            'SGP'=>'Singapore',
            'SGS'=>'South Georgia and the South Sandwich Islands',
            'SHN'=>'Saint Helena, Ascension and Tristan da Cunha',
            'SJM'=>'Svalbard and Jan Mayen',
            'SLB'=>'Solomon Islands',
            'SLE'=>'Sierra Leone',
            'SLV'=>'El Salvador',
            'SMR'=>'San Marino',
            'SOM'=>'Somalia',
            'SPM'=>'Saint Pierre and Miquelon',
            'SRB'=>'Serbia',
            'SSD'=>'South Sudan',
            'STP'=>'Sao Tome and Principe',
            'SUR'=>'Suriname',
            'SVK'=>'Slovakia',
            'SVN'=>'Slovenia',
            'SWE'=>'Sweden',
            'SWZ'=>'Eswatini',
            'SXM'=>'Sint Maarten (Dutch part)',
            'SYC'=>'Seychelles',
            'SYR'=>'Syrian Arab Republic',
            'TCA'=>'Turks and Caicos Islands',
            'TCD'=>'Chad',
            'TGO'=>'Togo',
            'THA'=>'Thailand',
            'TJK'=>'Tajikistan',
            'TKL'=>'Tokelau',
            'TKM'=>'Turkmenistan',
            'TLS'=>'Timor-Leste',
            'TON'=>'Tonga',
            'TTO'=>'Trinidad and Tobago',
            'TUN'=>'Tunisia',
            'TUR'=>'Türkiye',
            'TUV'=>'Tuvalu',
            'TWN'=>'Taiwan, Province of China',
            'TZA'=>'Tanzania, United Republic of',
            'UGA'=>'Uganda',
            'UKR'=>'Ukraine',
            'UMI'=>'United States Minor Outlying Islands',
            'URY'=>'Uruguay',
            'USA'=>'United States of America',
            'UZB'=>'Uzbekistan',
            'VAT'=>'Holy See',
            'VCT'=>'Saint Vincent and the Grenadines',
            'VEN'=>'Venezuela, Bolivarian Republic of',
            'VGB'=>'Virgin Islands (British)',
            'VIR'=>'Virgin Islands (U.S.)',
            'VNM'=>'Viet Nam',
            'VUT'=>'Vanuatu',
            'WLF'=>'Wallis and Futuna',
            'WSM'=>'Samoa',
            'YEM'=>'Yemen',
            'ZAF'=>'South Africa',
            'ZMB'=>'Zambia',
            'ZWE'=>'Zimbabwe',
        ];

        // $user_location_id = auth()->user()->details()->location_id;
        // if (!checkPermission('admin')) {
        //     $hire_loc = MasterLocation::whereIn('id', explode(',', $user_location_id))->get();
        // } else {
        $list_hire_loc = MasterLocation::where('group_type', 'hrd')->get();
        $param->hire_loc = $list_hire_loc;
        // }

        // $list_work_location = MasterLocation::all();
        // dd($list_work_location);
        $param->work_location = null;

        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        // add form citizenship

        $status_id = $request->status_id;
        $status = MasterStatus::when($request->status_id !== null, function ($query) use ($request) {
            return $query->where('id', $request->status_id);
        }, function ($query) {
            return $query->where('kode', 0);
        })->first();

        $app_code = config('SsoConfig.main.APP_CODE');

        $request->validate([
            // 'no_ktp' => 'required|numeric|digits:16|unique:master_' . $this->sheet_slug . ',no_ktp' . ($request->id ? ',' . $request->id : ''),
            // 'no_ktp' => [
            //     'required',
            //     'numeric',
            //     'digits:16',
            //     'unique:master_' . $this->sheet_slug . ',no_ktp' . ($request->id ? ',' . $request->id : ''),
            //     function ($attribute, $value, $fail) {
            //         if (!LibraryClayController::isValidNIK($value)) {
            //             $fail('Format NIK tidak valid atau tidak sesuai kode wilayah/tanggal.');
            //         }
            //     },
            // ],

            'employee_name' => 'required',
            'citizenship' => "required",
            'employee_email' => "nullable|email|max:150",
            // 'no_ktp' => 'required|numeric|digits:16',
            // 'no_ktp' => 'required_if:citizenship,WNI|numeric|digits:16',
            'no_ktp' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->citizenship === 'WNI') {
                        if (!is_numeric($value) || strlen($value) !== 16) {
                            $fail('No KTP harus berupa angka 16 digit untuk WNI.');
                        }
                    }
                }
            ],
            'status_id' => 'required',
            'job_position_id' => 'nullable',
            'hire_id' => 'nullable',
            'tanggal_join'  => $status->kode == 0 ? 'nullable|date' : 'required|date',
            // 'tanggal_akhir_kerja' => 'nullable|date|after:tanggal_join',
            // 'tanggal_akhir_kontrak' => 'required|date|after:tanggal_join',
            'tanggal_akhir_kerja' => $request->tanggal_join ? 'nullable|date|after:tanggal_join' : 'nullable|date',
            'tanggal_akhir_kontrak' => $request->tanggal_join ? 'nullable|date|after:tanggal_join' : 'nullable|date',
            'corporate_email' => "nullable|email|max:150",
            'keterangan' => "nullable",
            'work_location_id' => "nullable",
        ]);

        // Validasi lanjutan tergantung status_id
        if ((int) $request->status_id !== 0) {
            $request->validate([
                'job_position_id' => 'required',
                'hire_id' => 'required',
                'tanggal_join' => 'required|date',
                'tanggal_akhir_kontrak' => 'required|date|after:tanggal_join',
            ]);
        }

        // Validasi lanjutan tergantung status_id bukan EXSPATRIAT ga bisa harus pakek filed bantu cityzen blm ada
        // if ((int) $request->status_id !== 3) {
        //     $request->validate([
        //             'no_ktp' => [
        //             'required',
        //             'numeric',
        //             'digits:16',
        //             'unique:master_' . $this->sheet_slug . ',no_ktp' . ($request->id ? ',' . $request->id : ''),
        //             function ($attribute, $value, $fail) {
        //                 if (!LibraryClayController::isValidNIK($value)) {
        //                     $fail('Format NIK tidak valid atau tidak sesuai kode wilayah/tanggal.');
        //                 }
        //             },
        //         ],
        //     ]);
        // }

        if ($request->id) {
            // Update existing employee
            $employee = Employee::findOrFail($request->id);

            $jobPositionData = HrdJobPosition::find($request->job_position_id);

            $update = $employee->update([
                'employee_name' => $request->employee_name,
                'employee_phone' => $request->employee_phone,
                'employee_job_title' =>  strtoupper($jobPositionData->position_name),
                'employee_email' => $request->employee_email,
                'employee_phone' => $request->employee_phone,
                'deleted_at' => $request->deleted_at,
                'corporate_email' => $request->corporate_email,
                'no_ktp' => $request->no_ktp,
                'no_id_karyawan' => $request->no_id_karyawan,
                'status_id' => $request->status_id,
                'hire_id' => $request->hire_id,
                'tanggal_join' => $request->tanggal_join,
                'tanggal_akhir_kerja' => $request->tanggal_akhir_kerja,
                'tanggal_akhir_kontrak' => $request->tanggal_akhir_kontrak,
                'keterangan' => $request->keterangan,
                'work_location_id' => $request->work_location_id,
                'employee_blood_type' => $request->employee_blood_type,
                'citizenship' => $request->citizenship,
                'country_code' => $request->country_code,
            ]);

            if ($update && $employee->wasChanged()) {
                /*sync callback*/
                $id =  $employee->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                $sync_row = $employee->toArray();
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
            $karyawan_active = Employee::where('no_ktp', $request->no_ktp)
            ->whereNull('tanggal_akhir_kerja')
            ->latest()->first();

            if ($karyawan_active) {
               $message = 'Data dengan NIK ' . $request->no_ktp . ' sudah ada dan masih aktif';
             } else {
                // Create new employee
                $employee = Employee::create([
                    'employee_name' => strtoupper($request->employee_name),
                    'employee_phone' => $request->employee_phone,
                    'employee_job_title' => strtoupper($request->employee_job_title),
                    'employee_email' => $request->employee_email,
                    'employee_phone' => $request->employee_phone ?? '-',
                    'deleted_at' => $request->deleted_at,
                    'corporate_email' => $request->corporate_email,
                    'no_ktp' => $request->no_ktp,
                    'no_id_karyawan' => $request->no_id_karyawan,
                    'status_id' => $request->status_id,
                    'hire_id' => $request->hire_id,
                    'tanggal_join' => $request->tanggal_join,
                    'tanggal_akhir_kerja' => $request->tanggal_akhir_kerja,
                    'tanggal_akhir_kontrak' => $request->tanggal_akhir_kontrak,
                    'keterangan' => $request->keterangan,
                    'work_location_id' => $request->work_location_id,
                    'employee_blood_type' => $request->employee_blood_type,
                    'app_code' => $app_code,
                    'citizenship' => $request->citizenship,
                    'country_code' => $request->country_code,
                ]);
                $message = $this->sheet_name . ' created successfully';
            }
        }

        //hanya generate jika dari app HRD
        if ($app_code == 'APP11') {
            $unique_group = self::getUniqueFormat($request->tanggal_join, $request->hire_id, $request->status_id);
            $no_id_karyawan = self::createNIPKaryawan($unique_group, $employee->id);

            $employee->no_id_karyawan = $no_id_karyawan;
            $employee->update();
        }

        if ($employee) {
            $sync_row = $employee->toArray();

            if (!empty($sync_row)) {
                /*sync callback*/
                $id =  $employee->id;
                $sync_tabel = 'master_' . $this->sheet_slug;
                $sync_id = $id;
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message = $this->sheet_name . ' updated successfully';
            } else {
                $message = $this->sheet_name . ' has no data to sync';
            }
        } else {
            $message = $this->sheet_name . ' no data changed';
        }

        return redirect()->route('master.' . $this->sheet_slug . '.index')->with('success_message', $message);
    }


    public function getUniqueFormat($tanggal_join, $hire_id, $status_id)
    {
        //check join year lessthan 2000
        $date1 = Carbon::createFromFormat('Y-m-d', $tanggal_join);
        $date2 = Carbon::createFromFormat('Y-m-d', '2000-12-12');
        if ($date1->lt($date2)) {
            $year = '00';
        } else {
            $year = Carbon::createFromFormat('Y-m-d', $tanggal_join)->format('y');
        }

        $hire_loc = MasterLocation::find($hire_id);
        $unique_group = $status_id . $hire_loc->loc_code . $year;

        return $unique_group;
    }

    public function createNIPKaryawan($unique_group, $karyawan_id)
    {
        $increment_data = MasterIncrement::where('unique_group', $unique_group)->max('increment');

        if (!$increment_data) {
            $increment = MasterIncrement::create([
                'object_id' => $karyawan_id,
                'unique_group' => $unique_group,
                'increment' => 1,
            ]);
        } else {
            $increment = MasterIncrement::create([
                'object_id' => $karyawan_id,
                'unique_group' => $unique_group,
                'increment' => $increment_data + 1,
            ]);
        }

        $no_urut_karyawan = str_pad($increment->increment, 5, "0", STR_PAD_LEFT);
        $no_id_karyawan = $unique_group . $no_urut_karyawan;

        return $no_id_karyawan;
    }


    public function show($id)
    {
        $this->readonly = true;
        return self::edit($id);
    }

    public function edit($id)
    {
        $form_row_type = 'single'; /*single / multi kalo single cek detail kosong redirect*/
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.' . $sheet_slug . '.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        // $param = DB::table('master_' . $this->sheet_slug)->where('id', $id)->first();

        $data['page']['logs'] = ActivityLog::
            select(
                'id',
                'model_type',
                'model_id',
                'user_id',
                'action',
                DB::raw('SUBSTRING_INDEX(description, action, 1) AS nama_user'),
                DB::raw('TRIM(SUBSTRING_INDEX(description, action, -1)) AS sort_description'),
                'description',
                'properties',
                'updated_at',
                DB::raw('DATE_FORMAT(updated_at, "%e %M %Y %H:%i:%s") AS updated_at_formated'),
                DB::raw('DATE_FORMAT(updated_at, "%e %M %Y") AS updated_at_formated_date'),
                DB::raw('DATE_FORMAT(updated_at, "%H:%i:%s") AS updated_at_formated_time'),
            )
            ->where('model_type', 'Bangsamu\Master\Models\Employee')
            ->where('model_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        // dd($data['page']['logs']);
        // $param = DB::table('master_' . $this->sheet_slug)->select('master_employee.*', 'm_l.loc_name as work_location_name')
        //     ->leftJoin('master_location as m_l', 'm_l.id', '=', 'master_employee.work_location_id')
        //     ->where('master_employee.id', $id)
        //     ->first();

        $param = HrdKaryawan::select('master_employee.*', 'mjp.position_code','md.department_name','ml.loc_name as work_location_name', 'master_employee.job_position_id')
            ->leftJoin('master_location as ml', 'ml.id', '=', 'master_employee.work_location_id')
            ->leftJoin('master_job_position as mjp', 'mjp.id', '=', 'master_employee.job_position_id')
            ->leftJoin('master_department as md', 'md.id', '=', 'mjp.department_id')
            ->where('master_employee.id', $id)
            ->first();

        $param->work_location_id = session()->getOldInput('work_location_id') ?? $param->work_location_id;

        $statuses = MasterStatus::select('id', DB::raw('concat(kode, " - ", status) as status'))->get();
        $list_status = $statuses->pluck('status', 'id');
        $param->status = $statuses;

        $param->country_code = [
            ''=>'-',
            'ABW'=>'Aruba',
            'AFG'=>'Afghanistan',
            'AGO'=>'Angola',
            'AIA'=>'Anguilla',
            'ALA'=>'Åland Islands',
            'ALB'=>'Albania',
            'AND'=>'Andorra',
            'ARE'=>'United Arab Emirates',
            'ARG'=>'Argentina',
            'ARM'=>'Armenia',
            'ASM'=>'American Samoa',
            'ATA'=>'Antarctica',
            'ATF'=>'French Southern Territories',
            'ATG'=>'Antigua and Barbuda',
            'AUS'=>'Australia',
            'AUT'=>'Austria',
            'AZE'=>'Azerbaijan',
            'BDI'=>'Burundi',
            'BEL'=>'Belgium',
            'BEN'=>'Benin',
            'BES'=>'Bonaire, Sint Eustatius and Saba',
            'BFA'=>'Burkina Faso',
            'BGD'=>'Bangladesh',
            'BGR'=>'Bulgaria',
            'BHR'=>'Bahrain',
            'BHS'=>'Bahamas',
            'BIH'=>'Bosnia and Herzegovina',
            'BLM'=>'Saint Barthélemy',
            'BLR'=>'Belarus',
            'BLZ'=>'Belize',
            'BMU'=>'Bermuda',
            'BOL'=>'Bolivia, Plurinational State of',
            'BRA'=>'Brazil',
            'BRB'=>'Barbados',
            'BRN'=>'Brunei Darussalam',
            'BTN'=>'Bhutan',
            'BVT'=>'Bouvet Island',
            'BWA'=>'Botswana',
            'CAF'=>'Central African Republic',
            'CAN'=>'Canada',
            'CCK'=>'Cocos (Keeling) Islands',
            'CHE'=>'Switzerland',
            'CHL'=>'Chile',
            'CHN'=>'China',
            'CIV'=>'Côte d`Ivoire',
            'CMR'=>'Cameroon',
            'COD'=>'Congo, Democratic Republic of the',
            'COG'=>'Congo',
            'COK'=>'Cook Islands',
            'COL'=>'Colombia',
            'COM'=>'Comoros',
            'CPV'=>'Cabo Verde',
            'CRI'=>'Costa Rica',
            'CUB'=>'Cuba',
            'CUW'=>'Curaçao',
            'CXR'=>'Christmas Island',
            'CYM'=>'Cayman Islands',
            'CYP'=>'Cyprus',
            'CZE'=>'Czechia',
            'DEU'=>'Germany',
            'DJI'=>'Djibouti',
            'DMA'=>'Dominica',
            'DNK'=>'Denmark',
            'DOM'=>'Dominican Republic',
            'DZA'=>'Algeria',
            'ECU'=>'Ecuador',
            'EGY'=>'Egypt',
            'ERI'=>'Eritrea',
            'ESH'=>'Western Sahara',
            'ESP'=>'Spain',
            'EST'=>'Estonia',
            'ETH'=>'Ethiopia',
            'FIN'=>'Finland',
            'FJI'=>'Fiji',
            'FLK'=>'Falkland Islands (Malvinas)',
            'FRA'=>'France',
            'FRO'=>'Faroe Islands',
            'FSM'=>'Micronesia, Federated States of',
            'GAB'=>'Gabon',
            'GBR'=>'United Kingdom of Great Britain and Northern Ireland',
            'GEO'=>'Georgia',
            'GGY'=>'Guernsey',
            'GHA'=>'Ghana',
            'GIB'=>'Gibraltar',
            'GIN'=>'Guinea',
            'GLP'=>'Guadeloupe',
            'GMB'=>'Gambia',
            'GNB'=>'Guinea-Bissau',
            'GNQ'=>'Equatorial Guinea',
            'GRC'=>'Greece',
            'GRD'=>'Grenada',
            'GRL'=>'Greenland',
            'GTM'=>'Guatemala',
            'GUF'=>'French Guiana',
            'GUM'=>'Guam',
            'GUY'=>'Guyana',
            'HKG'=>'Hong Kong',
            'HMD'=>'Heard Island and McDonald Islands',
            'HND'=>'Honduras',
            'HRV'=>'Croatia',
            'HTI'=>'Haiti',
            'HUN'=>'Hungary',
            'IDN'=>'Indonesia',
            'IMN'=>'Isle of Man',
            'IND'=>'India',
            'IOT'=>'British Indian Ocean Territory',
            'IRL'=>'Ireland',
            'IRN'=>'Iran, Islamic Republic of',
            'IRQ'=>'Iraq',
            'ISL'=>'Iceland',
            'ISR'=>'Israel',
            'ITA'=>'Italy',
            'JAM'=>'Jamaica',
            'JEY'=>'Jersey',
            'JOR'=>'Jordan',
            'JPN'=>'Japan',
            'KAZ'=>'Kazakhstan',
            'KEN'=>'Kenya',
            'KGZ'=>'Kyrgyzstan',
            'KHM'=>'Cambodia',
            'KIR'=>'Kiribati',
            'KNA'=>'Saint Kitts and Nevis',
            'KOR'=>'Korea, Republic of',
            'KWT'=>'Kuwait',
            'LAO'=>'Lao People`s Democratic Republic',
            'LBN'=>'Lebanon',
            'LBR'=>'Liberia',
            'LBY'=>'Libya',
            'LCA'=>'Saint Lucia',
            'LIE'=>'Liechtenstein',
            'LKA'=>'Sri Lanka',
            'LSO'=>'Lesotho',
            'LTU'=>'Lithuania',
            'LUX'=>'Luxembourg',
            'LVA'=>'Latvia',
            'MAC'=>'Macao',
            'MAF'=>'Saint Martin (French part)',
            'MAR'=>'Morocco',
            'MCO'=>'Monaco',
            'MDA'=>'Moldova, Republic of',
            'MDG'=>'Madagascar',
            'MDV'=>'Maldives',
            'MEX'=>'Mexico',
            'MHL'=>'Marshall Islands',
            'MKD'=>'North Macedonia',
            'MLI'=>'Mali',
            'MLT'=>'Malta',
            'MMR'=>'Myanmar',
            'MNE'=>'Montenegro',
            'MNG'=>'Mongolia',
            'MNP'=>'Northern Mariana Islands',
            'MOZ'=>'Mozambique',
            'MRT'=>'Mauritania',
            'MSR'=>'Montserrat',
            'MTQ'=>'Martinique',
            'MUS'=>'Mauritius',
            'MWI'=>'Malawi',
            'MYS'=>'Malaysia',
            'MYT'=>'Mayotte',
            'NAM'=>'Namibia',
            'NCL'=>'New Caledonia',
            'NER'=>'Niger',
            'NFK'=>'Norfolk Island',
            'NGA'=>'Nigeria',
            'NIC'=>'Nicaragua',
            'NIU'=>'Niue',
            'NLD'=>'Netherlands, Kingdom of the',
            'NOR'=>'Norway',
            'NPL'=>'Nepal',
            'NRU'=>'Nauru',
            'NZL'=>'New Zealand',
            'OMN'=>'Oman',
            'PAK'=>'Pakistan',
            'PAN'=>'Panama',
            'PCN'=>'Pitcairn',
            'PER'=>'Peru',
            'PHL'=>'Philippines',
            'PLW'=>'Palau',
            'PNG'=>'Papua New Guinea',
            'POL'=>'Poland',
            'PRI'=>'Puerto Rico',
            'PRK'=>'Korea, Democratic People`s Republic of',
            'PRT'=>'Portugal',
            'PRY'=>'Paraguay',
            'PSE'=>'Palestine, State of',
            'PYF'=>'French Polynesia',
            'QAT'=>'Qatar',
            'REU'=>'Réunion',
            'ROU'=>'Romania',
            'RUS'=>'Russian Federation',
            'RWA'=>'Rwanda',
            'SAU'=>'Saudi Arabia',
            'SDN'=>'Sudan',
            'SEN'=>'Senegal',
            'SGP'=>'Singapore',
            'SGS'=>'South Georgia and the South Sandwich Islands',
            'SHN'=>'Saint Helena, Ascension and Tristan da Cunha',
            'SJM'=>'Svalbard and Jan Mayen',
            'SLB'=>'Solomon Islands',
            'SLE'=>'Sierra Leone',
            'SLV'=>'El Salvador',
            'SMR'=>'San Marino',
            'SOM'=>'Somalia',
            'SPM'=>'Saint Pierre and Miquelon',
            'SRB'=>'Serbia',
            'SSD'=>'South Sudan',
            'STP'=>'Sao Tome and Principe',
            'SUR'=>'Suriname',
            'SVK'=>'Slovakia',
            'SVN'=>'Slovenia',
            'SWE'=>'Sweden',
            'SWZ'=>'Eswatini',
            'SXM'=>'Sint Maarten (Dutch part)',
            'SYC'=>'Seychelles',
            'SYR'=>'Syrian Arab Republic',
            'TCA'=>'Turks and Caicos Islands',
            'TCD'=>'Chad',
            'TGO'=>'Togo',
            'THA'=>'Thailand',
            'TJK'=>'Tajikistan',
            'TKL'=>'Tokelau',
            'TKM'=>'Turkmenistan',
            'TLS'=>'Timor-Leste',
            'TON'=>'Tonga',
            'TTO'=>'Trinidad and Tobago',
            'TUN'=>'Tunisia',
            'TUR'=>'Türkiye',
            'TUV'=>'Tuvalu',
            'TWN'=>'Taiwan, Province of China',
            'TZA'=>'Tanzania, United Republic of',
            'UGA'=>'Uganda',
            'UKR'=>'Ukraine',
            'UMI'=>'United States Minor Outlying Islands',
            'URY'=>'Uruguay',
            'USA'=>'United States of America',
            'UZB'=>'Uzbekistan',
            'VAT'=>'Holy See',
            'VCT'=>'Saint Vincent and the Grenadines',
            'VEN'=>'Venezuela, Bolivarian Republic of',
            'VGB'=>'Virgin Islands (British)',
            'VIR'=>'Virgin Islands (U.S.)',
            'VNM'=>'Viet Nam',
            'VUT'=>'Vanuatu',
            'WLF'=>'Wallis and Futuna',
            'WSM'=>'Samoa',
            'YEM'=>'Yemen',
            'ZAF'=>'South Africa',
            'ZMB'=>'Zambia',
            'ZWE'=>'Zimbabwe',
        ];

        $list_hire_loc = MasterLocation::where('group_type', 'hrd')->get();
        $param->hire_loc = $list_hire_loc;

        $list_work_location = MasterLocation::select('id', 'loc_code', 'loc_name')->where('id', $param->work_location_id)->limit(10)->get();
        // dd($list_work_location,$param->work_location_id);
        $param->work_location = $list_work_location;

        /**
         * formdata
         * data harus type multi array
         */
        $formdata = DB::table('master_' . $this->sheet_slug)->where('id', $id)->get();

        /*redirect jika bukan multi insert*/
        if (empty($formdata)) {
            if ($form_row_type == 'single') {
                return redirect()->route('master.' . $sheet_slug . '.index')->with('error_message', 'Data not found with id ' . $id . ' not found in database.');
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
        } else {
            abort(403, 'Gagal hapus:: ' . $modelClass . class_exists($modelClass));
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

            $import = new EmployeeImport;
            Excel::import($import, $file);
            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.employee.index')->with('error_message', $error)->with('success_message', $success);
        }
    }
}
