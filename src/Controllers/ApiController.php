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
use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Models\Department;
use Bangsamu\Master\Models\Employee;
use Bangsamu\Master\Models\Vendor;
use Bangsamu\Master\Models\Brand;
use Bangsamu\Master\Models\Apps;

use DataTables;
use PDF;
use DB;
use Carbon;
use View;

class ApiController extends Controller
{
    protected $LIMIT;

    function __construct()
    {
        $this->LIMIT = 5;
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

        if ($search == '') {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->limit($this->LIMIT)->get();
        } else {
            $projects = Project::orderBy('id', 'desc')->select('id', 'project_code', 'project_name')->where('project_code', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
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
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name')->limit($this->LIMIT)->get();
        } else {
            $employees = Employee::orderBy('id', 'desc')->select('id', 'employee_name')->where('employee_name', 'like', '%' . $search . '%')->limit($this->LIMIT)->get();
        }

        $response = array();
        foreach ($employees as $employee) {
            $response[] = array(
                "id" => $employee->id,
                "text" => $employee->employee_name
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
