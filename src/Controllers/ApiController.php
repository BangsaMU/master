<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\FileManager;
use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\UoM;
use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Models\Department;
use Bangsamu\Master\Models\Employee;
use Bangsamu\Master\Models\Vendor;
use Bangsamu\Master\Models\Brand;
use Bangsamu\Master\Models\Apps;
use Bangsamu\Master\Models\Company;

use DataTables;
use PDF;
use DB;
use Carbon;
use View;
use Illuminate\Support\Facades\Schema;

class ApiController extends Controller
{
    protected $LIMIT;

    function __construct()
    {
        $this->LIMIT = 5;
    }

    /**
     * @param id = primary key filter by id
     * @param set[text] = return replace text dari filed apa
     * @param set[field][] = array fild yg akan direturn custom
     * @param set[field]=* retrun semua filed
     * @param start= set awal pencarian record jika tidak ada 0
     * @param limit= set maksimal record yang ditampilkan jika tidak ada 10
     * @param search= data string, akan melakukan pencarian di semua kolom (filed id dihiraukan) sesuai yg di select dari param set[field] dengan where like
     * @param search[key]=val data array, akan melakukan pencarian di filed search[key] dengan orwhere =
     */
    public function getTabelByParams(Request $request, $tabel)
    {
        // contoh select2 http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?set[text]=description&search[type]=vehicle&search[description]=PJP
        // contoh search http://meindo-teliti.test:8181/api/gethse_indicator_detailbyparams?&set[field][]=type&limit=13&start=0&search[type]=vehicle&search[indicator_method_id]=4&search[type]=samu
        // contoh by id http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?id=45&set[text]=type&set[field][]=description&set[field][]=id
        // contoh get list http://meindo-teliti.test:8181/api/gethse_indicator_methodbyparams?set[text]=type&set[field][]=description&set[field][]=id
        /*Param serch support array dan string data yg diambil param terakhir */
        $search = $request->search;

        $set = $request->input("set");
        $start = $request->input("start", 0);
        $limit = $request->input("limit", 10);
        // dd($set,$start,$limit);
        // $field = $request->set['field'];
        $id = $request->id;
        // dd($field);
        $data_lokal =  DB::table($tabel);
        $key = md5($id . ':' . config('SsoConfig.main.KEY'));
        // $token = $key == $request->input('api_token');
        $token = true; //bypass test
        $select[] = "id";


        if ($set) {
            $text = @$set['text'];
            $field = @$set['field'];

            if ($field == '*') {
                // dd($field);
                $select[] = "$tabel.*";
            } else {
                // dd($field);
                if (is_array($field)) {
                    foreach ($field as $kolom) {
                        if (Schema::hasColumn($tabel, $kolom)) {
                            /*validasi kolom tabel*/
                            $select[] = "$kolom";
                        }
                    }
                }
            }

            if ($set) {
                /*validasi kolom tabel*/
                if (Schema::hasColumn($tabel, $text)) {
                    $select[] = "$text AS text";
                }
            }
        }
        $tabel_exist = Schema::hasTable($tabel);
        // dd($tabel_exist);
        if ($token && $tabel_exist) {
            $data_array = $data_lokal->select($select);
            if ($id) {
                $data_array = $data_array->where('id', $id);
            } else {
                /*buang array by value*/
                $del_val = 'id';
                if (($key = array_search($del_val, $select)) !== false) {
                    unset($select[$key]);
                }
                foreach ($select as $filter_kolom_as) {

                    if ($field != '*') {
                        // dd($select);
                        $extrac_kolom = explode(' AS ', $filter_kolom_as);
                        $filter_kolom = $extrac_kolom[0];
                        if (is_array($search)) {
                            foreach ($search as $keyFiled => $searchVal) {
                                if (Schema::hasColumn($tabel, $keyFiled)) {
                                    // dd($keyFiled, $select,!in_array($keyFiled, $select));
                                    if (!in_array($keyFiled, $select)) {
                                        // if (in_array($keyFiled, $select) and $filter_kolom != $keyFiled) {
                                        $data_array = $data_array->where($keyFiled, 'like', '%' . $searchVal . '%');
                                    } else {
                                        $data_array = $data_array->where($filter_kolom, '=', $searchVal);
                                    }
                                }
                            }
                        } elseif ($search) {
                            $data_array = $data_array->where($filter_kolom, 'like', '%' . $search . '%');
                        }
                    }
                }
                // dd($data_array->toSql());
            }
            $data_array = $data_array->offset($start)->limit($limit)->get()->toArray();
            $respon = $data_array;
        } else {
            $respon = ['Data Not Found'];
        }

        return response()->json($respon);
    }

    public function getCompanyByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $company = Company::orderBy('id', 'desc')->select('id', 'company_code', 'company_name')->limit($this->LIMIT)->get();
        } else {
            $company = Company::orderBy('id', 'desc')->select('id', 'company_code', 'company_name')->where('company_code', 'like', '%' . $search . '%')->orwhere('company_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($company as $item) {
            $response[] = array(
                "id" => $item->id,
                "text" => $item->company_code . ' - ' . $item->company_name
            );
        }

        return response()->json($response);
    }

    public function getItemCodeByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $item_codes = ItemCode::orderBy('id', 'desc')->select('id', 'item_code')->limit($this->LIMIT)->get();
        } else {
            $item_codes = ItemCode::orderBy('id', 'desc')->select('id', 'item_code')->where('item_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($item_codes as $item) {
            $response[] = array(
                "id" => $item->id,
                "text" => $item->item_code
            );
        }

        return response()->json($response);
    }

    public function getAppsByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $apps = Apps::orderBy('id', 'asc')->select('id', 'app_code', 'name')->limit($this->LIMIT)->get();
        } else {
            $apps = Apps::orderBy('id', 'asc')->select('id', 'app_code', 'name')->orwhere('name', 'like', '%' . $search . '%')->orwhere('app_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($apps as $app) {
            $app_name =  $app->name ? ': ' . $app->name : '';
            $response[] = array(
                "id" => $app->id,
                "code" => $app->app_code,
                "text" => $app->app_code . $app_name
            );
        }

        return response()->json($response);
    }

    public function getUomByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $uoms = UoM::orderBy('id', 'desc')->select('id', 'uom_code', 'uom_name')->limit($this->LIMIT)->get();
        } else {
            $uoms = UoM::orderBy('id', 'desc')->select('id', 'uom_code', 'uom_name')->orwhere('uom_code', 'like', '%' . $search . '%')->orwhere('uom_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($uoms as $uom) {
            $uom_name =  $uom->uom_name ? ': ' . $uom->uom_name : '';
            $response[] = array(
                "id" => $uom->id,
                "code" => $uom->uom_code,
                "text" => $uom->uom_code . $uom_name
            );
        }

        return response()->json($response);
    }

    public function getPcaByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $pcas = Pca::orderBy('id', 'desc')->select('id', 'pca_code')->limit($this->LIMIT)->get();
        } else {
            $pcas = Pca::orderBy('id', 'desc')->select('id', 'pca_code')->where('pca_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($pcas as $pca) {
            $response[] = array(
                "id" => $pca->id,
                "text" => $pca->pca_code
            );
        }

        return response()->json($response);
    }

    public function getCategoryByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $categories = Category::orderBy('id', 'desc')->select('id', 'category_name')->limit($this->LIMIT)->get();
        } else {
            $categories = Category::orderBy('id', 'desc')->select('id', 'category_name')->where('category_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($categories as $category) {
            $response[] = array(
                "id" => $category->id,
                "text" => $category->category_name
            );
        }

        return response()->json($response);
    }

    public function getItemGroupByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $itemgroups = ItemGroup::orderBy('id', 'desc')->select('id', 'item_group_code')->limit($this->LIMIT)->get();
        } else {
            $itemgroups = ItemGroup::orderBy('id', 'desc')->select('id', 'item_group_code')->where('item_group_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($itemgroups as $item_group) {
            $response[] = array(
                "id" => $item_group->id,
                "text" => $item_group->item_group_code
            );
        }

        return response()->json($response);
    }

    public function getProjectByParams(Request $request)
    {
        $search = $request->search;
        if($search){
            $search = strip_tags($search);
            $search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8', false);
        }

        if ($search == '') {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->limit($this->LIMIT)->get();
        } else {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->orwhere('project_name', 'like', '%' . $search . '%')->orwhere('project_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($projects as $project) {
            $response[] = array(
                "id" => $project->id,
                "text" => $project->project_code . ' - ' . $project->project_name
            );
        }

        return response()->json($response);
    }

    public function getDepartmentByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $departments = Department::orderBy('id', 'desc')->select('id', 'department_code')->limit($this->LIMIT)->get();
        } else {
            $departments = Department::orderBy('id', 'desc')->select('id', 'department_code')->where('department_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($departments as $department) {
            $response[] = array(
                "id" => $department->id,
                "text" => $department->department_code
            );
        }

        return response()->json($response);
    }

    public function getLocationByParams(Request $request)
    {
        $search = $request->search;
        $group_type = $request->group_type ?? 'office';

        $locations = Location::select('id', 'loc_code', 'loc_name');
        $locations->where(function ($q) use ($group_type) {
            $q->where('group_type', '=', $group_type);
        });

        if ($search) {
            $locations->where(function ($q) use ($search) {
                $q->orwhere('loc_code', 'like', '%' . $search . '%')->orwhere('loc_name', 'like', '%' . $search . '%');
            });
        }

        $locations = $locations->orderBy('id', 'desc')->limit($this->LIMIT)->get();

        $response = array();
        foreach ($locations as $location) {
            $response[] = array(
                "id" => $location->id,
                "text" => ($location->loc_code ? $location->loc_code . ' - ' : '') . $location->loc_name
            );
        }

        return response()->json($response);
    }

    public function getEmployeeByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name', 'employee_job_title')->limit($this->LIMIT)->get();
        } else {
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name', 'employee_job_title')->where('employee_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($employees as $employee) {
            $response[] = array(
                "id" => $employee->id,
                "text" => $employee->employee_name,
                "position" => $employee->employee_job_title,
            );
        }

        return response()->json($response);
    }

    public function getBrandByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $brands = Brand::orderBy('id', 'desc')->select('id', 'brand_code')->limit($this->LIMIT)->get();
        } else {
            $brands = Brand::orderBy('id', 'desc')->select('id', 'brand_code')->where('brand_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($brands as $brand) {
            $response[] = array(
                "id" => $brand->id,
                "text" => $brand->brand_code
            );
        }

        return response()->json($response);
    }

    public function getVendorByParams(Request $request)
    {
        $search = $request->search;

        if ($search == '') {
            $vendors = Vendor::orderBy('id', 'desc')->select('id', 'vendor_code')->limit($this->LIMIT)->get();
        } else {
            $vendors = Vendor::orderBy('id', 'desc')->select('id', 'vendor_code')->where('vendor_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($vendors as $vendor) {
            $response[] = array(
                "id" => $vendor->id,
                "text" => $vendor->vendor_code
            );
        }

        return response()->json($response);
    }
}
