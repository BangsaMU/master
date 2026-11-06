<?php

namespace Bangsamu\Master\Controllers;

use Bangsamu\Master\Services\MasterScope;
use Bangsamu\Master\Services\MasterTrash;
use Bangsamu\Master\Services\MasterAccess;
use App\Http\Controllers\Controller;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\Company;
use Bangsamu\Master\Models\FileManager;
use Bangsamu\Master\Models\Gallery;
use Bangsamu\Master\Traits\DynamicFilterable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Bangsamu\Master\Services\MasterEditLink;
use Bangsamu\Master\Support\CompanyTemplate;
use Bangsamu\Master\Support\DataTableCell;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyController extends Controller
{
    use DynamicFilterable;

    /** file Company Logo dan logo header PDF template: gambar PNG/JPG maks. 2 MB (jenis dibaca dari isi file, bukan nama) */
    public const LOGO_RULES = ['image', 'mimes:png,jpg,jpeg', 'max:2048'];

    protected $readonly = false;

    protected $sheet_name = 'Company'; // nama label untuk FE

    protected $sheet_slug = 'company'; // nama routing (slug)

    protected $view_tabel_index = [
        'mc.id AS No',
        'mc.company_code AS company_code',
        'mc.company_name AS company_name',
        '"action" AS action',
    ];

    protected $view_tabel = [
        'mc.id AS id',
        'mc.brand_code AS brand_code',
        'mc.brand_description AS brand_description',
        '"action" AS action',
    ];

    public function config($id = null, $data = null)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        $data['ajax']['url_prefix'] = $sheet_slug;
        $data['page']['url_prefix'] = $sheet_slug;
        $data['page']['sheet_name'] = $sheet_name;
        $data['page']['new']['active'] = true;
        $data['page']['new']['url'] = route('master.'.$sheet_slug.'.create');

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
        $data['page']['list'] = route('master.'.$sheet_slug.'.index');
        $data['page']['title'] = $sheet_name;

        $data['tab-menu']['title'] = 'List '.$sheet_name;

        if (app(MasterAccess::class)->can('company', 'read')) {
            $data['datatable']['btn']['sync']['id'] = 'sync';
            $data['datatable']['btn']['sync']['title'] = 'Sync';
            $data['datatable']['btn']['sync']['icon'] = 'btn-warning';
            $data['datatable']['btn']['sync']['act'] = "syncFn('company,gallery')";
        }

        if (MasterEditLink::allowed('company', 'create')) {
            $data['datatable']['btn']['create'] = MasterEditLink::createButton('company');
        }
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->can('company', 'create')) {
            // company hanya ada 3 di lock ga usah add
            // $data['datatable']['btn']['create']['id'] = 'create';
            // $data['datatable']['btn']['create']['title'] = 'Create';
            // $data['datatable']['btn']['create']['icon'] = 'btn-primary';
            // $data['datatable']['btn']['create']['url'] = route('master.' . $sheet_slug . '.create');

            // $data['datatable']['btn']['import']['id'] = 'importitem';
            // $data['datatable']['btn']['import']['title'] = 'Import';
            // $data['datatable']['btn']['import']['icon'] = 'btn-primary';
            // $data['datatable']['btn']['import']['url'] = '#';
            // $data['datatable']['btn']['import']['act'] = 'importFn()';
        }

        if (app(MasterAccess::class)->can('company', 'read')) {
            $data['datatable']['btn']['export']['id'] = 'exportdata';
            $data['datatable']['btn']['export']['title'] = 'Export';
            $data['datatable']['btn']['export']['icon'] = 'btn-primary';
            $data['datatable']['btn']['export']['url'] = url('master/getmaster_company/export');
        }

        // $data['page']['import']['layout'] = 'layouts.import.form';
        // $data['page']['import']['post'] = route('company.import');
        // $data['page']['import']['template'] = url('/templates/BrandImportTemplate.xlsx');

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
            $order = 'mc.created_at';
        }
        $dir = $request->input('order.0.dir') ?? 'desc';

        $array_data_maping = $view_tabel_index;

        $tableName = 'master_company';
        $category = 'master_company';
        $settings = $this->getSettingsForTable($category);

        $query = DB::table($tableName.' as mc')
            ->tap(fn ($query) => $trash->filter($query, 'company', 'mc'));

        $this->applyDynamicFilter($query, $tableName, $settings, 'mc');

        $totalData = $query->count();

        // $totalData = DB::table('master_company as mc')->whereNull('mc.deleted_at')->count();
        $totalFiltered = $totalData;
        if ($request_columns || $search) {
            $view_tabel = $view_tabel_index;

            $data_tabel = DB::table($tableName.' as mc')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->tap(fn ($query) => $trash->filter($query, 'company', 'mc'));

            $this->applyDynamicFilter($data_tabel, $tableName, $settings, 'mc');
            $data_tabel->groupby('mc.id');

            $data_tabel = datatabelFilterQuery(compact('array_data_maping', 'data_tabel', 'view_tabel', 'request_columns', 'search', 'jml_char_nosearch', 'char_nosearch'));

            $totalFiltered = $data_tabel->get()->count();

            $data_tabel->offset($start)
                ->groupby('mc.id')
                ->orderBy($order, $dir)
                ->limit($limit)
                ->offset($start);

            $data_tabel = $data_tabel->get();
        } else {
            $datatb_request = DB::table($tableName.' as mc')
                ->select(
                    DB::raw(implode(',', $view_tabel_index)),
                )
                ->tap(fn ($query) => $trash->filter($query, 'company', 'mc'));

            $this->applyDynamicFilter($datatb_request, $tableName, $settings, 'mc');

            $datatb_request = $datatb_request->groupby('mc.id')
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

                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('company', 'update', $row)) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.edit', $row->No).'" class="btn btn-primary btn-sm">Update</a> ';
                } else {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.show', $row->No).'" class="btn btn-primary btn-sm">View</a>';
                }
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') == true && app(MasterAccess::class)->allows('company', 'delete', $row)) {
                    $btn .= '<a href="'.route('master.'.$sheet_slug.'.destroy', $row->No).'" onclick="notificationBeforeDelete(event,this)" class="btn btn-danger btn-sm">Delete</a>';
                }

                $btn .= MasterEditLink::rowButton('company', $row->No, $row);
                $nestedData['action'] = $trash->actions('company', $row, $btn);
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
        $data['page']['title'] = $sheet_name;
        $data['page']['readonly'] = $this->readonly;
        $param = null;

        // return view('master::master.company.form', compact('data', 'param'));
        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact('data', 'param'));
    }

    public function store(Request $request)
    {
        // izin modul + pemilik baris (MasterAccess); pemilik data baru/lama (MasterScope: app_code + company_id)
        $existing = app(MasterAccess::class)->authorizeStore('company', $request);
        $owner = $existing ? app(MasterScope::class)->ownerForUpdate('master_company', $existing) : app(MasterScope::class)->ownerForNew('master_company');

        $request->validate([
            'company_code' => ['required', app(MasterScope::class)->uniqueRule('master_company', 'company_code', [], $request->id)],
            'company_name' => 'required',
            'company_logo' => ['nullable', ...self::LOGO_RULES],
        ], [], ['company_logo' => 'Company Logo']);

        // File logo disimpan ke disk DULU, database belakangan. Upload gagal = pesan di form tanpa menulis apa pun ke
        // database (dulu baris master_gallery dibuat dulu demi id untuk nama file, lalu tertinggal kosong saat disk gagal).
        $logo = $request->hasFile('company_logo') ? $this->putLogoFile($request->file('company_logo'), 'company_logo') : null;

        try {
            [$company, $gallery, $companyChanged, $message] = DB::transaction(function () use ($request, $logo) {
                $fields = [
                    'company_code' => $request->company_code,
                    'company_name' => $request->company_name,
                    'company_short' => $request->company_short,
                    'company_attention' => $request->company_attention,
                    'company_address' => $request->company_address,
                ];

                if ($request->id) {
                    $company = Company::findOrFail($request->id);
                    $company->update($fields);
                    $companyChanged = $company->wasChanged();
                    $message = $this->sheet_name.($companyChanged ? ' updated successfully' : ' no data changed');
                } else {
                    $company = Company::create($fields);
                    $companyChanged = false;
                    if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                        $modelClass = LibraryClayController::resolveModelFromSheetSlug('master_'.$this->sheet_slug);
                        $modelClass::create($fields + ['created_at' => now()]);
                    }
                    $message = $this->sheet_name.' created successfully';
                }

                $gallery = null;
                if ($logo !== null) {
                    $gallery = $this->createLogoGallery($logo);
                    $oldLogoId = $company->company_logo_id;
                    $company->company_logo_id = $gallery->id;
                    $company->save();
                    $companyChanged = true;

                    // Logo lama dilepas setelah logo baru pasti tersimpan: barisnya di-soft delete, filenya dibiarkan supaya
                    // baris itu masih bisa dipulihkan (dulu file + baris lama dihapus SEBELUM upload baru dicoba).
                    if ($oldLogoId && (int) $oldLogoId !== (int) $gallery->id) {
                        Gallery::whereKey($oldLogoId)->first()?->delete();
                    }
                    $message .= ', Logo '.$this->sheet_name.' updated successfully';
                }

                return [$company, $gallery, $companyChanged, $message];
            });
        } catch (Throwable $e) {
            // database gagal: file yang telanjur tersimpan dihapus lagi supaya tidak ada file tanpa baris gallery
            if ($logo !== null) {
                $this->deleteLogoFile($logo);
            }

            throw $e;
        }

        // sinkron ke DB master setelah commit (aplikasi MASTER_DIRECT_EDIT dengan DB master terpisah)
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
            $sync_list_callback = config('AppConfig.CALLBACK_URL');
            if ($gallery !== null) {
                LibraryClayController::updateMaster(['sync_tabel' => 'master_gallery', 'sync_id' => $gallery->id, 'sync_row' => $gallery->toArray(), 'sync_list_callback' => $sync_list_callback]);
            }
            if ($companyChanged) {
                LibraryClayController::updateMaster(['sync_tabel' => 'master_'.$this->sheet_slug, 'sync_id' => $company->id, 'sync_row' => $company->toArray(), 'sync_list_callback' => $sync_list_callback]);
            }
        }

        return redirect()->route('master.'.$this->sheet_slug.'.index')->with('success_message', $message);
    }

    /**
     * Simpan template header + upload logo header PDF (Motto/ISO) per App Code Key. Hanya di aplikasi Master Data
     * (CompanyTemplate::editableHere); aplikasi lain menerima hasilnya lewat sync master_company.
     */
    public function updateTemplateJson(Request $request, $id)
    {
        abort_unless(CompanyTemplate::editableHere(), 403, 'Template company dikelola di aplikasi Master Data; ubah di sana.');

        $company = Company::findOrFail($id);

        $request->validate([
            'template_json' => 'required|json',
            'logo_pdf_key' => 'nullable|string|max:20',
            'logo_pdf_file' => 'nullable|array',
            'logo_pdf_file.*' => self::LOGO_RULES,
        ], [], [
            'logo_pdf_file.logo_2' => CompanyTemplate::LOGO_FIELDS['logo_2'],
            'logo_pdf_file.iso' => CompanyTemplate::LOGO_FIELDS['iso'],
        ]);

        $templateJson = $request->input('template_json');

        // Cek data saat ini dari database
        $currentData = json_decode($company->template_json, true) ?? [];
        $newData = json_decode($templateJson, true) ?? [];

        // Validasi hak akses edit key
        $isAdmin = checkPermission('is_admin');
        $appCode = config('SsoConfig.main.APP_CODE');

        if (! $isAdmin) {
            // Jika bukan admin, user hanya boleh mengedit key yang sesuai config main.APP_CODE
            // Jadi, pertahankan key lain, hanya timpa key main.APP_CODE dengan input yang baru
            if (isset($newData[$appCode])) {
                $currentData[$appCode] = $newData[$appCode];
            }
            $finalData = $currentData;
        } else {
            // Jika admin, user boleh menyimpan input baru (termasuk penambahan/pengurangan key)
            $finalData = $newData;
        }

        // file logo header PDF disimpan dulu, baris gallery + template ditulis dalam satu transaksi sesudahnya
        $stored = [];
        try {
            $finalData = $this->applyTemplateLogos($request, CompanyTemplate::sanitizeLogos($finalData), $isAdmin, $appCode, $stored);

            DB::transaction(function () use ($company, $finalData, $stored) {
                foreach ($stored as $logo) {
                    $this->createLogoGallery($logo);
                }
                $company->template_json = json_encode($finalData);
                $company->save();
            });
        } catch (Throwable $e) {
            // logo berikutnya atau database gagal: file yang telanjur tersimpan dihapus lagi
            foreach ($stored as $logo) {
                $this->deleteLogoFile($logo);
            }

            throw $e;
        }

        // Sync ke master db jika MASTER_DIRECT_EDIT aktif
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
            $id = $company->id;
            $sync_tabel = 'master_'.$this->sheet_slug;
            $sync_id = $id;
            $sync_row = $company->toArray();
            $sync_list_callback = config('AppConfig.CALLBACK_URL');
            LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
        }

        return redirect()->back()->with('success_message', 'Template JSON updated successfully');
    }

    /**
     * Upload gambar header PDF (logo_pdf_file[logo_2|iso]) untuk App Code Key logo_pdf_key: file ke disk master,
     * URL-nya ke template_json[key].logo_pdf. Non-admin hanya untuk key aplikasi ini (aturan sama dengan key template).
     * File yang sudah tersimpan dicatat di $stored (baris gallery dibuat pemanggil setelah semua file berhasil).
     *
     * @param  array<string, mixed>  $template
     * @param  list<array<string, mixed>>  $stored
     * @return array<string, mixed>
     */
    private function applyTemplateLogos(Request $request, array $template, bool $isAdmin, ?string $appCode, array &$stored): array
    {
        $files = array_intersect_key((array) $request->file('logo_pdf_file', []), CompanyTemplate::LOGO_FIELDS);
        if ($files === []) {
            return $template;
        }

        $key = trim((string) $request->input('logo_pdf_key'));
        if ($key === '' || ! is_array($template[$key] ?? null) || (! $isAdmin && $key !== $appCode)) {
            throw ValidationException::withMessages(['logo_pdf_key' => 'App Code Key untuk logo tidak valid; pilih key lalu upload ulang.']);
        }

        $logos = is_array($template[$key][CompanyTemplate::LOGO_KEY] ?? null) ? $template[$key][CompanyTemplate::LOGO_KEY] : [];
        foreach ($files as $field => $file) {
            if ($file instanceof UploadedFile) {
                $logo = $this->putLogoFile($file, 'logo_pdf_file.'.$field);
                $stored[] = $logo;
                $logos[$field] = $logo['url'];
            }
        }
        $template[$key][CompanyTemplate::LOGO_KEY] = $logos;

        return $template;
    }

    /**
     * Simpan satu file logo (Company Logo / logo header PDF) ke disk app.storage_disk_master SEBELUM database ditulis.
     * Nama file = nama asli (slug) + acak, tidak memakai id gallery; ekstensi dari isi file (png/jpg/jpeg), bukan dari
     * nama kiriman browser. Disk gagal (exception seperti UnableToCreateDirectory, atau putFileAs false) = pesan validasi
     * pada $field, bukan error 500, dan belum ada baris database yang ditulis.
     *
     * @return array{disk: string, path: string, filename: string, url: string, caption: string, size: int|false, header_type: ?string, file_type: string, hash_file: ?string}
     */
    private function putLogoFile(UploadedFile $file, string $field): array
    {
        $extension = strtolower((string) $file->extension());
        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([$field => 'Logo harus gambar PNG atau JPG.']);
        }

        $disk = (string) config('app.storage_disk_master');
        $path = 'company/'.$extension;
        $name = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 80, '') ?: 'logo';
        $filename = $name.'-'.Str::lower(Str::random(12)).'.'.$extension;
        $hash = $file->getRealPath() ? hash_file('sha256', $file->getRealPath()) : false;

        $url = null;
        $error = null;
        try {
            $stored = Storage::disk($disk)->putFileAs($path, $file, $filename);
            if ($stored === false || ! Storage::disk($disk)->exists($path.'/'.$filename)) {
                $error = 'putFileAs: '.json_encode($stored);
            } else {
                // aplikasi lain mengambil gambar lewat HTTP: URL disk relatif dijadikan absolut ke aplikasi ini
                $url = Storage::disk($disk)->url($path.'/'.$filename);
                if (! CompanyTemplate::isHttpUrl($url)) {
                    $url = url($url);
                }
            }
        } catch (Throwable $e) {
            $error = get_class($e).': '.$e->getMessage();
        }

        if ($error !== null) {
            $this->deleteLogoFile(['disk' => $disk, 'path' => $path, 'filename' => $filename]);
            Log::error('user: '.auth()->id().' url: '.url()->current().' message: Upload logo gagal disk='.$disk.' file='.$path.'/'.$filename.' error='.$error);

            throw ValidationException::withMessages([$field => 'Logo gagal disimpan ke penyimpanan file (folder tidak bisa ditulis atau penuh). Belum ada data yang disimpan; coba lagi atau hubungi admin.']);
        }
        Log::info('user: '.auth()->id().' url: '.url()->current().' message: Upload logo sukses disk='.$disk.' file='.$path.'/'.$filename);

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'url' => $url,
            'caption' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'header_type' => $file->getMimeType(),
            'file_type' => $extension,
            'hash_file' => $hash ?: null,
        ];
    }

    /**
     * Baris master_gallery untuk file logo yang sudah pasti tersimpan (hasil putLogoFile).
     *
     * @param  array<string, mixed>  $logo
     */
    private function createLogoGallery(array $logo): Gallery
    {
        return Gallery::create([
            'user_id' => auth()->id(),
            'caption' => $logo['caption'],
            'url' => $logo['url'],
            'path' => $logo['path'],
            'filename' => $logo['filename'],
            'size' => $logo['size'],
            'header_type' => $logo['header_type'],
            'file_group' => null,
            'hash_file' => $logo['hash_file'],
            'file_type' => $logo['file_type'],
        ]);
    }

    /**
     * Hapus file logo yang telanjur tersimpan (kompensasi bila langkah berikutnya gagal); gagal hapus hanya dicatat.
     *
     * @param  array<string, mixed>  $logo
     */
    private function deleteLogoFile(array $logo): void
    {
        try {
            Storage::disk($logo['disk'])->delete($logo['path'].'/'.$logo['filename']);
        } catch (Throwable $e) {
            Log::warning('user: '.auth()->id().' message: file logo '.$logo['path'].'/'.$logo['filename'].' tidak bisa dihapus: '.$e->getMessage());
        }
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
        $data['page']['store'] = route('master.'.$sheet_slug.'.store');
        $data['page']['title'] = $sheet_name;
        // MASTER_DIRECT_EDIT=false: data company hasil sync hub, ubahnya di aplikasi Master Data (halaman edit = hanya baca)
        $data['page']['readonly'] = $this->readonly || ! config('MasterCrudConfig.MASTER_DIRECT_EDIT');
        $param = DB::table('master_'.$this->sheet_slug.' as tbl1')
            ->leftJoin('master_gallery as tbl2', 'tbl2.id', '=', 'tbl1.company_logo_id')
            ->where('tbl1.id', $id)
            ->select(
                'tbl1.*',
                'tbl2.url as company_logo_url'
            )
            ->first();

        $appCode = config('SsoConfig.main.APP_CODE');
        // template + logo header PDF hanya diubah di aplikasi Master Data; di aplikasi lain kartu Template hanya baca
        $templateEditable = ! $this->readonly && CompanyTemplate::editableHere();
        $appNames = CompanyTemplate::appNames();
        $logoFields = CompanyTemplate::LOGO_FIELDS;
        $masterEditUrl = $param && ! $templateEditable && MasterEditLink::allowed('company', 'update', $param)
            ? MasterEditLink::url('company', $param->id)
            : null;

        $templateData = [];
        if ($param && ! empty($param->template_json)) {
            $templateData = json_decode($param->template_json, true);
        }
        if (! is_array($templateData)) {
            $templateData = [];
        }
        // contoh isi hanya untuk template baru yang bisa disimpan; hanya baca = tampilkan apa adanya (kosong)
        if (empty($templateData) && $templateEditable) {
            $key = $appCode ?: 'APP32';
            $templateData = [
                $key => [
                    'form_no' => [
                        'spb' => 'MEI-FLK-SPB-001',
                        'spj' => 'MEI-FLK-SPJ-001',
                        'spa' => 'MEI-FLK-SPA-001',
                    ],
                    'rev_no' => [
                        'spb' => '1-spb',
                        'spj' => '1-spj',
                        'spa' => '1-spa',
                    ],
                    'issued_date' => [
                        'spb' => '2026-07-09',
                        'spj' => '2026-07-10',
                        'spa' => '2026-07-11',
                    ],
                    'format_date' => 'F,Y',
                    'template_header' => 1,
                ],
            ];
        }

        // dd($param);
        // return view('company.form', compact('data', 'param'));
        return view('master::master'.config('app.themes').'.'.$this->sheet_slug.'.form', compact(
            'data', 'param', 'templateData', 'appCode', 'templateEditable', 'appNames', 'logoFields', 'masterEditUrl'
        ));
    }

    public function insertNew(Request $request, $query, $id = null)
    {

        $auto_insert = false;
        $data_config = self::config($id);
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;

        if ($auto_insert) {
            $company_code = $request->company_code;
            $company_name = $request->company_name;
            $internal_external = $request->internal_external;
            $company_start_date = $request->company_start_date;
            $company_complete_date = $request->company_complete_date;
            $company_remarks = $request->company_remarks;
            $user_id = auth()->user()->id;

            $data_insert[] = [
                'company_code' => $company_code,
                'company_name' => $company_name,
                'internal_external' => $internal_external,
                'company_start_date' => $company_start_date,
                'company_complete_date' => $company_complete_date,
                'company_remarks' => $company_remarks,
                'user_id' => $user_id,
            ];

            if (isset($data_insert)) {
                $Company = Company::insert($data_insert);
            }
            // dd($formdata);
        } else {
            $query = '
                select
                mc.id AS company_id
                ,mc.company_code AS company_code
                ,mc.company_name AS company_name
                ,mc.company_remarks AS company_remarks
                ,mc.company_start_date AS company_start_date
                ,mc.company_complete_date AS company_complete_date
                from master_company  as mp
                where mc.id="'.$id.'"
                limit 1
            ';
        }

        return DB::select($query);
    }

    public function requestDetail(Request $request, $id = null)
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

        $user_id = auth()->user()->id;
        $form_row_type = 'multi'; /* single / multi kalo single cek detail kosong redirect */
        $id = $id;
        // $indicator_method_id = 45; dinamic dari tabel hse_indicator_method
        $type = $this->sheet_name;

        $data_config = self::config($id);
        if (! is_numeric($id) && $id !== null && $id !== 'new') {
            return redirect()->route($data_config['ajax']['url_prefix'].'.index')->with('status', 'Please created/save Form request!');
        }

        $formdata = null;
        $view_form[1] = [
            'field' => 'company_code',
            'name' => 'company_code',
            'label' => 'Company Code',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
            // 'disabled' => true,
        ];
        $view_form[2] = [
            'field' => 'company_name',
            'name' => 'company_name',
            'label' => 'Name',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[3] = [
            'field' => 'company_short',
            'name' => 'company_short',
            'label' => 'Company Short',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[4] = [
            'field' => 'company_logo',
            'name' => 'company_logo',
            'label' => 'Logo',
            'type' => 'image',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[41] = [
            'field' => 'company_logo_id',
            'name' => 'company_logo_id',
            'label' => 'Logo',
            'type' => 'attachment',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[5] = [
            'field' => 'company_attention',
            'name' => 'company_attention',
            'label' => 'Attention',
            'type' => 'text',
            'col' => 'col-12 col-md-4 mc-2',
        ];
        $view_form[6] = [
            'field' => 'company_address',
            'name' => 'company_address',
            'label' => 'Address',
            'type' => 'text',
            'col' => 'col-12 col-md-12 mc-2',
        ];
        // $view_form[7] = [
        //     'field' => 'save',
        //     'name' => 'save',
        //     'label' => 'Save',
        //     'type' => 'submit',
        //     'col' => 'col-12 col-md-2 mc-2',
        // ];

        $query = '
            select
                '.implode(',', $view_tabel).'
            from master_company as mc
            left join master_file_manager mfm on mc.company_logo_id=mfm.id
            left join master_gallery mg on mg.id=mfm.wgallery_id
            where mc.id = "'.$id.'"
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
        $foreing_key = null;
        $foreing_key['id'] = $id;

        $data = self::config($id, $data);

        $view_form_list = null;
        $view_form_listDetail = [];
        // $view_form_listDetail = ProjectDetail::select('company_id', 'company_code_client', 'company_name_client', 'company_code', 'company_name', 'master_company_detail.id as view')->where('company_id', $id)
        //     ->join('master_company as mc', 'mc.id', '=', 'master_company_detail.company_id')
        //     ->join('master_company as mc', 'mc.id', '=', 'master_company_detail.company_id')
        //     ->get()->toArray();
        // dd($view_form_listDetail);
        // foreach ($view_form_listDetail as $keyPD => $valPD) {
        //     foreach ($valPD as $keyD => $valD) {

        //         // dd($view_form_listDetail, $keyPD, $valPD,$keyD,$valD);
        //         if ($keyD == 'view') {
        //             $view_form_list[$keyPD][] = [
        //                 'field' => $keyD,
        //                 'name' => $keyD,
        //                 'label' => \Str::headline($keyD),
        //                 'type' => 'button',
        //                 'col' => 'col-12 col-md mc-1',
        //                 'url' => route('master/project-detail', ['company_id' => $id, 'id' => $valD])
        //             ];
        //         } elseif (strpos('A|company_id', $keyD) === false) {
        //             $view_form_list[$keyPD][] = [
        //                 'field' => $keyD,
        //                 'name' => $keyD,
        //                 'label' => \Str::headline($keyD),
        //                 'type' => 'label',
        //                 'col' => 'col-12 col-md mc-1',
        //             ];
        //         }
        //     }
        // }
        // dd($formdata);
        $page_var = compact('data', 'foreing_key', 'formdata_multi', 'formdata', 'view_form', 'view_form_list', 'view_form_listDetail');

        // dd($page_var);
        // return view('hse-dar.' . $sheet_slug . '.request', $page_var);
        return view('master::layouts.request', $page_var);
    }

    // public function config($id = null, $data = null)
    // {
    //     $sheet_name = $this->sheet_name;
    //     $sheet_slug = $this->sheet_slug;

    //     $data['page']['folder'] = 'master';
    //     $data['ajax']['url_prefix'] = $data['page']['folder'] . '/' . $sheet_slug;
    //     $data['page']['sheet_slug'] = $sheet_slug;
    //     $data['page']['sheet_name'] = $sheet_name;
    //     $data['page']['form_number'] = @$tabel->form_number;
    //     $data['page']['file']['document'] = '.pdf,.doc,.docx,.xml,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    //     $data['page']['file']['audio'] = 'audio/*';
    //     $data['page']['file']['video'] = 'video/*';
    //     $data['page']['file']['image'] = 'image/*';

    //     // $data = configTabActionHsePlan($id, $data);

    //     $data['page']['id'] = $id;
    //     $data['modal']['view_path'] = $data['page']['folder'] . '.mastermodal';

    //     return $data;
    // }

    public function storeJson(Request $request, $json = true)
    {
        // dd($request->all());
        return self::store($request, $json);
    }

    public function uploadFile($request, $id)
    {
        $request->validate([
            'app' => 'required',
            'attachment' => 'required',
        ]);

        $app = $request->input('app');
        $id_group = $request->input('id_group');
        $attachment_type = 'action';
        $return_id = [];
        $return_name = [];
        $user_id = auth()->user()->id;

        foreach ($request->file('attachment') as $file) {
            $prefix = $file->getClientOriginalExtension();

            $caption = $file->getClientOriginalName();
            $url = null;
            $path = 'app/public/gallery/'.$prefix;
            $filename = null;
            $size = $file->getSize();
            $header_type = $file->getClientMimeType();
            $file_group = null;
            $hash_file = null;
            $file_type = $prefix;

            $Gallery = Gallery::create([
                'user_id' => $user_id,
                'caption' => $caption,
                'url' => $url,
                'path' => $path,
                'filename' => $filename,
                'size' => $size,
                'header_type' => $header_type,
                'file_group' => $file_group,
                'hash_file' => $hash_file,
                'file_type' => $file_type,
            ]);

            $filename = $Gallery->id.'-'.$file->getClientOriginalName();
            $filePath = $file->storeAs('public/gallery/'.$prefix, $filename, 'local');

            $Gallery->filename = $filename;
            $Gallery->url = config('app.url').'/storage/gallery/'.$prefix.'/'.$filename;
            $Gallery->save();

            array_push($return_id, $Gallery->id);
            array_push($return_name, $Gallery->filename);
        }

        foreach ($return_id as $wgallery_id) {
            $list_data[] = FileManager::create([
                'tfr_id' => $id,
                'tfrs_id' => $id_group,
                'app' => $app,
                'wgallery_id' => $wgallery_id,
                'user_id' => $user_id,
                'attachment_type' => $attachment_type,
            ]);
        }

        $response[] = [
            'success' => true,
            'data' => @$list_data,
            'message' => count($return_id).' Attachment berhasil di submit!',
        ];

        return $response;
    }

    public function destroy(Request $request, $id = null)
    {
        app(MasterAccess::class)->authorize('company', 'delete', $id);
        $company = Company::findOrFail($id);

        $gallery = Gallery::find($company->company_logo_id);

        if (@$gallery->url) {
            $path = str_replace(url('/storage/'), '', $gallery->url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $gallery->delete();
        }
        $company->delete(); // soft delete

        // route paket bernama master.company.* (dulu company.index yang tidak ada -> 500)
        return redirect()->route('master.'.$this->sheet_slug.'.index')->with('success', $this->sheet_slug.' deleted successfully');
    }
}
