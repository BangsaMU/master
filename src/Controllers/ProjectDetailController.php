<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Rules\MasterUnique;
use Bangsamu\Master\Services\MasterAccess;
use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Exports\Master\ProjectDetailTemplateExport;
use Bangsamu\Master\Imports\Master\ProjectDetailImport;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Services\ActivityLogService;
use Bangsamu\Master\Services\MasterBroadcastService;
use Bangsamu\Master\Services\MasterUniqueKeyGuard;
use Bangsamu\Master\Services\MasterSchemaUpgrader;
use Bangsamu\Master\Services\ProjectDetailNumberReset;
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
use Bangsamu\Master\Support\ListSearch;

class ProjectDetailController extends Controller
{
    use DynamicFilterable;

    protected $readonly = false;

    protected $sheet_name = 'Project Detail'; // nama label untuk FE

    protected $sheet_slug = 'project-detail'; // nama routing (slug)

    protected $view_tabel_index = [
        'mpd.id AS No',
        'mp.internal_external AS project_type',
        'mp.project_code AS project_code',
        'mp.project_name AS project_name',
        'mc.company_code AS company_code',
        'mc.company_name AS company_name',
        'mpd.project_code_client AS project_code_client',
        '"action" AS action',
    ];

    protected $view_tabel = [
        'mpd.id AS id',
        'mpd.loc_code AS location_code',
        'mpd.loc_name AS location_name',
        '"action" AS action',
    ];

    /**
     * Kolom daftar (alias `data` DataTables) => ekspresi SQL yang boleh dicari dan diurutkan (ListSearch). Cari per kolom
     * dan cari global memakai daftar ini, termasuk kolom join project/company: dulu helper app `datatabelFilterQuery()`
     * hanya mencari kolom master_project_detail (Project Code Client), jadi cari "21317" tidak menemukan baris company lain
     * milik project 21317. Project Type dicari sebagai teks yang tampil (Internal/External), bukan kode I/E.
     */
    protected const SEARCH_COLUMNS = [
        'project_type' => "(CASE mp.internal_external WHEN 'I' THEN 'Internal' WHEN 'E' THEN 'External' ELSE mp.internal_external END)",
        'project_code' => 'mp.project_code',
        'project_name' => 'mp.project_name',
        'company_code' => 'mc.company_code',
        'company_name' => 'mc.company_name',
        'project_code_client' => 'mpd.project_code_client',
    ];

    /** alias kolom daftar "Yearly Reset" (Ya/Tidak dari master_project_detail.number_reset_yearly) */
    public const NUMBER_RESET_LIST_ALIAS = 'yearly_reset';

    public function __construct()
    {
        $this->addNumberResetColumn();
    }

    /**
     * Kolom "Yearly Reset" (Ya/Tidak) sebelum kolom Action, bila master_project_detail sudah punya number_reset_yearly.
     * Tabel lama di-upgrade dulu (MasterSchemaUpgrader), seperti kolom Pemilik di daftar Project.
     */
    protected function addNumberResetColumn(): void
    {
        app(MasterSchemaUpgrader::class)->ensure(ProjectDetailNumberReset::TABLE);
        if (! app(ProjectDetailNumberReset::class)->available()) {
            return;
        }

        $column = app(ProjectDetailNumberReset::class)->textSql('mpd').' AS '.self::NUMBER_RESET_LIST_ALIAS;
        if (! in_array($column, $this->view_tabel_index, true)) {
            array_splice($this->view_tabel_index, count($this->view_tabel_index) - 1, 0, [$column]);
        }
    }

    /**
     * SEARCH_COLUMNS + kolom Yearly Reset bila kolomnya ada (cari per kolom "Ya"/"Tidak", urut).
     *
     * @return array<string, string>
     */
    protected function searchColumns(): array
    {
        $columns = self::SEARCH_COLUMNS;
        if (app(ProjectDetailNumberReset::class)->available()) {
            $columns[self::NUMBER_RESET_LIST_ALIAS] = app(ProjectDetailNumberReset::class)->textSql('mpd');
        }

        return $columns;
    }

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['ajax']['url_prefix'] = $sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.'.$this->sheet_slug.'.create');

        $data = configDefAction($id, $data);

        $data['page']['js_list'][] = 'js.global';
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
        $data['page']['list'] = route('master.'.$this->sheet_slug.'.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List '.$sheet_name;

        // baris input cari per kolom di bawah judul kolom (kolom = 'filter' true dari dataJson, SEARCH_COLUMNS)
        $data['datatable']['column_search'] = true;

        // klik kode/nama company di tabel atau card ringkasan = hanya baris company itu (?company_id=, cocok persis)
        $data['datatable']['company_filter'] = CompanyListFilter::config($request, 'Hanya baris company', ['company_code', 'company_name'], $data['page']['list']);

        // hub: card ringkasan baris per company + tombol filter baris bermasalah (company/project terhapus atau tidak ada)
        $owner = app(ProjectOwnerDetail::class);
        if ($owner->enabled() && (app(MasterAccess::class)->can('project-detail', 'read') || app(MasterAccess::class)->can('project', 'read'))) {
            $data['page']['top_partial'] = 'master::partials.project-detail-summary';
            $data['page']['detail_summary'] = $owner->detailSummary();
        }

        if ((app(MasterAccess::class)->can('project-detail', 'read') || app(MasterAccess::class)->can('project', 'read'))) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('project,company,project_detail')";
        }

        if (MasterEditLink::allowed('project-detail', 'create')) {
            $data['datatable']['btn']['create'] = MasterEditLink::createButton('project-detail');
        }
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (app(MasterAccess::class)->can('project-detail', 'create') || app(MasterAccess::class)->can('project', 'create'))) {
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

        if ((app(MasterAccess::class)->can('project-detail', 'read') || app(MasterAccess::class)->can('project', 'read'))) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.table.export', ['table' => 'master_project_detail']);
            // $data['datatable']['btn']['export']['url'] = url('master/getmaster_project_detail/export');
        }

        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.import.preview', 'project-detail');
        $data['page']['import']['label'] = 'Preview Import';
        $data['page']['import']['template'] = route('master.'.$sheet_slug.'.importtemplate');

        // admin: saklar tampil/sembunyikan data terhapus (bawaan sembunyi, pilihan diingat di browser)
        $data['datatable']['trash_toggle'] = app(MasterTrash::class)->toggle('project-detail');

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    public function dataJson(Request $request)
    {
        $trash = app(MasterTrash::class); // baris terhapus tampil untuk admin, dengan tombol Restore
        $sheet_slug = $this->sheet_slug;
        $view_tabel_index = $this->view_tabel_index;

        $limit = (int) $request->input('length', 10);
        $limit = $limit > 0 ? $limit : 10; // -1 (All) dan kosong = 10 seperti dulu
        $start = max(0, (int) $request->input('start', 0));

        // urutan dicocokkan lewat nama kolom DataTables ke whitelist; dulu memakai indeks $view_tabel (id, loc_code,
        // loc_name, action) yang tidak sejajar dengan kolom tabel. Kolom No / tak dikenal = waktu dibuat seperti dulu.
        $order = ListSearch::orderExpression($request, $this->searchColumns(), 'mpd.created_at');
        $dir = ListSearch::direction($request);

        // setting aplikasi: tabel `setting` / dashboard_settings category master_project_detail (DynamicFilterable)
        $settings = $this->getSettingsForTable('master_project_detail');

        // tombol filter card ringkasan: hanya baris bermasalah (company / project terhapus atau tidak ada di master)
        $problem = $request->input('masalah');
        $problem = is_string($problem) && in_array($problem, ProjectOwnerDetail::DETAIL_PROBLEMS, true) ? $problem : null;

        // klik company di tabel / card ringkasan: hanya baris company itu (CompanyListFilter, cocok persis per id)
        $companyId = CompanyListFilter::fromRequest($request)['id'] ?? null;

        // join project + company lewat PRIMARY = tepat satu baris per detail: tanpa GROUP BY, jumlah cukup COUNT(*)
        $totalData = $this->listQuery($trash, $settings, null, $problem, $companyId)->count();

        $search = ListSearch::fromRequest($request, $this->searchColumns());
        $totalFiltered = $search->isEmpty()
            ? $totalData
            : $this->listQuery($trash, $settings, null, $problem, $companyId)->tap(fn ($query) => $search->apply($query))->count();

        $data_tabel = $this->listQuery($trash, $settings, $view_tabel_index, $problem, $companyId)
            ->tap(fn ($query) => $search->apply($query))
            ->orderBy(DB::raw($order), $dir)
            ->orderBy('mpd.id', $dir) // urutan stabil antar halaman
            ->offset($start)
            ->limit($limit)
            ->get();

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
                'filter' => isset($this->searchColumns()[$c_filed]), // kolom ber-input cari per kolom (view: column_search)
            ];
        }

        $data = [];
        if (! empty($data_tabel)) {
            $bisaPindahCompany = app(ProjectOwnerDetail::class)->canMoveCompany();

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
                // id + label company untuk tautan filter di sel Company Code/Name (company tidak ada di master = tanpa tautan)
                $nestedData = array_merge($nestedData, CompanyListFilter::rowFields($row->company_ref_id, $row->company_code, $row->company_name));

                switch ($row->project_type) {
                    case 'I':
                        $nestedData['project_type'] = 'Internal';
                        break;
                    case 'E':
                        $nestedData['project_type'] = 'External';
                        break;
                }

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (app(MasterAccess::class)->allows('project-detail', 'update', $row) || app(MasterAccess::class)->allows('project', 'update', $row))) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.edit', $row->No).'" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.show', $row->No).'" class="btn btn-primary btn-sm">View</a>';
                }
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && (app(MasterAccess::class)->allows('project-detail', 'delete', $row) || app(MasterAccess::class)->allows('project', 'delete', $row))) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.destroy', $row->No).'" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $btn .= MasterEditLink::rowButton('project-detail', $row->No, $row);
                // company baris terhapus / tidak ada di master: peringatan + Pindah company (admin hub, pengecualian N2)
                if (! $trash->isTrashed($row) && ($row->company_ref_id === null || filled($row->company_deleted_at))) {
                    $btn .= $this->companyProblemAction($row, $bisaPindahCompany);
                }
                $nestedData['action'] = $trash->actions('project-detail', $row, $btn);
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

    /**
     * query daftar Project Detail + project + company, sudah difilter data terhapus, setting aplikasi, dan (opsional)
     * baris bermasalah ProjectOwnerDetail::DETAIL_PROBLEMS serta company terpilih (CompanyListFilter)
     */
    private function listQuery(MasterTrash $trash, $settings, ?array $select = null, ?string $problem = null, ?int $companyId = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('master_project_detail as mpd');
        if ($select !== null) {
            $query->select(DB::raw(implode(',', $select))); // sebelum MasterTrash::filter, yang menambah kolom deleted_at
            // status company di master untuk peringatan di kolom Action (null = tidak ada barisnya)
            $query->addSelect(DB::raw('mc.id AS company_ref_id'), DB::raw('mc.deleted_at AS company_deleted_at'));
        }
        $query->leftJoin('master_project as mp', 'mp.id', '=', 'mpd.project_id')
            ->leftJoin('master_company as mc', 'mc.id', '=', 'mpd.company_id')
            ->tap(fn ($query) => $trash->filter($query, 'project-detail', 'mpd'));
        $this->applyDynamicFilter($query, 'master_project_detail', $settings, 'mpd');
        app(ProjectOwnerDetail::class)->applyDetailProblem($query, $problem);
        if ($companyId !== null) {
            $query->where('mpd.company_id', $companyId);
        }

        return $query;
    }

    /**
     * Isi kolom Action untuk baris yang company-nya terhapus / tidak ada di master: badge peringatan, lalu tombol
     * "Pindah company" (ke card di halaman Edit) bila boleh, alasan bila tidak, atau petunjuk untuk user bukan admin.
     */
    private function companyProblemAction(object $row, bool $canMove): string
    {
        $owner = app(ProjectOwnerDetail::class);
        $label = $owner->detailCompanyProblemLabel($row->company_ref_id === null ? 'missing' : 'deleted', $row->company_code ?? null);
        $html = '<div class="mt-1"><span class="badge bg-danger badge-danger text-bg-danger" data-company-problem>'.e($label).'</span></div>';

        if (! $canMove) {
            return $html.'<div class="small text-muted">Minta admin Master Data memindahkan company baris ini.</div>';
        }

        $detail = ProjectDetail::withTrashed()->find($row->No);
        $denial = $detail !== null ? $owner->moveCompanyDenial($detail) : 'Baris Project Detail tidak ditemukan.';
        if ($denial !== null) {
            return $html.'<div class="small text-muted" data-company-problem-denial>'.e($denial).'</div>';
        }

        return $html.'<a href="'.e(route('master.project-detail.edit', $row->No).'#project-detail-move-company').'" class="btn btn-warning btn-sm mt-1"'
            .' title="Company baris ini sudah tidak ada di master: pindahkan ke company aktif (id baris dan kode client tetap).">Pindah company</a>';
    }

    public function create()
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.project-detail.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = false;
        $param = null;
        $data['page']['number_reset'] = $this->numberResetField(null);

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        // kode huruf besar & kunci unik hanya boleh diubah is_admin (dengan konfirmasi)
        app(MasterUniqueKeyGuard::class)->prepare($request, 'project-detail');

        // izin modul + pemilik baris (MasterAccess); pemilik data baru/lama (MasterScope: app_code + company_id)
        $existing = app(MasterAccess::class)->authorizeStore('project-detail', $request);
        $owner = $existing ? app(MasterScope::class)->ownerForUpdate('master_project_detail', $existing) : app(MasterScope::class)->ownerForNew('master_project_detail');

        $request->validate([
            'project_id' => 'required',
            // 'project_code_client' => 'required',
            'project_name_client' => 'required',
            'company_id' => 'required',
            // Validasi unik pada kombinasi project_id, project_code_client, dan company_id
            'project_code_client' => [
                'required',
                // MasterUnique: pesan menyebut baris pemakai kode, termasuk yang sudah dihapus
                (new MasterUnique('master_project_detail'))->where(function ($query) use ($request) {
                    return $query->where('project_id', $request->project_id)
                        ->where('company_id', $request->company_id);
                })
                    ->ignore($request->id),  // ID yang sedang di-update
            ],
        ]);

        // reset nomor tahunan: hanya admin Master Data; null = tidak diubah (ProjectDetailNumberReset)
        $numberReset = app(ProjectDetailNumberReset::class)->valueFromRequest($request);
        $numberResetValues = $numberReset === null ? [] : [ProjectDetailNumberReset::COLUMN => $numberReset];

        if ($request->id) {
            // Update existing Project Detail
            $project_detail = ProjectDetail::findOrFail($request->id);
            // project/company baris tidak bisa diganti; baris pemilik project mengikuti Project (ProjectOwnerDetail)
            app(ProjectOwnerDetail::class)->assertDetailChangeAllowed($project_detail, [
                'project_id' => $request->project_id,
                'company_id' => $request->company_id,
                'project_code_client' => strtoupper((string) $request->project_code_client),
                'project_name_client' => $request->project_name_client,
            ]);
            $update = $project_detail->update([
                'project_id' => $request->project_id,
                'project_code_client' => strtoupper($request->project_code_client),
                'project_name_client' => $request->project_name_client,
                'company_id' => $request->company_id,
            ] + $numberResetValues);

            if ($update && $project_detail->wasChanged()) {
                /* sync callback */
                $id = $project_detail->id;
                $sync_tabel = 'master_project_detail';
                $sync_id = $id;
                $sync_row = $project_detail->toArray();
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
            // Create new master_project_detail
            $modelClass = LibraryClayController::resolveModelFromSheetSlug('project_detail');

            $modelClass::create([
                'project_id' => $request->project_id,
                'project_code_client' => strtoupper($request->project_code_client),
                'project_name_client' => $request->project_name_client,
                'company_id' => $request->company_id,
            ] + $numberResetValues);

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                // Create new project detail
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_project_detail');

                $modelClass::create([
                    'project_id' => $request->project_id,
                    'project_code_client' => strtoupper($request->project_code_client),
                    'project_name_client' => $request->project_name_client,
                    'company_id' => $request->company_id,
                    'created_at' => now(),
                ] + (\Bangsamu\Master\Services\SchemaColumns::hasColumn(ProjectDetailNumberReset::TABLE, ProjectDetailNumberReset::COLUMN, 'db_master') ? $numberResetValues : []));
            }

            // DB::table('master_project_detail')->insert([
            //     'project_id' => $request->project_id,
            //     'project_code_client' => $request->project_code_client,
            //     'project_name_client' => $request->project_name_client,
            //     'company_id' => $request->company_id,
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
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.'.$this->sheet_slug.'.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_project_detail as tbl1')
            ->leftJoin('master_project as tbl2', 'tbl2.id', '=', 'tbl1.project_id')
            ->leftJoin('master_company as tbl3', 'tbl3.id', '=', 'tbl1.company_id')
            ->where('tbl1.id', $id)
            ->select(
                'tbl1.*',
                'tbl2.project_code',
                'tbl2.project_name',
                'tbl2.internal_external',
                'tbl2.project_start_date',
                'tbl2.project_complete_date',
                'tbl3.company_code',
                'tbl3.company_name',
            )
            ->first();

        // kunci form: project/company baris tidak bisa diganti; baris pemilik project juga kode/namanya (ikut Project)
        $data['page']['owner_row'] = $param !== null && app(ProjectOwnerDetail::class)->isOwnerRow($param);

        $data['page']['number_reset'] = $this->numberResetField($param);

        // company baris terhapus / tidak ada di master: card Pindah company (admin hub) atau peringatan
        $data['page']['move_company'] = $param !== null ? $this->moveCompanyData($param) : null;

        $data['page']['logs'] = app(ActivityLogService::class)->forModel(ProjectDetail::class, $id);

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    /**
     * Centang "Reset nomor tahunan" di form (partial project-detail-number-reset): null bila kolom belum ada. Baris
     * pemilik project tetap bisa diubah (kunci baris pemilik hanya kode/nama).
     *
     * @return array{value: bool, can_edit: bool}|null
     */
    protected function numberResetField(?object $param): ?array
    {
        $service = app(ProjectDetailNumberReset::class);
        if (! $service->available()) {
            return null;
        }

        return [
            'value' => (bool) old(ProjectDetailNumberReset::COLUMN, $service->isYearly($param)),
            'can_edit' => $service->canEdit() && ! $this->readonly,
        ];
    }

    /**
     * Data card "Pindah company" di halaman Edit (partial project-detail-move-company): null bila company baris masih
     * aktif atau baris sudah dihapus.
     *
     * @return array{problem: string, label: string, can_move: bool, denial: ?string, choices: \Illuminate\Support\Collection, url: string}|null
     */
    private function moveCompanyData(object $param): ?array
    {
        $owner = app(ProjectOwnerDetail::class);
        $problem = $owner->detailCompanyProblem($param);
        if ($problem === null || filled($param->deleted_at ?? null)) {
            return null;
        }

        $canMove = $owner->canMoveCompany() && ! $this->readonly;
        $denial = $canMove ? $owner->moveCompanyDenial($param) : null;

        return [
            'problem' => $problem,
            'label' => $owner->detailCompanyProblemLabel($problem, $param->company_code ?? null),
            'can_move' => $canMove,
            'denial' => $denial,
            'choices' => $canMove && $denial === null ? $owner->moveCompanyChoices($param) : collect(),
            'url' => route('master.project-detail.move-company', $param->id),
        ];
    }

    /**
     * Pindah company baris Project Detail yang company-nya terhapus / tidak ada di master (Edit Project Detail → Pindah
     * company): hanya admin Master Data di hub. Id baris, project, kode/nama client tetap (ProjectOwnerDetail::moveCompany).
     */
    public function moveCompany(Request $request, string $id)
    {
        $service = app(ProjectOwnerDetail::class);
        abort_unless($service->enabled(), 404);
        abort_unless(app(MasterAccess::class)->isAdmin(), 403);

        $request->validate([
            ProjectOwnerDetail::FIELD_MOVE_COMPANY => ['required', 'integer', 'min:1'],
            ProjectOwnerDetail::FIELD_MOVE_CONFIRM => ['accepted'],
        ], [
            ProjectOwnerDetail::FIELD_MOVE_COMPANY.'.required' => 'Pilih company tujuan.',
            ProjectOwnerDetail::FIELD_MOVE_CONFIRM.'.accepted' => 'Konfirmasi pindah company belum diberikan.',
        ]);

        $result = $service->moveCompany((int) $id, (int) $request->input(ProjectOwnerDetail::FIELD_MOVE_COMPANY));

        $asal = $result['from'] > 0 ? $service->companyLabel($result['from']) : '(kosong)';
        $pesan = 'Company baris Project Detail dipindah dari '.e($asal).' ('.($result['problem'] === 'deleted' ? 'sudah dihapus' : 'tidak ada di master')
            .') ke '.e($service->companyLabel($result['to'])).'. Id baris, Project Code Client, dan Project Name Client tetap.';

        return redirect()->route('master.project-detail.edit', $id)->with('success_message', $pesan);
    }

    public function insertNew(Request $request, $query, $id = null)
    {
        $auto_insert = false;
        $data_config = self::config($id);
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $project_id = $request->segment(3);

        if ($auto_insert) {
            $project_code_client = $request->project_code_client;
            $company_id = $request->company_id;
            $user_id = auth()->user()->id;

            if ($project_id && $project_code_client && $company_id) {
                $data_insert[] = [
                    'project_id' => $project_id,
                    'project_code_client' => $project_code_client,
                    'company_id' => $company_id,
                ];
            }

            if (isset($data_insert)) {
                $ProjectDetail = ProjectDetail::insert($data_insert);
            }
        } else {
            $query = '
                select
                mp.id AS project_id
                ,mp.project_code AS project_code
                ,mp.project_name AS project_name
                ,mp.project_remarks AS project_remarks
                ,mp.project_start_date AS project_start_date
                ,mp.project_complete_date AS project_complete_date
                from master_project  as mp
                where mp.id="'.$project_id.'"
                limit 1
            ';
        }

        return DB::select($query);
        // dd($formdata);
    }

    public function requestDetail(Request $request, $project_id, $id = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $view_tabel = $this->view_tabel;
        $view_tabel_index = $this->view_tabel_index;
        $data = self::config($id);
        $data['page']['slug'] = 'dttable_master';
        $data['page']['title'] = 'Master '.$sheet_name;
        $data['page']['sheet_name'] = $sheet_name;
        $data['tab-menu']['title'] = Str::headline('Master '.$sheet_name);

        // $data['route']['back'] = route($data['ajax']['url_prefix'] . '', []);

        $user_id = auth()->user()->id;
        $form_row_type = 'multi'; /* single / multi kalo single cek detail kosong redirect */
        $id = $id;
        // $indicator_method_id = 45; dinamic dari tabel hse_indicator_method
        $type = $this->sheet_name;

        $data_config = self::config($id);
        if (! is_numeric($id) && $id !== null && $id !== 'new') {
            return redirect()->route($data_config['ajax']['url_prefix'].'.index')->with('error_message', 'Error: Data not found');
        }
        // dd($project_id, $project_id !== 'new');
        $formdata = null;
        if ($project_id !== 'new') {
            $view_form[1] = [
                'field' => 'project_code',
                'name' => 'project_code',
                'label' => 'Project Code',
                'type' => 'label',
                'col' => 'col-12 col-md-4 mb-2',
                // 'disabled' => true,
            ];
            $view_form[2] = [
                'field' => 'project_name',
                'name' => 'project_name',
                'label' => 'Name',
                'type' => 'label',
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[3] = [
                'field' => 'internal_external',
                'name' => 'internal_external',
                'label' => 'Project Type',
                'type' => 'select',
                'readonly' => true,
                'option' => ['I' => 'Internal', 'E' => 'External'],
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[4] = [
                'field' => 'project_start_date',
                'name' => 'project_start_date',
                'label' => 'Start Date',
                'type' => 'date',
                'disabled' => true,
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[5] = [
                'field' => 'project_complete_date',
                'name' => 'project_complete_date',
                'label' => 'Compdlete Date',
                'type' => 'date',
                'disabled' => true,
                'col' => 'col-12 col-md-4 mb-2',
            ];
            $view_form[6] = [
                'field' => 'project_remarks',
                'name' => 'project_remarks',
                'label' => 'Remarks',
                'type' => 'label',
                'col' => 'col-12 col-md-12 mb-2',
            ];
        }

        if ($id === 'new') {
            $project_id_disabled = true;
        }
        $view_form[7] = [
            'field' => 'project_id',
            'name' => 'project_id',
            'label' => 'Relate to Project',
            'type' => 'select2',
            'disabled' => @$project_id_disabled,
            'col' => 'col ',
        ];

        $view_form[8] = [
            'field' => 'project_code_client',
            'name' => 'project_code_client',
            'label' => 'Project Code Client',
            'type' => 'text',
            'col' => 'col ',
        ];
        $view_form[9] = [
            'field' => 'project_name_client',
            'name' => 'project_name_client',
            'label' => 'Project Name Client',
            'type' => 'text',
            'col' => 'col ',
        ];

        $view_form[10] = [
            'field' => 'company_id',
            'name' => 'company_id',
            'label' => 'Company Id',
            'type' => 'select2',
            // 'disabled' => true,
            'col' => 'col ',
        ];

        $query = '
            select
                '.implode(',', $view_tabel).'
            from master_project_detail as mpd
            left join master_project mp on mp.id=mpd.project_id
            where mpd.id = "'.$id.'"
        ';

        if ($id || $id == 0) {

            $formdata = DB::select($query);
            // dd($query,$formdata);
            // redirect jika bukan bulti insert
            if (empty($formdata) && $form_row_type == 'single') {
                return redirect()->route($data_config['ajax']['url_prefix'].'.index')->with('error_message', 'Data not found with id '.$id.' not found in database.');
            }

            $data_config = self::config($id, (array) $formdata);
        }

        /* jika data detail kosong */
        if (empty($formdata)) {
            $insertNew = self::insertNew($request, $query, $id);
            if (empty($insertNew)) {
                $formdata = [0];
            } else {
                $formdata = $insertNew;
            }
        }

        $formdata_multi = $formdata;
        $formdata = @$formdata[0];
        $foreing_key['id'] = $id;

        $data = self::config($id, $data);
        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form');

        // dd(1,$page_var);
        return view('master::layouts.request', $page_var);
    }

    public function storeJson(Request $request, $json = true)
    {
        // dd($request->all());
        return self::store($request, $json);
    }

    public function destroy($id)
    {
        app(MasterAccess::class)->authorize('project-detail', 'delete', $id);
        // Route::resource mendaftarkan DELETE master/project-detail/{id}; dulu method ini tidak ada -> 500
        $detail = ProjectDetail::findOrFail($id);
        // baris pemilik project tidak bisa dihapus (pindah pemilik dulu, atau hapus project-nya)
        if (($denial = app(ProjectOwnerDetail::class)->detailDeleteDenial($detail)) !== null) {
            return redirect()->route('master.'.$this->sheet_slug.'.index')->with('error_message', $denial);
        }
        $detail->delete(); // soft delete

        return redirect()->route('master.'.$this->sheet_slug.'.index')->with('success', $this->sheet_slug.' deleted successfully');
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

            $import = new ProjectDetailImport;

            MasterBroadcastService::withoutBroadcasting(function () use ($import, $file) {
                Excel::import($import, $file);
            });

            // Ensure single batch summary broadcast is dispatched to Senada
            $import->sendBatchBroadcastSummary();

            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.project-detail.index')->with('error_message', $error)->with('success_message', $success);
        }
    }

    public function importtemplate()
    {
        return Excel::download(new ProjectDetailTemplateExport, 'ProjectDetailTemplateImport.xlsx');
    }
}
