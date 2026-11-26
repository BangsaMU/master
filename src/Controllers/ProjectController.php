<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\MasterAccess;
use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Exports\Master\ProjectTemplateExport;
// use App\Imports\Master\ProjectImport;
use Bangsamu\Master\Imports\Master\ProjectImport;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Services\ActivityLogService;
use Bangsamu\Master\Services\MasterBroadcastService;
use Bangsamu\Master\Services\MasterUniqueKeyGuard;
use Bangsamu\Master\Services\ProjectCodePrefix;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Bangsamu\Master\Traits\DynamicFilterable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\Master\Services\MasterEditLink;
use Bangsamu\Master\Support\CompanyListFilter;
use Bangsamu\Master\Support\DataTableCell;
use Bangsamu\Master\Support\ProjectListFilter;

class ProjectController extends Controller
{
    use DynamicFilterable;

    protected $readonly = false;

    protected $sheet_name = 'Master - Project'; // nama label untuk FE

    protected $sheet_slug = 'project'; // nama routing (slug)

    protected $view_tabel_index = [
        'mp.id AS No',
        // '"action" AS action',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'null AS action',
    ];

    protected $view_tabel = [
        'mp.id AS id',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'mp.project_start_date AS project_start_date',
        'mp.project_complete_date AS project_complete_date',
        'mp.project_remarks AS project_remarks',
        'null AS action',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        // parent::__construct($request);
        $this->addOwnerColumn();
    }

    /**
     * Kolom "Pemilik" (kode company pemilik project) di daftar, setelah kolom nama, bila master_project sudah punya
     * owner_company_id. Kolom biasa lewat join (bukan subquery) supaya pencarian DataTables helper app mana pun bisa
     * memakai WHERE kolom LIKE.
     */
    protected function addOwnerColumn(): void
    {
        if (! app(ProjectOwnerDetail::class)->hasOwnerColumn() || ! \Bangsamu\Master\Services\SchemaColumns::hasTable(ProjectOwnerDetail::TABLE_COMPANY)) {
            return;
        }

        foreach (['view_tabel_index', 'view_tabel'] as $property) {
            if (! in_array(self::OWNER_LIST_COLUMN, $this->{$property}, true)) {
                array_splice($this->{$property}, 3, 0, [self::OWNER_LIST_COLUMN]);
            }
        }
    }

    /** kolom daftar: kode company pemilik project (join ownerCompanyJoin) */
    public const OWNER_LIST_COLUMN = 'opc.company_code AS pemilik';

    /** daftar punya kolom "Pemilik" (master_project.owner_company_id ada) */
    protected function hasOwnerListColumn(): bool
    {
        return in_array(self::OWNER_LIST_COLUMN, $this->view_tabel_index, true);
    }

    /**
     * Join company pemilik project (alias opc) untuk kolom "Pemilik", plus id dan nama company pemilik untuk tautan
     * filter di sel Pemilik (CompanyListFilter::rowFields); tanpa kolom owner_company_id tidak menjoin.
     */
    protected function joinOwnerCompany($query): void
    {
        if (! $this->hasOwnerListColumn()) {
            return;
        }

        $ownerSql = app(ProjectOwnerDetail::class)->ownerSql('mp');
        $query->leftJoin(ProjectOwnerDetail::TABLE_COMPANY.' as opc', fn ($join) => $join->on('opc.id', '=', DB::raw($ownerSql)));
        $query->addSelect(DB::raw('opc.id AS pemilik_company_id'));
        if (\Bangsamu\Master\Services\SchemaColumns::hasColumn(ProjectOwnerDetail::TABLE_COMPANY, 'company_name')) {
            $query->addSelect(DB::raw('opc.company_name AS pemilik_company_name'));
        }
    }

    /**
     * Filter daftar dari URL/ajax: klik company (CompanyListFilter, hanya project milik company itu; pemilik kosong =
     * pemilik bawaan) dan satu project (ProjectListFilter, tautan Project Code dari daftar Project Detail).
     */
    protected function applyListFilters($query, ?array $company, ?array $project): void
    {
        if ($company !== null) {
            $query->whereRaw('('.app(ProjectOwnerDetail::class)->ownerSql('mp').') = ?', [$company['id']]);
        }
        if ($project !== null) {
            $query->where('mp.id', $project['id']);
        }
    }

    /** Tabel master_project lama (tanpa owner_company_id) di-upgrade otomatis sebelum dipakai. */
    protected function ensureSchema(): void
    {
        app(\Bangsamu\Master\Services\MasterSchemaUpgrader::class)->ensure('master_project');
    }

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['module']['folder'] = 'module';
        $data['ajax']['url_prefix'] = $data['module']['folder'].'.'.$sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.'.$sheet_slug.'.create');

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'].'.mastermodal';

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

        $api_token = LibraryClayController::api_token(null);

        $view_form['parent_id'] = [
            'field' => 'parent_id',
            'name' => 'parent_id',
            'label' => 'Parent',
            'type' => 'select2',
            'select2_minimum' => 0, // [1-5]
            'select2_tags' => false, // [true.false]
            'select2_search' => [['name']], // [field]
            'select2_url' => url('api/getmaster_mcubyparams?set[field][]=name&set[text]=name&ap_token='.$api_token.''),
            // 'select2_url' => url('api/getmaster_projectbyparams?set[id]=id&set[text]=name&ap_token=' . api_token($id) . ''),
            'multi' => false,
            'col' => 'col-12 col-md-3 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['name'] = [
            'field' => 'name',
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mb-2',
            'disabled' => $global_disable || $revisi_disable ? true : false,
        ];
        $view_form['project_id'] = [
            'field' => 'project_id',
            'name' => 'project_id',
            'label' => 'Project*',
            'type' => 'custom',
            'file' => 'components.select2-custom',
            // 'select2_minimum' => 0, //[1-5]
            // 'select2_tags' => false, //[true.false]
            // 'select2_search' => "project_code", //[field]
            'select2_search' => [['project_code'], ['|' => 'project_name']], // [field]
            // 'select2_url' => url('api/getmaster_projectbyparams?set[text]=project_code&ap_token=' . api_token($id) . ''),
            'select2_url' => url('api/getmaster_projectbyparams?set[field][]=project_code&set[field][]=project_name&set[text]=project_code&set[text][|]=project_code&set[text][]=project_name&ap_token='.$api_token.''),
            // 'multi' => false,
            'col' => 'col-12 col-md-6 mb-2',
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
        $data['page']['list'] = route('master.project.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List '.$sheet_name;

        if (app(MasterAccess::class)->can('project', 'read')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('project,company,project_detail')";
        }

        if (MasterEditLink::allowed('project', 'create')) {
            $data['datatable']['btn']['create'] = MasterEditLink::createButton('project');
        }
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->can('project', 'create')) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.'.$sheet_slug.'.create');

            $data['datatable']['btn']['import']['id'] = 'importitem';
            $data['datatable']['btn']['import']['title'] = 'Import Item';
            $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            $data['datatable']['btn']['import']['url'] = '#';
            $data['datatable']['btn']['import']['act'] = 'importFn()';
        }

        if (app(MasterAccess::class)->can('project', 'read')) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_project']);
        }

        // admin: saklar tampil/sembunyikan data terhapus (bawaan sembunyi, pilihan diingat di browser)
        if (app(MasterTrash::class)->canSeeTrashed('project')) {
            $data['datatable']['trash_toggle'] = ['default' => false, 'label' => 'Tampilkan data terhapus'];
        }

        // klik kode company di kolom Pemilik / card ringkasan = hanya project milik company itu (?company_id=)
        if ($this->hasOwnerListColumn()) {
            $data['datatable']['company_filter'] = CompanyListFilter::config($request, 'Hanya project milik', ['pemilik'], $data['page']['list']);
        }

        // satu project dari tautan Project Code daftar Project Detail (?project_id=, project terhapus + ?show_trashed=1)
        $data['datatable']['fixed_filter'] = ProjectListFilter::config($request, app(MasterTrash::class)->canSeeTrashed('project'));

        // hub: card company vs project code client + tombol "Buat Detail" untuk project tanpa detail company pemilik
        $owner = app(ProjectOwnerDetail::class);
        if ($owner->enabled() && app(MasterAccess::class)->can('project', 'read')) {
            $data['page']['top_partial'] = 'master::partials.project-owner-summary';
            $data['page']['owner_summary'] = $owner->summary();
        }

        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.import.preview', 'project');
        $data['page']['import']['label'] = 'Preview Import';
        $data['page']['import']['template'] = route('master.project.importtemplate');

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    public function dataJson(Request $request)
    {
        $trash = app(MasterTrash::class); // baris terhapus tampil untuk admin, dengan tombol Restore
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;

        $limit = strpos('A|-1||', '|'.@$request->input('length').'|') > 0 ? 10 : $request->input('length');
        $start = $request->input('start') ?? 0;

        $request_columns = $request->columns;
        $jml_char_nosearch = strlen(print_r($request_columns, true)); // 0

        $char_nosearch = 0;
        $search = $request->input('search.value');

        $id = $request->id ?? 0;

        $offest = 0;
        $user_id = Auth::user()->id ?? 0;

        if ($request->input('order.0.column')) {
            /* remove alias */
            $colom_filed = explode(' AS ', $view_tabel[$request->input('order.0.column')]);
            $order = $colom_filed[0] ?? 'id';
        } else {
            $order = 'mp.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $tableName = 'master_project';
        $category = 'master_project';
        $settings = $this->getSettingsForTable($category);

        // klik company di kolom Pemilik / card ringkasan (kolom Pemilik tidak ada = tanpa filter)
        $company = $this->hasOwnerListColumn() ? CompanyListFilter::fromRequest($request) : null;
        $project = ProjectListFilter::fromRequest($request); // satu project (tautan Project Code dari Project Detail)

        $query = DB::table($tableName.' as mp')
            ->tap(fn ($query) => $trash->filter($query, 'project', 'mp'));

        $this->applyDynamicFilter($query, $tableName, $settings, 'mp');
        $this->applyListFilters($query, $company, $project);

        $totalData = $query->count();

        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table($tableName.' as mp')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->tap(fn ($query) => $trash->filter($query, 'project', 'mp'))
                ->tap(fn ($query) => $this->joinOwnerCompany($query));

            $this->applyDynamicFilter($data_tabel, $tableName, $settings, 'mp');
            $this->applyListFilters($data_tabel, $company, $project);
            $data_tabel->groupby('mp.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mp.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start);

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table($tableName.' as mp')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->tap(fn ($query) => $trash->filter($query, 'project', 'mp'))
                ->tap(fn ($query) => $this->joinOwnerCompany($query));

            $this->applyDynamicFilter($datatb_request, $tableName, $settings, 'mp');
            $this->applyListFilters($datatb_request, $company, $project);

            $datatb_request = $datatb_request->groupby('mp.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start);

            $datatb_request = $datatb_request->get();
            $data_tabel = $datatb_request;
        }

        // $mapping_json[11] = 'action';
        foreach ($view_tabel_index as $keyC => $valC) {
            /* remove alias */
            $colom_filed = explode(' AS ', $valC);
            $c_filed = $colom_filed[1] ?? $colom_filed[0];
            $name = $mapping_json[$keyC] ?? $c_filed;
            $columnsHeader[$keyC] = $c_filed;
            $columns[$keyC] = [
                'data' => $name,
                'name' => ucwords(str_replace('_', ' ', $name)),
                'visible' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, '_id') > 0 ? false : true),
                'filter' => ($c_filed === 'app_code' || $c_filed === 'id' || strpos($c_filed, '_id') > 0 ? false : true),
            ];
        }

        $data = [];
        if (! empty($data_tabel)) {
            // project di halaman ini yang belum punya detail company pemilik (tombol "Buat Detail")
            $ownerDetail = app(ProjectOwnerDetail::class);
            $bisaBuatDetail = $ownerDetail->canCreate();
            $sudahPunyaDetail = $bisaBuatDetail
                ? array_flip($ownerDetail->projectIdsWithDetail(collect($data_tabel)->pluck('No')->all()))
                : [];

            $DT_RowIndex = $start + 1;
            foreach ($data_tabel as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {

                    /* remove alias */
                    $colom_filed = explode(' AS ', $valC);
                    $c_filed = $colom_filed[1] ?? $colom_filed[0];

                    $nestedData[$c_filed] = DataTableCell::text($row->$c_filed ?? null); // escape: sel DataTables = HTML (stored XSS)
                }
                $nestedData['No'] = $DT_RowIndex;
                // id + label company pemilik untuk tautan filter di sel Pemilik (skrip layout, CompanyListFilter)
                $nestedData = array_merge($nestedData, CompanyListFilter::rowFields(
                    $row->pemilik_company_id ?? null, $row->pemilik ?? null, $row->pemilik_company_name ?? null
                ));

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('project', 'update', $row)) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.edit', $row->No).'" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.show', $row->No).'" class="btn btn-primary btn-sm">View</a>';
                }
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('project', 'delete', $row)) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.destroy', $row->No).'" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $btn .= MasterEditLink::rowButton('project', $row->No, $row);
                if ($bisaBuatDetail && ! $trash->isTrashed($row) && ! isset($sudahPunyaDetail[(int) $row->No])) {
                    $btn .= ' <a href="'.e(route('master.project.owner-detail', $row->No)).'" onclick="notificationBeforeOwnerDetail(event,this)"'
                        .' class="btn btn-warning btn-sm" title="Project ini belum punya Project Detail untuk company pemiliknya. Klik untuk membuatnya otomatis.">'
                        .'<i class="fas fa-plus mr-1 me-1" aria-hidden="true"></i>Buat Detail</a>';
                }
                $nestedData['action'] = $trash->actions('project', $row, $btn);
                $nestedData['DT_RowClass'] = $trash->rowClass($row);

                $data[] = $nestedData;
                $DT_RowIndex++;
            }
        }

        $json_data = [
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
            'columns' => $columns,
        ];

        return response()->json($json_data);
    }

    public function create()
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.'.$sheet_slug.'.store');
        $data['page']['list'] = route('master.'.$sheet_slug.'.index');
        $data['page']['readonly'] = false;
        $data['page']['title'] = $sheet_name;
        $param = null;
        $this->ensureSchema();
        $data['page']['project_owner'] = $this->ownerFormData(null);

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    /**
     * Data bagian "Pemilik project" di form (partial master::partials.project-owner-field).
     * Create: pilihan company pemilik selain pemilik bawaan (is_admin semua company, delegasi company-nya saja).
     * Edit: pemilik sekarang + pilihan Pindah pemilik (admin Master Data).
     *
     * @return array<string, mixed>
     */
    protected function ownerFormData(?object $project): array
    {
        $service = app(ProjectOwnerDetail::class);
        $defaultLabel = $service->defaultOwnerLabel();

        if ($project === null) {
            return [
                'mode' => 'create',
                'default_label' => $defaultLabel,
                'choices' => $service->ownerChoices(),
                'checked_company' => $service->defaultOtherOwner(),
            ];
        }

        $ownerId = $service->ownerCompanyIdOf($project);
        $owner = DB::table(ProjectOwnerDetail::TABLE_COMPANY)->where('id', $ownerId)->first();

        return [
            'mode' => 'edit',
            'default_label' => $defaultLabel,
            'owner_id' => $ownerId,
            'owner_label' => $owner ? trim($owner->company_code.(isset($owner->company_name) && filled($owner->company_name) ? ' - '.$owner->company_name : '')) : '#'.$ownerId,
            'other' => $service->hasOtherOwner($project),
            'trashed' => filled($project->deleted_at ?? null),
            'transfer_url' => route('master.project.transfer-owner', $project->id),
            'transfer_choices' => filled($project->deleted_at ?? null) ? collect() : $service->transferChoices($project),
            'project_code' => (string) $project->project_code,
            'project_name' => (string) $project->project_name,
        ];
    }

    public function store(Request $request)
    {
        // kode huruf besar & kunci unik hanya boleh diubah is_admin (dengan konfirmasi)
        app(MasterUniqueKeyGuard::class)->prepare($request, 'project');

        // izin modul + pemilik baris (MasterAccess); pemilik data baru/lama (MasterScope: app_code + company_id)
        $existing = app(MasterAccess::class)->authorizeStore('project', $request);
        $owner = $existing ? app(MasterScope::class)->ownerForUpdate('master_project', $existing) : app(MasterScope::class)->ownerForNew('master_project');

        $request->validate([
            'project_code' => ['required', 'string', 'max:10', app(MasterScope::class)->uniqueRule('master_project', 'project_code', $owner, $request->id)],
            'project_name' => 'required|string|max:100',
        ]);
        app(MasterScope::class)->assertNoCompanyConflict('master_project', 'project_code', $request->input('project_code'), $owner, $request->id);

        // project baru: pemilik project (pemilik bawaan, atau company lain yang dicentang) + company baris detailnya.
        // Project lama: pemilik tidak berubah lewat form ini (hanya lewat Pindah pemilik).
        $ownerPlan = null;
        if (! $request->id) {
            $this->ensureSchema();
            $ownerDetail = app(ProjectOwnerDetail::class);
            $chosen = $request->boolean(ProjectOwnerDetail::FIELD_OTHER_OWNER) ? (int) $request->input(ProjectOwnerDetail::FIELD_OWNER_COMPANY) : null;
            if ($request->boolean(ProjectOwnerDetail::FIELD_OTHER_OWNER) && $chosen <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([ProjectOwnerDetail::FIELD_OWNER_COMPANY => ['Pilih company pemilik project, atau hapus centang "Pemilik project bukan '.$ownerDetail->defaultOwnerLabel().'".']]);
            }
            $ownerPlan = $ownerDetail->newProjectPlan($chosen);
            // panjang maksimal + awalan kode per company (ME 1/2/3/8/9, MEB 7, ...): company pemilik + company pembuat (delegasi) yang
            // barisnya ikut memakai kode ini
            app(ProjectCodePrefix::class)->assertForCompanies($ownerPlan['companies'] ?? [], (string) $request->input('project_code'));
            // satu kode satu project: kode project (= kode baris pemilik) belum dipakai baris detail company mana pun
            $ownerDetail->assertCodeFree((string) $request->input('project_code'));
        }

        if ($request->id) {
            // Update existing Project
            $project = Project::findOrFail($request->id);
            $kodeLama = (string) $project->project_code;
            // kode baru: panjang maksimal + awalan company pemilik project; kode tetap = tidak dicek (data lama)
            app(ProjectCodePrefix::class)->assertChanged(app(ProjectOwnerDetail::class)->ownerCompanyIdOf($project), (string) $request->project_code, $kodeLama);
            // kode baru (ikut dicerminkan ke baris pemilik) belum dipakai project lain; kode tetap = tidak dicek
            app(ProjectOwnerDetail::class)->assertChangedCodeFree((int) $project->id, (string) $request->project_code, $kodeLama, 'project_code');
            $update = $project->update(app(MasterScope::class)->stamp('master_project', [
                'project_code' => $request->project_code,
                'project_name' => $request->project_name,
                'internal_external' => $request->internal_external,
                'project_remarks' => $request->project_remarks,
                'project_start_date' => $request->project_start_date,
                'project_complete_date' => $request->project_complete_date,
            ], $owner));

            if ($update && $project->wasChanged()) {
                // cermin 1:1: baris detail company pemilik yang kodenya = kode lama ikut kode/nama baru
                if ($project->wasChanged(['project_code', 'project_name'])) {
                    app(ProjectOwnerDetail::class)->mirror($project, $kodeLama);
                }

                /* sync callback */
                $id = $project->id;
                $sync_tabel = 'master_'.$this->sheet_slug;
                $sync_id = $id;
                $sync_row = $project->toArray();
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                // update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message = $this->sheet_name.' updated successfully';
            } else {
                $message = $this->sheet_name.' no data changed';
            }
        } else {
            // Create new Project + baris detail dalam satu transaksi
            DB::transaction(function () use ($request, $owner, $ownerPlan) {
                $values = [
                    'project_code' => $request->project_code,
                    'project_name' => $request->project_name,
                    'internal_external' => $request->internal_external,
                    'project_remarks' => $request->project_remarks,
                    'project_start_date' => $request->project_start_date,
                    'project_complete_date' => $request->project_complete_date,
                ];
                // pemilik project ditulis eksplisit (juga pemilik bawaan) bila kolomnya sudah ada
                if (app(ProjectOwnerDetail::class)->hasOwnerColumn() && ($ownerPlan['owner'] ?? null) !== null) {
                    $values[ProjectOwnerDetail::OWNER_COLUMN] = (int) $ownerPlan['owner'];
                }
                $project = Project::create(app(MasterScope::class)->stamp('master_project', $values, $owner));

                // auto add project detail: baris company pemilik project, lalu baris company pembuat bila project milik
                // pemilik bawaan dibuat lewat session delegasi company lain (ProjectOwnerDetail::newProjectPlan)
                foreach (app(ProjectOwnerDetail::class)->rowsForNewProject($project, $ownerPlan['companies'] ?? null) as $detailRow) {
                    $modelClass = LibraryClayController::resolveModelFromSheetSlug('project_detail');
                    $data = app(MasterScope::class)->stamp('master_project', $detailRow, $owner);
                    // company_id di tabel ini = company client, bukan pemilik data
                    $data['company_id'] = $detailRow['company_id'];
                    $modelClass::create($data); // ini akan trigger Loggable
                }
            });

            // DB::table('master_project_detail')->insert([
            //     'project_id' => $project->id,
            //     'project_code_client' => $request->project_code,
            //     'project_name_client' => $request->project_name,
            //     'company_id' => $company->id,
            // ]);

            $message = $this->sheet_name.' created successfully';
        }

        return redirect()->route('master.'.$this->sheet_slug.'.index')->with('success_message', $message);
    }

    public function show($id)
    {
        $this->readonly = true;

        return self::edit($id);
    }

    public function edit($id)
    {
        $form_row_type = 'multi'; /* single / multi kalo single cek detail kosong redirect */
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.'.$sheet_slug.'.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $this->ensureSchema();
        $param = DB::table('master_'.$this->sheet_slug)->where('id', $id)->first();
        // id tidak ada (data dihapus permanen / salah ketik di URL) = 404, bukan 500 atau form Create kosong
        abort_if(! $param, 404, $this->sheet_name.' dengan id '.$id.' tidak ditemukan.');
        // baris di luar pemilik user (lain aplikasi/company) hanya bisa dilihat
        if ($param && ! app(MasterAccess::class)->allows('project', 'update', $param)) {
            $data['page']['readonly'] = true;
        }
        if ($param) {
            $data['page']['project_owner'] = $this->ownerFormData($param);
        }

        /**
         * formdata
         * data harus type multi array
         */
        $formdata = DB::table('master_'.$this->sheet_slug)->where('id', $id)->get();

        // dulu empty($formdata) pada Collection selalu false lalu $formdata[0] = 500; id tidak ada sudah 404 di atas
        abort_if($formdata->isEmpty(), 404, $this->sheet_name.' dengan id '.$id.' tidak ditemukan.');

        $formdata_multi = $formdata;
        $formdata = $formdata[0];
        /**
         * foreing_key buat input type hiden
         */
        $foreing_key['id'] = $id;

        $view_form = self::view_form(compact('id'));

        $view_form_list = null;
        $view_form_listDetail = ProjectDetail::select('project_id', 'project_code_client', 'project_name_client', 'company_code', 'company_name', 'master_project_detail.id as view')->where('project_id', $id)
            ->join('master_project as mp', 'mp.id', '=', 'master_project_detail.project_id')
            ->join('master_company as mc', 'mc.id', '=', 'master_project_detail.company_id')
            ->get()->toArray();
        // dd($view_form_listDetail);
        foreach ($view_form_listDetail as $keyPD => $valPD) {
            foreach ($valPD as $keyD => $valD) {

                // dd($view_form_listDetail, $keyPD, $valPD,$keyD,$valD);
                if ($keyD == 'view') {
                    $view_form_list[$keyPD][] = [
                        'field' => $keyD,
                        'name' => $keyD,
                        'label' => Str::headline($keyD),
                        'type' => 'button',
                        'col' => 'col-12 col-md mb-1',
                        'url' => route($this->readonly ? 'master.project-detail.show' : 'master.project-detail.edit', $valD),
                    ];
                } elseif (strpos('A|project_id', $keyD) === false) {
                    $view_form_list[$keyPD][] = [
                        'field' => $keyD,
                        'name' => $keyD,
                        'label' => Str::headline($keyD),
                        'type' => 'label',
                        'col' => 'col-12 col-md mb-1',
                    ];
                }
            }
        }

        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');

        // return view('master::layouts.dashboard.request', $page_var);
        $data['page']['logs'] = app(ActivityLogService::class)->forSlug('project', $id);

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param', 'view_form_list', 'view_form_listDetail'));
    }

    public function destroy($id)
    {
        app(MasterAccess::class)->authorize('project', 'delete', $id);
        // DB::table('master_' . $this->sheet_slug)->where('id', $id)->delete();
        $modelClass = 'Bangsamu\\Master\\Models\\Master'.Str::studly($this->sheet_slug);

        if (class_exists($modelClass)) {
            $modelClass::findOrFail($id)->delete(); // akan melakukan soft delete
        } else {
            abort(403, 'Gagal hapus:: '.$modelClass.class_exists($modelClass));
        }

        return redirect()->route('master.'.$this->sheet_slug.'.index')->with('success', $this->sheet_slug.' deleted successfully');
    }

    /**
     * Buat otomatis Project Detail company pemilik (1 project = 1 detail Meindo) untuk project yang belum punya.
     * Hanya hub; izin tambah Project Detail (admin selalu). Idempoten, detail terhapus dipulihkan bukan digandakan.
     */
    public function createOwnerDetail(string $id)
    {
        $service = app(ProjectOwnerDetail::class);
        abort_unless($service->enabled(), 404);
        app(MasterAccess::class)->authorize('project-detail', 'create');

        $project = DB::table('master_project')->where('id', $id)->first() ?? abort(404);
        $label = 'Project '.e($project->project_code);

        try {
            $result = $service->ensure($project);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('master.project.index')->with('error_message', e($e->validator->errors()->first()));
        }

        $pesan = match ($result['status']) {
            'created' => $label.': Project Detail company pemilik berhasil dibuat.',
            'restored' => $label.': Project Detail company pemilik yang terhapus dipulihkan.',
            default => $label.' sudah punya Project Detail company pemilik, tidak ada yang dibuat.',
        };

        return redirect()->route('master.project.index')->with('success_message', $pesan);
    }

    /**
     * Pindah pemilik project (Edit Project → Pindah pemilik): hanya admin Master Data di hub. Pemilik baru memakai
     * baris detail company itu (dibuat/dipulihkan bila belum ada); kode/nama project mengikuti baris itu; baris
     * pemilik lama tetap ada (ProjectOwnerDetail::transferOwner).
     */
    public function transferOwner(Request $request, string $id)
    {
        $service = app(ProjectOwnerDetail::class);
        abort_unless($service->enabled(), 404);
        abort_unless(app(MasterAccess::class)->isAdmin(), 403);
        $this->ensureSchema();

        $request->validate([
            ProjectOwnerDetail::FIELD_TRANSFER_COMPANY => ['required', 'integer', 'min:1'],
            'confirm_transfer' => ['accepted'],
        ], [
            'confirm_transfer.accepted' => 'Konfirmasi pindah pemilik belum diberikan.',
        ]);

        $result = $service->transferOwner((int) $id, (int) $request->input(ProjectOwnerDetail::FIELD_TRANSFER_COMPANY));

        $pesan = 'Pemilik project dipindah dari '.e($service->companyLabel($result['from'])).' ke '.e($service->companyLabel($result['to'])).'.';
        if ($result['code_before'] !== $result['code_after']) {
            $pesan .= ' Project Code berubah '.e($result['code_before']).' → '.e($result['code_after']).' (mengikuti baris company pemilik baru).';
        }
        $pesan .= match ($result['row']) {
            'created' => ' Baris Project Detail company pemilik baru dibuat dari kode/nama project.',
            'restored' => ' Baris Project Detail company pemilik baru yang terhapus dipulihkan.',
            default => '',
        };
        $pesan .= ' Baris company pemilik lama tetap ada; hapus di Project Detail bila company itu tidak memakai project ini lagi.';

        return redirect()->route('master.project.edit', $id)->with('success_message', $pesan);
    }

    public function import(Request $request)
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => 'required|file|max:20480|mimes:xls,xlsx,txt,csv',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $import = new ProjectImport;

            MasterBroadcastService::withoutBroadcasting(function () use ($import, $file) {
                Excel::import($import, $file);
            });

            // Ensure single batch summary broadcast is dispatched to Senada
            $import->sendBatchBroadcastSummary();

            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.project.index')->with('error_message', $error)->with('success_message', $success);
        }
    }

    public function importtemplate()
    {
        return Excel::download(new ProjectTemplateExport, 'ProjectTemplateImport.xlsx');
    }
}
