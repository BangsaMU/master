<?php

namespace Bangsamu\Master\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

use App\Http\Controllers\Module\Procurement\ProcurementController;

use Illuminate\Support\Collection;

use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;
use App\Models\Requisition; // Pastikan model Requisition sudah ada
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProcurementExport implements WithMultipleSheets
{
    protected $request;

    function __construct($request)
    {
        $this->request = $request;
    }
    public function sheets(): array
    {
        return [
            new ProcurementList($this->request),
        ];
    }
}

class ProcurementList implements ShouldAutoSize, FromCollection, WithHeadings, withStyles, WithTitle, WithColumnFormatting
{
    protected $request;
    protected $view_tabel_index = array(
        'r.id AS No',
        'r.requisition_date AS requisition_date',
        'r.submit_date AS submit_date',
        't.name AS type',
        'ml.loc_name AS spb_source',
        'mp.project_code AS project_code',
        'md.department_code AS department_code',
        // '"action" AS action',
        'r.status AS requisition',
        // DB::raw('concat(ro.routing_number," ") AS routing_number'),
        'concat(ro.routing_number," ") AS routing_number',
        // 'ro.routing_number AS routing_number',
        // 'r.id AS id',
        // 'r.pdf_id AS pdf_id',
        'r.code_number AS request_number',
        'r.version AS version',
        'r.subject AS subject',
        'mp2.priority_name AS priority',
        'mic.item_code AS item_code',
        'mic.item_name AS item_name',
        'muom.uom_name AS uom',
        'rd.qty AS qty',
        'rd.remark AS remark',
        'rd.full_spec AS scope_of_work',
        'rd.date_required AS date_required',
        'ml2.loc_name AS work_location',
        'r.label_originator AS requestor',
        'mpca.pca_code AS pca_code',
        'mig.item_group_code AS item_group_code',
        'ro.action_date AS last_action_date',
        // DB::raw('concat(ro.label_email, "|", COALESCE(REPLACE(ro.comment, \'<hr>\', \'|\'), "")) AS last_approver'),
        // 'concat(ro.label_email, "|", COALESCE(REPLACE(ro.comment, \'<hr>\', \'|\'), "")) AS last_approver',
        'ro.label_email AS last_approver',
        'Replace(ro.comment, \'<hr>\', \'|\') AS comment',
        // 'ro.label_email AS last_approver',
        'ro.status AS approval_status'
    );

    function __construct($request)
    {
        $this->request = $request;
    }
    public function title(): string
    {
        return 'Report requisition Corporate';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        $view_tabel_index = $this->view_tabel_index;

        foreach ($view_tabel_index as $keyC => $valC) {
            $colom_filed = explode(" AS ", $valC);
            $name = $colom_filed[1] ?? $colom_filed[0];
            $return[] = str_replace('_', ' ', $name);
        }
        return $return;
        // return [
        //     'No',
        //     'Submit date',
        //     // 'Action',
        //     'Requisition',
        //     'Routing number',
        //     // 'id',
        //     // 'pdf id',
        //     'Request number',
        //     'Version',
        //     'Subject',
        //     'Requestor',
        //     'Last approver',
        //     'Approval status',
        // ];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $email = Auth::user()->email;
        $exampleController = new ProcurementController($this->request);
        $protectedValue = $exampleController->config();
        $view_tabel_index = $this->view_tabel_index;
        $request = $this->request;
        $columns =  $request->columns;
        $datas = DB::table('requisition as r')
            ->select(
                DB::raw(implode(',', $view_tabel_index)),
            )
            ->leftJoin('routing AS ro', function ($join) {
                $join->on('ro.object_id', '=', 'r.id')
                    ->on('ro.version', '=', 'r.version')
                    ->where('ro.active', '=', 1);
            })
            ->leftJoin('requisition_detail AS rd', function ($join) {
                $join->on('rd.requisition_id', '=', 'r.id')
                    ->on('rd.version', '=', 'r.version');
            })
            ->leftJoin('terms AS t', 't.term_id', '=', 'r.expense_id')
            ->leftJoin('master_location AS ml', 'ml.id', '=', 'r.spb_source_id')
            ->leftJoin('master_location AS ml2', 'ml2.id', '=', 'r.worklocation_id')
            ->leftJoin('master_project AS mp', 'mp.id', '=', 'r.project_id')
            ->leftJoin('master_department AS md', 'md.id', '=', 'r.department_id')
            ->leftJoin('master_priority AS mp2', 'mp2.id', '=', 'rd.priority_id')
            ->leftJoin('master_item_code AS mic', 'mic.id', '=', 'rd.item_code_id')
            ->leftJoin('master_uom AS muom', 'muom.id', '=', 'mic.uom_id')
            ->leftJoin('master_pca AS mpca', 'mpca.id', '=', 'mic.pca_id')
            ->leftJoin('master_item_group AS mig', 'mig.id', '=', 'mic.group_id')
            ->whereNull('r.deleted_at')
            ->where(function ($query) use ($columns, $protectedValue) {
                if (is_array($columns)) {
                    foreach ($columns as $keyC => $valC) {
                        if (!empty($valC)) {
                            $colom_filed = explode(" AS ",  $protectedValue['view_tabel_index'][$keyC]);
                            $name = $colom_filed[0];
                            $query->where($name, $valC);
                        }
                    }
                }
            })
            ->where(function ($query) use ($email) {
                if (!checkPermission('is_admin')) {
                    $query->where('label_originator', $email);
                }
            })
            // ->groupBy('r.id')
            ->orderBy('r.created_at', 'desc')
            // ->limit(10)
            // ->offset(0)
            ->get();

        $collections = [];
        foreach ($datas as $key => $item) {

            foreach ($view_tabel_index as $keyC => $valC) {
                $colom_filed = explode(" AS ", $valC);
                $name = $colom_filed[1] ?? $colom_filed[0];
                $return[] = str_replace('_', ' ', $name);

                // dd($datas[$keyC]->{$name});
                if ($name == 'No') {
                    $collections[$key][$name] = $key + 1;
                } else {
                    $collections[$key][$name] = $datas[$key]->{$name};
                }
                // Create the collection
                // $collections[$keyC] =
                // [
                //     'ui'=>$keyC+1,
                //     'submit_date'=>$item->submit_date,
                //     // 'action'=>$item->action,
                //     'requisition'=>$item->requisition,
                //     'routing_number'=> (string)$item->routing_number,
                //     // 'id'=>$item->id,
                //     // 'pdf_id'=>$item->pdf_id,
                //     'request_number'=>$item->request_number,
                //     'version'=>$item->version,
                //     'subject'=>$item->subject,
                //     'requestor'=>$item->requestor,
                //     'last_approver'=>$item->last_approver,
                //     'approval_status'=>$item->approval_status,
                // ];
            }
        }

        return collect($collections);
    }
    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
