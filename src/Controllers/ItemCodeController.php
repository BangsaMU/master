<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\MasterAccess;
use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Exports\Master\ItemCodeExport;
use Bangsamu\Master\Exports\Master\ItemCodeTemplateExport;
use Bangsamu\Master\Imports\Master\ItemCodeImport;
use Bangsamu\Master\Models\DashboardSettings;
use Bangsamu\Master\Models\ItemCode;
use Bangsamu\Master\Models\Setting;
use Bangsamu\Master\Services\ActivityLogService;
use Bangsamu\Master\Services\MasterBroadcastService;
use Bangsamu\Master\Services\MasterUniqueKeyGuard;
use Bangsamu\Master\Traits\DynamicFilterable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Bangsamu\Master\Services\MasterEditLink;

class ItemCodeController extends Controller
{
    use DynamicFilterable;

    protected $readonly = false;

    protected $sheet_name = 'Master - Item Code'; // nama label untuk FE

    protected $sheet_slug = 'item_code'; // nama routing (slug)

    protected $view_tabel_index = [
        'mic.id AS No',
        'mic.item_code AS item_code',
        'mic.item_name AS description',
        'mu.uom_name AS uom',
        'mp.pca_name AS pca',
        'mc.category_name AS category',
        'mig.item_group_name AS item_group',
        'mic.attributes AS attributes',
        'mic.app_code AS app_code',
        'mic.company_id AS company_id',
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size_1')) AS size_1",
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.size_2')) AS size_2",
        // "JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.unit_weight')) AS unit_weight",
        '"action" AS action',
    ];

    protected $view_tabel = [
        'mic.id AS id',
        'mic.item_code AS item_code',
        'mic.item_name AS description',
        'mu.uom_name AS uom',
        'mp.pca_name AS pca',
        'mc.category_name AS category',
        'mig.item_group_name AS item_group',
        '"action" AS action',
    ];

    /**
     * Kolom daftar (alias `data` DataTables) => ekspresi SQL yang boleh dicari dan diurutkan. Cari per kolom (input di
     * bawah judul kolom) dan cari global hanya memakai daftar ini; nilai dari request tidak pernah menjadi nama kolom.
     * LIKE '%kata%' tidak bisa memakai index B-tree, jadi tiap pencarian memindai master_item_code (ribuan baris = beberapa
     * milidetik); join ke tabel referensi memakai PRIMARY (eq_ref).
     */
    protected const SEARCH_COLUMNS = [
        'item_code' => 'mic.item_code',
        'description' => 'mic.item_name',
        'uom' => 'mu.uom_name',
        'pca' => 'mp.pca_name',
        'category' => 'mc.category_name',
        'item_group' => 'mig.item_group_name',
        'attributes' => 'mic.attributes',
    ];

    /** batas kata per kotak cari (tiap kata = satu LIKE) */
    protected const SEARCH_MAX_TERMS = 5;

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
        $data['ajax']['url_prefix'] = $data['module']['folder'].'.'.$sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.item-code.create');

        $data['page']['js_list'][] = 'js.master-data';

        $data = configDefAction($id, $data);

        $data['page']['id'] = $id;
        $data['modal']['view_path'] = $data['module']['folder'].'.mastermodal';

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
        $data['page']['list'] = route('master.item-code.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List '.$sheet_name;

        // baris input cari per kolom di bawah judul kolom (kolom = 'filter' true dari dataJson, SEARCH_COLUMNS)
        $data['datatable']['column_search'] = true;

        if (app(MasterAccess::class)->can('item-code', 'read')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('category,uom,pca,item-group,item_code')";
        }

        if (MasterEditLink::allowed('item-code', 'create')) {
            $data['datatable']['btn']['create'] = MasterEditLink::createButton('item-code');
        }
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->can('item-code', 'create')) {
            $data['datatable']['btn']['create']['id'] = 'create';
            $data['datatable']['btn']['create']['title'] = 'Create';
            $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            $data['datatable']['btn']['create']['url'] = route('master.item-code.create');

            $data['datatable']['btn']['import']['id'] = 'importitem';
            $data['datatable']['btn']['import']['title'] = 'Import Item';
            $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            $data['datatable']['btn']['import']['url'] = '#';
            $data['datatable']['btn']['import']['act'] = 'importFn()';
        }

        if (app(MasterAccess::class)->can('item-code', 'read')) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = route('master.item-code.export');
        }

        $data['page']['import']['layout'] = 'layouts.import.form';
        $data['page']['import']['post'] = route('master.import.preview', 'item-code');
        $data['page']['import']['label'] = 'Preview Import';
        $data['page']['import']['template'] = route('master.item-code.importtemplate');

        $page_var = compact('data');

        $page_var['formModal'] = searchConfig($data, $view_tabel_index);

        return view('master::layouts.dashboard.index', $page_var);
    }

    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     *                                   untuk set filter dari db bisa dari setting atau dashboard_settings
     *                                   INSERT INTO `dashboard_settings`(`key`, `value`, `group`, `type`, `label`, `options`, `order`, `created_at`, `updated_at` ) VALUES ('app_code', 'APP09,APP32', 'master_item_code', 'config', 'Config Master Item Code', NULL, '0', NULL, NULL );
     *                                   INSERT INTO `setting`(`name`, `value`, `created_at`, `updated_at`, `deleted_at`, `category` ) VALUES ('app_code', 'APP09,APP32', '2026-03-03 10:38:23', NULL, NULL, 'master_item_codeX' );
     */
    public function dataJson(Request $request)
    {
        $trash = app(MasterTrash::class); // baris terhapus tampil untuk admin, dengan tombol Restore
        $view_tabel_index = $this->view_tabel_index;

        $limit = (int) $request->input('length', 10);
        $limit = $limit > 0 ? $limit : 10; // -1 (All) dan kosong = 10 seperti dulu
        $start = max(0, (int) $request->input('start', 0));

        // urutan dicocokkan lewat nama kolom DataTables ke whitelist; dulu memakai indeks $view_tabel yang lebih pendek
        // dari kolom tabel, jadi urut kolom Attributes = error SQL. Kolom No / tak dikenal = mic.id.
        $orderName = $request->input('columns.'.(int) $request->input('order.0.column', 0).'.data');
        $order = is_string($orderName) && isset(self::SEARCH_COLUMNS[$orderName]) ? self::SEARCH_COLUMNS[$orderName] : 'mic.id';
        $dir = strtolower((string) ($request->input('order.0.dir') ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        // setting aplikasi: tabel `setting` category master_item_code, fallback dashboard_settings group yang sama (docblock)
        $settings = $this->getSettingsForTable('master_item_code');

        $query = DB::table('master_item_code as mic')
            ->tap(fn ($query) => $trash->filter($query, 'item-code', 'mic'));
        // filter setting aplikasi (DynamicFilterable: format kolom lama + baris scope)
        $this->applyDynamicFilter($query, 'master_item_code', $settings, 'mic');
        $totalData = $query->count();

        $columnSearch = $this->columnSearchTerms($request);
        $globalSearch = $this->searchTerms($request->input('search.value'));

        // Join referensi lewat PRIMARY = tepat satu baris per item, jadi tanpa GROUP BY (dulu GROUP BY mic.id memaksa
        // temporary table + filesort) dan jumlah hasil cukup COUNT(*) (dulu semua baris hasil diambil ke PHP lalu dihitung).
        $totalFiltered = $totalData;
        if ($columnSearch !== [] || $globalSearch !== []) {
            $totalFiltered = $this->listQuery($trash, $settings)
                ->tap(fn ($query) => $this->applySearch($query, $columnSearch, $globalSearch))
                ->count();
        }

        $data_tabel = $this->listQuery($trash, $settings, $view_tabel_index)
            ->tap(fn ($query) => $this->applySearch($query, $columnSearch, $globalSearch))
            ->orderBy($order, $dir)
            ->when($order !== 'mic.id', fn ($query) => $query->orderBy('mic.id', $dir)) // urutan stabil antar halaman
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
                'filter' => isset(self::SEARCH_COLUMNS[$c_filed]), // kolom ber-input cari per kolom (view: column_search)
            ];

            if ($name == 'uom') {
                $columns[$keyC]['name'] = 'Unit (UoM)';
            }
        }

        $data = [];
        if (! empty($data_tabel)) {

            $DT_RowIndex = $start + 1;
            foreach ($data_tabel as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {

                    /* remove alias */
                    $colom_filed = explode(' AS ', $valC);
                    $c_filed = $colom_filed[1] ?? $colom_filed[0];

                    $nestedData[$c_filed] = @$row->$c_filed;
                }
                $nestedData['No'] = $DT_RowIndex;

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('item-code', 'update', $row)) {
                    $btn .= '<a href="'.route('master.item-code.edit', $row->No).'" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="'.route('master.item-code.show', $row->No).'" class="btn btn-primary btn-sm">View</a>';
                }
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('item-code', 'delete', $row)) {
                    $btn .= '<a href="'.route('master.item-code.destroy', $row->No).'" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $attributesArray = json_decode($nestedData['attributes'], true);

                $nestedData['attributes'] = is_array($attributesArray)
                    ? implode('<br>', array_map(function ($key, $value) {
                        $formattedKey = ucfirst(str_replace('_', ' ', $key));

                        return "$formattedKey: $value";
                    }, array_keys($attributesArray), $attributesArray))
                    : '';

                $btn .= MasterEditLink::rowButton('item-code', $row->No, $row);
                $nestedData['action'] = $trash->actions('item-code', $row, $btn);
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

    /** query daftar item code + referensi, sudah difilter data terhapus dan setting aplikasi */
    private function listQuery(MasterTrash $trash, $settings, ?array $select = null): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('master_item_code as mic');
        if ($select !== null) {
            $query->select(DB::raw(implode(',', $select))); // sebelum MasterTrash::filter, yang menambah kolom deleted_at
        }
        $query->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
            ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
            ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
            ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
            ->tap(fn ($query) => $trash->filter($query, 'item-code', 'mic'));
        $this->applyDynamicFilter($query, 'master_item_code', $settings, 'mic');

        return $query;
    }

    /**
     * Cari per kolom: setiap kata wajib ada di kolom itu, antar kolom AND.
     * Cari global: setiap kata wajib ada di salah satu kolom SEARCH_COLUMNS (termasuk nama UoM/PCA/Category/Item Group).
     *
     * @param  array<string, list<string>>  $columnSearch
     * @param  list<string>  $globalSearch
     */
    private function applySearch($query, array $columnSearch, array $globalSearch): void
    {
        foreach ($columnSearch as $column => $terms) {
            foreach ($terms as $term) {
                $this->whereContains($query, self::SEARCH_COLUMNS[$column], $term);
            }
        }

        foreach ($globalSearch as $term) {
            $query->where(function ($any) use ($term) {
                foreach (self::SEARCH_COLUMNS as $expression) {
                    $this->whereContains($any, $expression, $term, 'or');
                }
            });
        }
    }

    /** LIKE '%nilai%' dengan % dan _ ketikan user dibaca sebagai huruf biasa (ESCAPE '!' berlaku di MariaDB dan SQLite) */
    private function whereContains($query, string $expression, string $value, string $boolean = 'and'): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
        $query->whereRaw($expression." LIKE ? ESCAPE '!'", ['%'.$escaped.'%'], $boolean);
    }

    /** @return array<string, list<string>> nama kolom SEARCH_COLUMNS => kata dari columns[i][search][value] */
    private function columnSearchTerms(Request $request): array
    {
        $result = [];
        foreach ((array) $request->input('columns', []) as $column) {
            $name = is_array($column) ? ($column['data'] ?? null) : null;
            if (! is_string($name) || ! isset(self::SEARCH_COLUMNS[$name])) {
                continue;
            }
            $terms = $this->searchTerms($column['search']['value'] ?? null);
            if ($terms !== []) {
                $result[$name] = $terms;
            }
        }

        return $result;
    }

    /** @return list<string> kata pencarian dipisah spasi, unik, maksimal SEARCH_MAX_TERMS */
    private function searchTerms(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $terms = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($terms)), 0, self::SEARCH_MAX_TERMS);
    }

    public function create()
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data = self::config();
        $data['page']['type'] = $sheet_slug;
        $data['page']['slug'] = $sheet_slug;
        $data['page']['store'] = route('master.item-code.store');
        $data['page']['list'] = route('master.item-code.index');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = false;
        $data['page']['attributes'] = '{"size_1":null,"size_2":null,"unit_weight":null}';
        $param = null;

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        // kode huruf besar & kunci unik hanya boleh diubah is_admin (dengan konfirmasi)
        app(MasterUniqueKeyGuard::class)->prepare($request, 'item-code');

        // izin modul + pemilik baris (MasterAccess); pemilik data baru/lama (MasterScope: app_code + company_id)
        $existing = app(MasterAccess::class)->authorizeStore('item-code', $request);
        $owner = $existing ? app(MasterScope::class)->ownerForUpdate('master_item_code', $existing) : app(MasterScope::class)->ownerForNew('master_item_code');

        // dd($request->all());
        $request->validate([
            'item_code' => ['required', app(MasterScope::class)->uniqueRule('master_item_code', 'item_code', $owner, $request->id)],
            'item_name' => 'required',
            'uom_id' => 'required',
            // 'pca_id' => 'required',
            // 'category_id' => 'required',
            'group_id' => 'required',
        ]);
        app(MasterScope::class)->assertNoCompanyConflict('master_item_code', 'item_code', $request->input('item_code'), $owner, $request->id);
        $item_group_attributes = DB::table('master_item_group as mig')
            ->where('id', $request->group_id)
            ->select('item_group_attributes')
            ->pluck('item_group_attributes')
            ->first();

        if ($request->id) {
            // Update existing item code
            $item_code_attributes = json_decode($item_group_attributes) ?? (object) [];

            $indexI = 0;
            $attributes = $request->input('attributes');
            if ($attributes) {
                foreach ($item_code_attributes as $key => $detail) {
                    $keyIndex = array_key_exists($key, $attributes) ? array_key_exists($key, $attributes) : $indexI; // key ganti ke id karena index jika kosong
                    if (array_key_exists($keyIndex, $attributes)) {
                        // dd(2,$keyIndex,$attributes);
                        $item_code_attributes->$key = $attributes[$keyIndex];
                    }
                    $indexI++;
                }

                // dd(1,$item_code_attributes,$key,$keyIndex, $attributes,array_key_exists($keyIndex, $attributes),@$attributes[$keyIndex]);
            }
            $item_code_attributes = json_encode($item_code_attributes);

            // dd($item_group_attributes,$item_code_attributes, $attributes);

            $item_code = ItemCode::findOrFail($request->id);
            // dd($request->all());
            $update = $item_code->update(app(MasterScope::class)->stamp('master_item_code', [
                'item_code' => $request->item_code,
                'item_name' => $request->item_name,
                'uom_id' => $request->uom_id,
                'pca_id' => $request->pca_id,
                'category_id' => $request->category_id,
                'group_id' => $request->group_id,
                'attributes' => $item_code_attributes,
                'remarks' => $request->remarks,
            ], $owner));

            if ($update && $item_code->wasChanged()) {
                /* sync callback */
                $id = $item_code->id;
                $sync_tabel = 'master_'.$this->sheet_slug;
                $sync_id = $id;
                $sync_row = $item_code->toArray();
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
            // Create new Item Code

            $item_code_attributes = json_decode($item_group_attributes) ?? (object) [];
            $indexI = 0;
            $attributes = $request->input('attributes');
            // dd($item_group_attributes,$item_code_attributes,$attributes);
            if (! empty($item_code_attributes) && $attributes) {
                foreach ($item_code_attributes as $key => $detail) {
                    $item_code_attributes->$key = $attributes[$indexI];
                    $indexI++;
                }
            }
            $item_code_attributes = json_encode($item_code_attributes);

            $modelClass = LibraryClayController::resolveModelFromSheetSlug($this->sheet_slug);

            $createdItem = $modelClass::create(app(MasterScope::class)->stamp('master_item_code', [
                'item_code' => $request->item_code,
                'item_name' => $request->item_name,
                'uom_id' => $request->uom_id,
                'pca_id' => $request->pca_id,
                'category_id' => $request->category_id,
                'group_id' => $request->group_id,
                'attributes' => $item_code_attributes,
                'remarks' => $request->remarks,
                'created_at' => now(),
            ], $owner)); // ini akan trigger Loggable

            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                // Create new item code
                $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_'.$this->sheet_slug);

                $modelClass::create(app(MasterScope::class)->stamp('master_item_code', [
                    'item_code' => $request->item_code,
                    'item_name' => $request->item_name,
                    'uom_id' => $request->uom_id,
                    'pca_id' => $request->pca_id,
                    'category_id' => $request->category_id,
                    'group_id' => $request->group_id,
                    'attributes' => $item_code_attributes,
                    'created_at' => now(),
                ], $owner));
            }

            $message = $this->sheet_name.' created successfully';
        }

        return redirect()->route('master.item-code.index')->with('success_message', $message);
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
        $data['page']['store'] = route('master.item-code.store');
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = DB::table('master_'.$this->sheet_slug)->where('id', $id)->first();
        // baris di luar pemilik user (lain aplikasi/company) hanya bisa dilihat
        if ($param && ! app(MasterAccess::class)->allows('item-code', 'update', $param)) {
            $data['page']['readonly'] = true;
        }

        $param->uom_name = DB::table('master_uom')->where('id', $param->uom_id)->value('uom_name');
        $param->pca_name = DB::table('master_pca')->where('id', $param->pca_id)->value('pca_name');
        $param->category_name = DB::table('master_category')->where('id', $param->category_id)->value('category_name');
        $param->item_group_name = DB::table('master_item_group')->where('id', $param->group_id)->value('item_group_name');

        // dd($param);
        $data['page']['logs'] = app(ActivityLogService::class)->forModel(ItemCode::class, $id);

        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    public function destroy($id)
    {
        app(MasterAccess::class)->authorize('item-code', 'delete', $id);
        // DB::table('master_' . $this->sheet_slug)->where('id', $id)->delete();
        $modelClass = 'Bangsamu\\Master\\Models\\Master'.Str::studly($this->sheet_slug);

        if (class_exists($modelClass)) {
            $deletedItem = $modelClass::findOrFail($id);
            $deletedItem->delete(); // akan melakukan soft delete
        } else {
            abort(403, 'Gagal hapus:: '.$modelClass.class_exists($modelClass));
        }

        return redirect()->route('master.item-code.index')->with('success', $this->sheet_name.' deleted successfully');
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

            $import = new ItemCodeImport;
            config(['excel.transactions.handler' => 'null']);

            MasterBroadcastService::withoutBroadcasting(function () use ($import, $file) {
                Excel::import($import, $file);
            });

            // Ensure single batch summary broadcast is dispatched to Senada
            $import->sendBatchBroadcastSummary();

            $error = $import->getError();
            $success = $import->getSuccess();

            return redirect()->route('master.item-code.index')->with('error_message', $error)->with('success_message', $success);
        }
    }

    public function importtemplate()
    {
        return Excel::download(new ItemCodeTemplateExport, 'ItemCodeTemplateExport.xlsx');
    }

    public function export()
    {
        $file_name = 'Master Item Code.xlsx';

        $export = new ItemCodeExport;

        return Excel::download($export, $file_name);
    }

    public function getJsonData(Request $request)
    {
        $sheet_slug = $this->sheet_slug;
        $view_tabel_index = $this->view_tabel_index;

        $limit = strpos('A|-1||', '|'.@$request->input('length').'|') > 0 ? 10 : $request->input('length');
        $start = $request->input('start') ?? 0;
        $search = $request->input('search.value');

        if ($request->input('order.0.column')) {
            /* remove alias */
            $column_field = explode(' AS ', $view_tabel_index[$request->input('order.0.column')]);
            $order = $column_field[0] ?? 'id';
        } else {
            $order = 'mic.id';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Base query for data
        $query = DB::table('master_item_code as mic')
            ->leftJoin('master_uom as mu', 'mu.id', '=', 'mic.uom_id')
            ->leftJoin('master_pca as mp', 'mp.id', '=', 'mic.pca_id')
            ->leftJoin('master_category as mc', 'mc.id', '=', 'mic.category_id')
            ->leftJoin('master_item_group as mig', 'mig.id', '=', 'mic.group_id')
            ->select(
                DB::raw(implode(',', $view_tabel_index))
            );

        $totalRecords = DB::table('master_item_code as mic')->count();
        $totalFiltered = $totalRecords;

        if ($search) {
            $search = '%'.$search.'%';

            foreach ($view_tabel_index as $valC) {
                $column_field = explode(' AS ', $valC)[0];

                if ($column_field != '"action"') {
                    // if (strpos($column_field, 'JSON_EXTRACT') !== false) {
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.size_1')) LIKE ?", [$search]);
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.size_2')) LIKE ?", [$search]);
                    //     $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(mic.attributes, '$.unit_weight')) LIKE ?", [$search]);
                    // } else {
                    $query->orWhere($column_field, 'LIKE', $search);
                    // }
                }
            }

            $totalFiltered = $query->count();
        }

        $data_get = $query
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        foreach ($view_tabel_index as $keyC => $valC) {
            /* remove alias */
            $column_field = explode(' AS ', $valC);
            $c_filed = $column_field[1] ?? $column_field[0];
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
        if (! empty($data_get)) {

            $DT_RowIndex = $start + 1;
            foreach ($data_get as $row) {
                $btn = '';
                $nestedData['DT_RowIndex'] = $DT_RowIndex;

                foreach ($view_tabel_index as $keyC => $valC) {
                    /* remove alias */
                    $column_field = explode(' AS ', $valC);
                    $c_filed = $column_field[1] ?? $column_field[0];
                    $nestedData[$c_filed] = @$row->$c_filed;
                }
                $nestedData['item_name'] = 'WELD NECK FLANGE  WN RJ #1500 SCH/WT XXS A 105 ASME B16.5';

                $nestedData['No'] = $DT_RowIndex;

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('item-code', 'update', $row)) {
                    $btn .= '<a href="'.route('master.item-code.edit', $row->No).'" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="'.route('master.item-code.show', $row->No).'" class="btn btn-primary btn-sm">View</a>';
                }
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('item-code', 'delete', $row)) {
                    $btn .= '<a href="'.route('master.item-code.destroy', $row->No).'" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $nestedData['attributes'] = $nestedData['attributes']
                    ? implode('<br>', array_map(function ($key, $value) {
                        return "$key: $value";
                    }, array_keys(json_decode($nestedData['attributes'], true)), json_decode($nestedData['attributes'], true)))
                    : '';

                $btn .= MasterEditLink::rowButton('item-code', $row->No, $row);
                $nestedData['action'] = @$btn;

                $data[] = $nestedData;
                $DT_RowIndex++;
            }
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
            'columns' => $columns,
        ]);
    }
}
