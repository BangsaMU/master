<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\MasterProjectDetail;
use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\ProjectDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Aturan: setiap project punya SATU company pemilik (master_project.owner_company_id; kosong = pemilik bawaan
 * MasterCrudConfig.PROJECT_OWNER_COMPANY_ID, Meindo). Setiap project aktif wajib punya TEPAT SATU Project Detail untuk
 * company pemiliknya ("baris pemilik"), cermin 1:1 dari project (project_code_client = project_code,
 * project_name_client = project_name). Company lain ditambahkan sebagai baris berikutnya. Project yang pemiliknya
 * bukan pemilik bawaan ("project non-Meindo") tidak punya baris Meindo, dan itu bukan kekurangan. Service ini
 * - mencari project yang baris pemiliknya belum ada (tombol "Buat Detail" di daftar Project),
 * - membuat (atau memulihkan, bila detailnya terhapus) baris itu,
 * - menghitung ringkasan company vs project code client untuk card di atas daftar Project,
 * - menentukan pemilik + baris detail untuk project BARU (form, import): pemilik bawaan (+ company pembuat bila lewat
 *   session delegasi company lain), atau company lain yang dipilih (newProjectPlan),
 * - menjaga cermin saat kode/nama project berubah (mirror) serta merapikan data lama (reconcile, perintah
 *   master:project-detail-mirror),
 * - mengunci baris detail: project/company baris mana pun tidak bisa diganti, baris pemilik tidak bisa diubah atau
 *   dihapus dari halaman Project Detail (detailChangeDenial, detailDeleteDenial), dan
 * - memindahkan pemilik project (transferOwner), satu-satunya jalan mengganti pemilik.
 * Tombol, card, pilihan pemilik, pindah pemilik, dan perintah perapian hanya di hub (master-data): aplikasi klien
 * memegang salinan tabel. Rancangan: docs/rancangan-project-non-meindo.md di SPB (meb_spb).
 */
class ProjectOwnerDetail
{
    public const TABLE_PROJECT = 'master_project';

    public const TABLE_DETAIL = 'master_project_detail';

    public const TABLE_COMPANY = 'master_company';

    /** kolom pemilik project; NULL = pemilik bawaan (companyId()) */
    public const OWNER_COLUMN = 'owner_company_id';

    /**
     * field form/request: company pemilik project baru selain pemilik bawaan (kosong = pemilik bawaan). Sengaja bukan
     * `owner_company_id`: nama itu dipakai MasterScope::OWNER_COMPANY_FIELD (pemilik DATA baris tabel master).
     */
    public const FIELD_OWNER_COMPANY = 'project_owner_company_id';

    /** field form: centang "Pemilik project bukan <pemilik bawaan>" */
    public const FIELD_OTHER_OWNER = 'project_owner_other';

    /** field form Pindah pemilik: company pemilik baru */
    public const FIELD_TRANSFER_COMPANY = 'to_company_id';

    /** field form Pindah company baris Project Detail bermasalah: company tujuan */
    public const FIELD_MOVE_COMPANY = 'move_company_id';

    /** field form Pindah company: centang konfirmasi */
    public const FIELD_MOVE_CONFIRM = 'confirm_move_company';

    /** filter daftar Project Detail baris bermasalah (parameter `masalah`, applyDetailProblem) */
    public const DETAIL_PROBLEMS = ['company', 'project'];

    public function __construct(protected MasterScope $scope, protected MasterAccess $access) {}

    /** master_company.id pemilik bawaan project (MasterCrudConfig.PROJECT_OWNER_COMPANY_ID, bawaan 1 = Meindo) */
    public function companyId(): int
    {
        return (int) config('MasterCrudConfig.PROJECT_OWNER_COMPANY_ID', 1);
    }

    // ---- pemilik per project ----

    /** master_project sudah punya kolom owner_company_id (paket >= v3.13: migration / master:install-tables) */
    public function hasOwnerColumn(): bool
    {
        return SchemaColumns::hasColumn(self::TABLE_PROJECT, self::OWNER_COLUMN);
    }

    /**
     * Company pemilik satu project: owner_company_id, kosong / kolom belum ada = pemilik bawaan. $project = baris
     * master_project (stdClass / model) atau id; baris tanpa kolom itu (select sebagian) dibaca ulang dari tabel.
     */
    public function ownerCompanyIdOf(object|int $project): int
    {
        $default = $this->companyId();
        if (! $this->hasOwnerColumn()) {
            return $default;
        }

        $known = false;
        $value = null;
        if ($project instanceof Model) {
            $attributes = $project->getAttributes();
            if (array_key_exists(self::OWNER_COLUMN, $attributes)) {
                [$known, $value] = [true, $attributes[self::OWNER_COLUMN]];
            }
            $id = $project->getKey();
        } elseif (is_object($project)) {
            $attributes = get_object_vars($project);
            if (array_key_exists(self::OWNER_COLUMN, $attributes)) {
                [$known, $value] = [true, $attributes[self::OWNER_COLUMN]];
            }
            $id = $attributes['id'] ?? null;
        } else {
            $id = $project;
        }

        if (! $known && $id !== null) {
            $value = DB::table(self::TABLE_PROJECT)->where('id', (int) $id)->value(self::OWNER_COLUMN);
        }

        return (int) $value > 0 ? (int) $value : $default;
    }

    /** Ekspresi SQL company pemilik project untuk alias tabel master_project ($alias), aman dipakai di whereRaw/select. */
    public function ownerSql(string $alias): string
    {
        $default = $this->companyId();

        return $this->hasOwnerColumn() ? 'COALESCE(NULLIF('.$alias.'.'.self::OWNER_COLUMN.', 0), '.$default.')' : (string) $default;
    }

    /** Project ini milik company selain pemilik bawaan ("project non-Meindo"). */
    public function hasOtherOwner(object|int $project): bool
    {
        return $this->ownerCompanyIdOf($project) !== $this->companyId();
    }

    /** Nama pendek pemilik bawaan untuk teks (kode company, cadangan "pemilik bawaan"). */
    public function defaultOwnerLabel(): string
    {
        $code = SchemaColumns::hasTable(self::TABLE_COMPANY)
            ? DB::table(self::TABLE_COMPANY)->where('id', $this->companyId())->value('company_code')
            : null;

        return filled($code) ? (string) $code : 'pemilik bawaan';
    }

    /** Kode company untuk teks/pesan; tidak ditemukan = "#id". */
    public function companyLabel(int $companyId): string
    {
        $code = SchemaColumns::hasTable(self::TABLE_COMPANY)
            ? DB::table(self::TABLE_COMPANY)->where('id', $companyId)->value('company_code')
            : null;

        return filled($code) ? (string) $code : '#'.$companyId;
    }

    /** fitur aktif: hanya di hub dan bila tabel yang dibutuhkan ada */
    public function enabled(): bool
    {
        return $this->scope->isHub()
            && SchemaColumns::hasTable(self::TABLE_PROJECT)
            && SchemaColumns::hasTable(self::TABLE_DETAIL)
            && SchemaColumns::hasTable(self::TABLE_COMPANY)
            && SchemaColumns::hasColumn(self::TABLE_DETAIL, 'deleted_at')
            && SchemaColumns::hasColumn(self::TABLE_PROJECT, 'deleted_at');
    }

    /** user boleh membuat detail: izin tambah Project Detail (admin selalu) */
    public function canCreate(): bool
    {
        return $this->enabled() && $this->access->can('project-detail', 'create');
    }

    /**
     * Dari id project yang ditampilkan, id yang SUDAH punya detail aktif company pemilik project itu.
     *
     * @param  array<int, int|string>  $projectIds
     * @return array<int, int>
     */
    public function projectIdsWithDetail(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        return DB::table(self::TABLE_DETAIL.' as d')
            ->join(self::TABLE_PROJECT.' as p', 'p.id', '=', 'd.project_id')
            ->whereIn('d.project_id', $projectIds)
            ->whereRaw('d.company_id = '.$this->ownerSql('p'))
            ->whereNull('d.deleted_at')
            ->pluck('d.project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Buat Project Detail untuk company pemilik project, atau pulihkan yang terhapus. Idempoten: sudah ada = tidak
     * membuat lagi.
     *
     * @return array{status: 'created'|'restored'|'exists', id: int}
     *
     * @throws ValidationException project terhapus / kode client sudah dipakai project lain
     */
    public function ensure(object $project): array
    {
        $companyId = $this->ownerCompanyIdOf($project);
        $projectId = (int) $project->id;

        $plan = $this->ensurePlan($project);
        $existing = $plan['existing'];

        if ($plan['status'] === 'exists') {
            return ['status' => 'exists', 'id' => (int) $existing->id];
        }

        if ($existing !== null) {
            $existing->restore(); // Loggable mencatat pemulihan, broadcast master ikut jalan

            return ['status' => 'restored', 'id' => (int) $existing->id];
        }

        $owner = $this->scope->ownerForUpdate(self::TABLE_PROJECT, $project);
        $data = $this->scope->stamp(self::TABLE_DETAIL, [
            'project_id' => $projectId,
            'project_code_client' => $project->project_code,
            'project_name_client' => $project->project_name,
        ], $owner);
        // company_id di tabel ini = company client, bukan pemilik data: tetap company pemilik utama
        $data['company_id'] = $companyId;

        $detail = MasterProjectDetail::create($data);

        return ['status' => 'created', 'id' => (int) $detail->id];
    }

    /**
     * Yang akan dilakukan ensure(), tanpa menulis apa pun (dipakai juga --dry-run perintah perapian).
     *
     * @return array{status: 'created'|'restored'|'exists', existing: ?MasterProjectDetail}
     *
     * @throws ValidationException project terhapus / company pemilik tidak ada / kode client sudah dipakai project lain
     */
    protected function ensurePlan(object $project): array
    {
        $companyId = $this->ownerCompanyIdOf($project);
        $projectId = (int) $project->id;

        if (filled($project->deleted_at ?? null)) {
            throw ValidationException::withMessages(['project' => ['Project '.$project->project_code.' sudah dihapus: pulihkan (Restore) dulu sebelum membuat detailnya.']]);
        }
        if (! DB::table(self::TABLE_COMPANY)->where('id', $companyId)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['project' => ['Company pemilik (id '.$companyId.') tidak ditemukan atau sudah dihapus.']]);
        }

        $existing = MasterProjectDetail::withTrashed()
            ->where('project_id', $projectId)
            ->where('company_id', $companyId)
            ->orderByRaw('deleted_at is null desc')
            ->orderByDesc('id')
            ->first();

        if ($existing !== null && ! $existing->trashed()) {
            return ['status' => 'exists', 'existing' => $existing];
        }

        // kode client yang sama sudah dipakai project lain untuk company ini: jangan buat dobel diam-diam
        $bentrok = DB::table(self::TABLE_DETAIL)
            ->where('company_id', $companyId)
            ->where('project_code_client', $project->project_code)
            ->where('project_id', '!=', $projectId)
            ->whereNull('deleted_at')
            ->value('project_id');
        if ($bentrok !== null) {
            throw ValidationException::withMessages(['project' => ['Project Code Client '.$project->project_code.' untuk company pemilik sudah dipakai project id '.$bentrok.'.']]);
        }

        return ['status' => $existing !== null ? 'restored' : 'created', 'existing' => $existing];
    }

    // ---- baris detail untuk project BARU (form Project, import) ----

    /**
     * Company pemilik untuk baris detail project baru: PROJECT_OWNER_COMPANY_ID bila company itu terdaftar aktif.
     * Bila tidak (instalasi yang company pemiliknya bukan id itu), aturan sebelum v3.12.0 tetap berlaku: company
     * berkode ME, lalu company pertama. null = master_company kosong, tidak ada baris detail yang dibuat.
     */
    public function companyIdForNewProject(): ?int
    {
        if (! SchemaColumns::hasTable(self::TABLE_COMPANY)) {
            return null;
        }

        $companies = DB::table(self::TABLE_COMPANY)
            ->when(SchemaColumns::hasColumn(self::TABLE_COMPANY, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));

        $configured = $this->companyId();
        if ((clone $companies)->where('id', $configured)->exists()) {
            return $configured;
        }

        $fallback = (clone $companies)->where('company_code', 'ME')->orderBy('id')->value('id')
            ?? (clone $companies)->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    /**
     * Company yang diberi baris detail saat project baru dibuat, berurutan: company pemilik, lalu company pembuat bila
     * project dibuat lewat session delegasi (user aplikasi lain yang membuka hub, mis. admin MEB dari SPB) dan
     * company-nya bukan company pemilik. Tanpa delegasi (tim master-data) hanya company pemilik.
     *
     * @return list<int>
     */
    public function companyIdsForNewProject(): array
    {
        $owner = $this->companyIdForNewProject();
        if ($owner === null) {
            return [];
        }

        $creator = (int) ($this->scope->delegation()['company_id'] ?? 0);

        return $creator > 0 && $creator !== $owner ? [$owner, $creator] : [$owner];
    }

    /**
     * Baris Project Detail untuk project baru (belum disimpan), satu per company companyIdsForNewProject(): kode/nama
     * client = kode/nama project. Cara menyimpannya (model + Loggable, import) tetap urusan pemanggil.
     *
     * @param  list<int>|null  $companyIds  hasil companyIdsForNewProject() yang sudah diambil (import banyak baris)
     * @return list<array{project_id: int, project_code_client: string, project_name_client: ?string, company_id: int}>
     */
    public function rowsForNewProject(object $project, ?array $companyIds = null): array
    {
        $rows = [];
        foreach ($companyIds ?? $this->companyIdsForNewProject() as $companyId) {
            $rows[] = ['project_id' => (int) $project->id] + $this->mirrorValues($project) + ['company_id' => (int) $companyId];
        }

        return $rows;
    }

    // ---- pemilik project baru selain pemilik bawaan (project non-Meindo) ----

    /**
     * Company yang boleh dipilih user ini sebagai pemilik project BARU selain pemilik bawaan (form Create Project,
     * kolom Owner Company Code import). Hanya di hub dan setelah kolom owner_company_id ada:
     * - is_admin (tim Master Data): semua company aktif selain pemilik bawaan;
     * - session delegasi company lain (mis. admin MEB dari SPB): hanya company delegasinya;
     * - user lain: tidak ada (project selalu milik pemilik bawaan).
     *
     * @return Collection<int, object{id: int, company_code: string, company_name: ?string}>
     */
    public function ownerChoices(): Collection
    {
        if (! $this->enabled() || ! $this->hasOwnerColumn()) {
            return collect();
        }

        $default = $this->companyIdForNewProject() ?? $this->companyId();
        $columns = array_values(array_filter(['id', 'company_code', SchemaColumns::hasColumn(self::TABLE_COMPANY, 'company_name') ? 'company_name' : null]));
        $query = DB::table(self::TABLE_COMPANY)->whereNull('deleted_at')->where('id', '!=', $default)->orderBy('company_code');

        if (! $this->access->isAdmin()) {
            $delegation = (int) ($this->scope->delegation()['company_id'] ?? 0);
            if ($delegation <= 0) {
                return collect();
            }
            $query->where('id', $delegation);
        }

        return $query->get($columns)->map(function ($row) {
            $row->id = (int) $row->id;
            $row->company_name ??= null;

            return $row;
        });
    }

    /**
     * Pilihan awal form Create Project: session delegasi company lain = centang "bukan pemilik bawaan" menyala dengan
     * company delegasi (keputusan N3, bisa dimatikan); selain itu null (tidak dicentang).
     */
    public function defaultOtherOwner(): ?int
    {
        $delegation = (int) ($this->scope->delegation()['company_id'] ?? 0);
        if ($delegation <= 0) {
            return null;
        }

        return $this->ownerChoices()->contains(fn ($company) => $company->id === $delegation) ? $delegation : null;
    }

    /**
     * Pemilik + company baris detail untuk project BARU. $chosen kosong / sama dengan pemilik bawaan = perilaku lama
     * (pemilik bawaan, + company pembuat bila lewat delegasi). $chosen company lain = project milik company itu dengan
     * satu baris detail company itu saja (tanpa baris pemilik bawaan).
     *
     * @return array{owner: ?int, companies: list<int>, other: bool}
     *
     * @throws ValidationException company tidak boleh dipilih user ini / di luar hub / kolom belum ada
     */
    public function newProjectPlan(?int $chosen): array
    {
        $default = $this->companyIdForNewProject();
        if ($chosen === null || $chosen <= 0 || $chosen === $default) {
            return ['owner' => $default, 'companies' => $this->companyIdsForNewProject(), 'other' => false];
        }

        $field = self::FIELD_OWNER_COMPANY;
        if (! $this->enabled()) {
            throw ValidationException::withMessages([$field => ['Pemilik project selain '.$this->defaultOwnerLabel().' hanya bisa dipilih di Master Data (hub).']]);
        }
        if (! $this->hasOwnerColumn()) {
            throw ValidationException::withMessages([$field => ['Kolom master_project.owner_company_id belum ada: jalankan php artisan migrate di Master Data.']]);
        }
        if (! $this->ownerChoices()->contains(fn ($company) => $company->id === $chosen)) {
            throw ValidationException::withMessages([$field => ['Company '.$this->companyLabel($chosen).' tidak bisa dipilih sebagai pemilik project: hanya admin Master Data yang memilih pemilik selain '
                .$this->defaultOwnerLabel().', session delegasi hanya company-nya sendiri, dan company harus aktif.']]);
        }

        return ['owner' => $chosen, 'companies' => [$chosen], 'other' => true];
    }

    /**
     * Satu kode satu project (sejak v3.21.0): kode $code belum dipakai project lain selain $exceptProjectId, oleh company
     * mana pun, yaitu belum menjadi
     * - Project Code Client baris detail aktif di project lain, dan
     * - Project Code project aktif lain (kode master = kode company pemilik di aplikasi pemakai, juga bila baris
     *   pemiliknya hilang atau berkode lain).
     * Beberapa company boleh memakai kode yang sama di project YANG SAMA. Baris/project terhapus tidak dihitung. Dipakai
     * semua jalan yang membuat atau mengubah kode: Project Detail (tambah, ubah kode, import, Restore) dan Project
     * (Create, Import project baru, Edit kode, Pindah pemilik, Restore). Kode yang menunjuk dua project membuat dropdown
     * dokumen ambigu dan urutan nomor dokumen per kode (mis. sequence_numbers SPB `<kode>-SPB-...`) tercampur.
     *
     * @throws ValidationException
     */
    public function assertCodeFree(?string $code, ?int $exceptProjectId = null, string $field = 'project_code'): void
    {
        $message = $this->codeUsageMessage($code, $exceptProjectId);
        if ($message !== null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }

    /** Pesan assertCodeFree() bila kode sudah dipakai project lain, null = bebas. */
    public function codeUsageMessage(?string $code, ?int $exceptProjectId = null): ?string
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return null;
        }
        $rule = ': satu kode hanya boleh menunjuk satu project (nomor dokumen akan bentrok).';

        $detail = DB::table(self::TABLE_DETAIL)
            ->whereRaw('UPPER(TRIM(project_code_client)) = ?', [$code])
            ->whereNull('deleted_at')
            ->when($exceptProjectId !== null, fn ($query) => $query->where('project_id', '!=', $exceptProjectId))
            ->orderBy('id')
            ->first(['project_id', 'company_id']);
        if ($detail !== null) {
            $other = DB::table(self::TABLE_PROJECT)->where('id', (int) $detail->project_id)->value('project_code');

            return 'Kode '.$code.' sudah dipakai sebagai Project Code Client project '.($other ?? '#'.$detail->project_id)
                .' (company '.$this->companyLabel((int) $detail->company_id).')'.$rule;
        }

        $project = DB::table(self::TABLE_PROJECT)
            ->whereRaw('UPPER(TRIM(project_code)) = ?', [$code])
            ->when(SchemaColumns::hasColumn(self::TABLE_PROJECT, 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($exceptProjectId !== null, fn ($query) => $query->where('id', '!=', $exceptProjectId))
            ->value('project_code');

        return $project !== null ? 'Kode '.$code.' sudah dipakai sebagai Project Code project '.$project.$rule : null;
    }

    /**
     * Kode project untuk company $companyId belum dipakai baris detail aktif company itu di project lain.
     *
     * @deprecated sejak v3.21.0 paket tidak memakainya lagi: aturan diperketat jadi satu kode satu project untuk semua
     *     company, pakai assertCodeFree().
     *
     * @throws ValidationException
     */
    public function assertCodeFreeForCompany(int $companyId, string $code, ?int $exceptProjectId = null, string $field = 'project_code'): void
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return;
        }

        $projectId = DB::table(self::TABLE_DETAIL)
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(TRIM(project_code_client)) = ?', [$code])
            ->whereNull('deleted_at')
            ->when($exceptProjectId !== null, fn ($query) => $query->where('project_id', '!=', $exceptProjectId))
            ->value('project_id');
        if ($projectId === null) {
            return;
        }

        $other = DB::table(self::TABLE_PROJECT)->where('id', (int) $projectId)->value('project_code');
        throw ValidationException::withMessages([$field => ['Kode '.$code.' sudah dipakai company '.$this->companyLabel($companyId)
            .' sebagai Project Code Client project '.($other ?? '#'.$projectId).': nomor dokumen company itu akan bentrok.']]);
    }

    /**
     * Kode baru/ubahan (Project Code Client baris detail project $projectId, atau Project Code project $projectId; null =
     * project baru) belum dipakai project lain (assertCodeFree). $oldCode = kode sebelum diubah: kode yang tidak berubah
     * tidak dicek, jadi data lama yang terlanjur dobel tetap bisa diubah namanya.
     *
     * Sejak v3.21.0 berlaku untuk semua company (v3.20.0: hanya company yang sama, dengan parameter $companyId).
     *
     * @throws ValidationException
     */
    public function assertChangedCodeFree(?int $projectId, ?string $code, ?string $oldCode = null, string $field = 'project_code_client'): void
    {
        if ($oldCode !== null && $this->normalizeCode($code) === $this->normalizeCode($oldCode)) {
            return;
        }

        $this->assertCodeFree($code, $projectId, $field);
    }

    /**
     * @deprecated sejak v3.21.0, pakai assertChangedCodeFree(): $companyId tidak dipakai lagi (aturan berlaku untuk semua
     *     company).
     *
     * @throws ValidationException
     */
    public function assertDetailCodeFree(int $projectId, int $companyId, ?string $code, ?string $oldCode = null, string $field = 'project_code_client'): void
    {
        $this->assertChangedCodeFree($projectId, $code, $oldCode, $field);
    }

    // ---- kunci baris detail ----

    /** Baris detail aktif milik company pemilik project-nya, dan project itu aktif ("baris pemilik"). */
    public function isOwnerRow(object $detail): bool
    {
        if (filled($detail->deleted_at ?? null) || ! filled($detail->project_id ?? null)) {
            return false;
        }

        $project = DB::table(self::TABLE_PROJECT)->where('id', (int) $detail->project_id)->first();
        if ($project === null || filled($project->deleted_at ?? null)) {
            return false;
        }

        return (int) $detail->company_id === $this->ownerCompanyIdOf($project);
    }

    /**
     * Alasan perubahan baris detail ditolak (form Project Detail, import), null = boleh.
     * - Project dan company baris mana pun tidak bisa diganti (keputusan N2): baris = pasangan (project, company) yang
     *   dipegang aplikasi (SPB, dll.); ganti = hapus baris lalu tambah baris baru.
     * - Kode/nama baris pemilik mengikuti Project (cermin): diubah lewat Edit Project.
     *
     * @param  array<string, mixed>  $new  nilai baru; kunci yang tidak ada = tidak diubah
     */
    public function detailChangeDenial(object $existing, array $new): ?string
    {
        if (array_key_exists('project_id', $new) && (int) $new['project_id'] !== (int) $existing->project_id) {
            return 'Project baris Project Detail tidak bisa diganti. Hapus baris ini lalu tambah baris baru di project yang benar.';
        }
        if (array_key_exists('company_id', $new) && (int) $new['company_id'] !== (int) $existing->company_id) {
            return 'Company baris Project Detail tidak bisa diganti. Hapus baris ini lalu tambah baris baru untuk company lain.';
        }

        if ($this->isOwnerRow($existing)) {
            $codeChanged = array_key_exists('project_code_client', $new)
                && $this->normalizeCode($new['project_code_client']) !== $this->normalizeCode($existing->project_code_client);
            $nameChanged = array_key_exists('project_name_client', $new)
                && trim((string) $new['project_name_client']) !== trim((string) $existing->project_name_client);
            if ($codeChanged || $nameChanged) {
                return 'Baris ini baris pemilik project ('.$this->companyLabel((int) $existing->company_id).'): kode/nama mengikuti Project. '
                    .'Ubah lewat Edit Project; pemilik diganti lewat Edit Project → Pindah pemilik.';
            }
        }

        return null;
    }

    /**
     * detailChangeDenial() sebagai error validasi pada field form yang bersangkutan (company_id / project_id /
     * project_code_client).
     *
     * @param  array<string, mixed>  $new
     *
     * @throws ValidationException
     */
    public function assertDetailChangeAllowed(object $existing, array $new): void
    {
        $denial = $this->detailChangeDenial($existing, $new);
        if ($denial === null) {
            return;
        }

        $field = match (true) {
            array_key_exists('project_id', $new) && (int) $new['project_id'] !== (int) $existing->project_id => 'project_id',
            array_key_exists('company_id', $new) && (int) $new['company_id'] !== (int) $existing->company_id => 'company_id',
            default => 'project_code_client',
        };

        throw ValidationException::withMessages([$field => [$denial]]);
    }

    /** Alasan hapus baris detail ditolak, null = boleh: baris pemilik project aktif tidak bisa dihapus. */
    public function detailDeleteDenial(object $detail): ?string
    {
        return $this->isOwnerRow($detail)
            ? 'Baris pemilik project ('.$this->companyLabel((int) $detail->company_id).') tidak bisa dihapus. Pindahkan pemilik dulu lewat Edit Project → Pindah pemilik, atau hapus project-nya.'
            : null;
    }

    // ---- baris bermasalah + pindah company (pengecualian N2) ----

    /**
     * Status company baris detail di master_company: 'missing' = company_id kosong / barisnya tidak ada, 'deleted' =
     * company sudah dihapus (soft delete), null = aktif.
     */
    public function detailCompanyProblem(object $detail): ?string
    {
        $companyId = (int) ($detail->company_id ?? 0);
        $company = $companyId > 0 ? DB::table(self::TABLE_COMPANY)->where('id', $companyId)->first(['id', 'deleted_at']) : null;
        if ($company === null) {
            return 'missing';
        }

        return filled($company->deleted_at ?? null) ? 'deleted' : null;
    }

    /** Teks peringatan untuk status detailCompanyProblem() ('missing' / 'deleted'). */
    public function detailCompanyProblemLabel(string $problem, ?string $companyCode = null): string
    {
        return $problem === 'deleted'
            ? 'Company '.(filled($companyCode) ? $companyCode.' ' : '').'sudah dihapus dari master'
            : 'Company tidak ada di master';
    }

    /**
     * Filter daftar Project Detail baris bermasalah (DETAIL_PROBLEMS, parameter `masalah`): baris aktif yang 'company'-nya
     * terhapus / tidak ada di master, atau 'project'-nya terhapus / tidak ada (sama dengan hitungan detailSummary; baris
     * terhapus bukan masalah yang perlu dirapikan). $detail/$project/$company = alias join daftar. Nilai lain diabaikan.
     */
    public function applyDetailProblem($query, ?string $problem, string $detail = 'mpd', string $project = 'mp', string $company = 'mc'): void
    {
        $missing = match ($problem) {
            'company' => $company,
            'project' => $project,
            default => null,
        };
        if ($missing === null) {
            return;
        }

        $query->whereNull($detail.'.deleted_at')
            ->where(fn ($q) => $q->whereNull($missing.'.id')->orWhereNotNull($missing.'.deleted_at'));
    }

    /** User boleh memindahkan company baris bermasalah: hub + admin Master Data (keputusan pemilik 2026-11-19). */
    public function canMoveCompany(): bool
    {
        return $this->enabled() && $this->access->isAdmin();
    }

    /**
     * Alasan baris ini tidak bisa dipindah company, null = boleh. Hanya baris aktif yang company-nya terhapus / tidak ada
     * di master, project-nya aktif, dan bukan baris pemilik project. Company yang masih aktif tetap tidak bisa diganti
     * (keputusan N2: hapus baris lalu tambah baris baru).
     */
    public function moveCompanyDenial(object $detail): ?string
    {
        if (filled($detail->deleted_at ?? null)) {
            return 'Baris Project Detail ini sudah dihapus: pulihkan (Restore) dulu.';
        }
        if ($this->detailCompanyProblem($detail) === null) {
            return 'Company baris ini masih aktif di master, jadi tidak bisa diganti: hapus baris lalu tambah baris baru untuk company lain.';
        }
        $project = filled($detail->project_id ?? null)
            ? DB::table(self::TABLE_PROJECT)->where('id', (int) $detail->project_id)->first(['id', 'deleted_at'])
            : null;
        if ($project === null || filled($project->deleted_at ?? null)) {
            return 'Project baris ini tidak ada atau sudah dihapus: pulihkan project-nya dulu, atau hapus baris ini.';
        }
        if ($this->isOwnerRow($detail)) {
            return 'Baris ini baris pemilik project: pemilik diganti lewat Edit Project → Pindah pemilik.';
        }

        return null;
    }

    /**
     * Pilihan company tujuan Pindah company: company aktif selain pemilik project (baris pemilik lewat Buat Detail /
     * Pindah pemilik) yang belum punya baris di project ini, termasuk baris terhapus (index unik project + company).
     *
     * @return Collection<int, object{id: int, company_code: string, company_name: ?string}>
     */
    public function moveCompanyChoices(object $detail): Collection
    {
        if (! $this->canMoveCompany() || $this->moveCompanyDenial($detail) !== null) {
            return collect();
        }

        $taken = DB::table(self::TABLE_DETAIL)->where('project_id', (int) $detail->project_id)->whereNotNull('company_id')
            ->pluck('company_id')->map(fn ($id) => (int) $id)->all();
        $taken[] = $this->ownerCompanyIdOf((int) $detail->project_id);
        $columns = array_values(array_filter(['id', 'company_code', SchemaColumns::hasColumn(self::TABLE_COMPANY, 'company_name') ? 'company_name' : null]));

        return DB::table(self::TABLE_COMPANY)->whereNull('deleted_at')->whereNotIn('id', array_values(array_unique($taken)))
            ->orderBy('company_code')->get($columns)
            ->map(function ($company) {
                $company->id = (int) $company->id;
                $company->company_name ??= null;

                return $company;
            })
            ->values();
    }

    /**
     * Pindahkan baris Project Detail yang company-nya terhapus / tidak ada di master ke company aktif lain. Pengecualian
     * terbatas keputusan N2 (keputusan pemilik 2026-11-19): id baris, project, dan kode/nama client tetap, jadi rujukan
     * aplikasi lain ke baris ini (mis. dokumen SPB) ikut pindah ke company baru. Tersimpan lewat model (Loggable +
     * broadcast) dan callback DB master, seperti edit manual.
     *
     * @return array{from: int, to: int, problem: string}
     *
     * @throws ValidationException tidak boleh / baris tidak memenuhi syarat / company tujuan tidak sah
     */
    public function moveCompany(int $detailId, int $toCompanyId): array
    {
        $field = self::FIELD_MOVE_COMPANY;
        if (! $this->canMoveCompany()) {
            throw ValidationException::withMessages([$field => ['Pindah company baris Project Detail hanya untuk admin Master Data di hub.']]);
        }

        return DB::transaction(function () use ($detailId, $toCompanyId, $field): array {
            $detail = ProjectDetail::withTrashed()->whereKey($detailId)->lockForUpdate()->first();
            if ($detail === null) {
                throw ValidationException::withMessages([$field => ['Baris Project Detail tidak ditemukan.']]);
            }
            if (($denial = $this->moveCompanyDenial($detail)) !== null) {
                throw ValidationException::withMessages([$field => [$denial]]);
            }
            if (! DB::table(self::TABLE_COMPANY)->where('id', $toCompanyId)->whereNull('deleted_at')->exists()) {
                throw ValidationException::withMessages([$field => ['Company tujuan tidak ditemukan atau sudah dihapus.']]);
            }
            if ($toCompanyId === $this->ownerCompanyIdOf((int) $detail->project_id)) {
                throw ValidationException::withMessages([$field => ['Company '.$this->companyLabel($toCompanyId).' pemilik project ini: baris pemilik dibuat lewat tombol Buat Detail di daftar Project, atau ganti pemilik lewat Edit Project → Pindah pemilik.']]);
            }
            $other = DB::table(self::TABLE_DETAIL)->where('project_id', (int) $detail->project_id)->where('company_id', $toCompanyId)
                ->where('id', '!=', $detail->id)->orderByRaw('deleted_at is null desc')->first(['id', 'deleted_at']);
            if ($other !== null) {
                throw ValidationException::withMessages([$field => ['Company '.$this->companyLabel($toCompanyId).' sudah punya baris di project ini (id '.$other->id.(filled($other->deleted_at) ? ', terhapus' : '').'): bila baris ini dobel, hapus saja baris ini.']]);
            }
            // kode baris tetap, jadi harus sesuai awalan company tujuan (ME 1/2/3/8/9, MEB 7, ...)
            if (($prefixError = app(ProjectCodePrefix::class)->message($toCompanyId, (string) $detail->project_code_client, 'Project Code Client')) !== null) {
                throw ValidationException::withMessages([$field => [$prefixError.' Pindah company tidak mengubah kode baris: pilih company lain, atau hapus baris ini lalu tambah baris baru dengan kode yang sesuai.']]);
            }

            $problem = (string) $this->detailCompanyProblem($detail);
            $from = (int) $detail->company_id;
            $detail->company_id = $toCompanyId;
            $detail->save(); // Loggable: company_id sebelum/sesudah
            $this->syncToMaster($detail);

            return ['from' => $from, 'to' => $toCompanyId, 'problem' => $problem];
        });
    }

    /**
     * Ringkasan untuk card di atas daftar Project Detail (hub): baris detail aktif per company, termasuk company yang
     * sudah dihapus, dan jumlah baris bermasalah (company / project terhapus atau tidak ada di master).
     *
     * @return array{detail_total: int, company_deleted: int, company_missing: int, project_problem: int,
     *     companies: list<array{id: int, code: string, name: string, detail: int, deleted: bool}>}
     */
    public function detailSummary(): array
    {
        $aktif = fn () => DB::table(self::TABLE_DETAIL.' as d')->whereNull('d.deleted_at')
            ->leftJoin(self::TABLE_COMPANY.' as c', 'c.id', '=', 'd.company_id')
            ->leftJoin(self::TABLE_PROJECT.' as p', 'p.id', '=', 'd.project_id');
        $name = SchemaColumns::hasColumn(self::TABLE_COMPANY, 'company_name') ? ['c.company_name'] : [];

        $companies = $aktif()->whereNotNull('c.id')
            ->groupBy('c.id', 'c.company_code', ...$name, ...['c.deleted_at'])
            ->orderByRaw('c.deleted_at is not null')->orderBy('c.company_code')
            ->get(['c.id', 'c.company_code', ...$name, 'c.deleted_at', DB::raw('count(*) as detail')])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->company_code,
                'name' => (string) ($row->company_name ?? ''),
                'detail' => (int) $row->detail,
                'deleted' => filled($row->deleted_at),
            ])->all();

        return [
            'detail_total' => $aktif()->count(),
            'company_deleted' => $aktif()->whereNotNull('c.id')->whereNotNull('c.deleted_at')->count(),
            'company_missing' => $aktif()->whereNull('c.id')->count(),
            'project_problem' => $aktif()->where(fn ($q) => $q->whereNull('p.id')->orWhereNotNull('p.deleted_at'))->count(),
            'companies' => $companies,
        ];
    }

    // ---- pindah pemilik ----

    /** User ini boleh memindahkan pemilik project: hub, kolom ada, admin Master Data (keputusan N4). */
    public function canTransfer(): bool
    {
        return $this->enabled() && $this->hasOwnerColumn() && $this->access->isAdmin();
    }

    /**
     * Pilihan company pemilik baru untuk Pindah pemilik: company aktif selain pemilik sekarang. `has_row` = company itu
     * sudah punya baris detail aktif di project ini (kode/nama induk akan mengikuti baris itu).
     *
     * @return Collection<int, object{id: int, company_code: string, company_name: ?string, has_row: bool, code: ?string, name: ?string}>
     */
    public function transferChoices(object $project): Collection
    {
        if (! $this->canTransfer()) {
            return collect();
        }

        $owner = $this->ownerCompanyIdOf($project);
        $rows = DB::table(self::TABLE_DETAIL)->where('project_id', (int) $project->id)->whereNull('deleted_at')
            ->orderBy('id')->get(['company_id', 'project_code_client', 'project_name_client'])->keyBy(fn ($row) => (int) $row->company_id);
        $columns = array_values(array_filter(['id', 'company_code', SchemaColumns::hasColumn(self::TABLE_COMPANY, 'company_name') ? 'company_name' : null]));

        return DB::table(self::TABLE_COMPANY)->whereNull('deleted_at')->where('id', '!=', $owner)->orderBy('company_code')->get($columns)
            ->map(function ($company) use ($rows) {
                $row = $rows->get((int) $company->id);
                $company->id = (int) $company->id;
                $company->company_name ??= null;
                $company->has_row = $row !== null;
                $company->code = $row->project_code_client ?? null;
                $company->name = $row->project_name_client ?? null;

                return $company;
            })
            ->sortByDesc('has_row')->values();
    }

    /**
     * Pindahkan pemilik project ke company $toCompanyId, satu transaksi:
     * 1. baris detail company tujuan dipakai bila ada, dipulihkan bila terhapus, dibuat dari kode/nama project bila
     *    belum ada (keputusan N6);
     * 2. owner_company_id = tujuan, kode/nama project = kode/nama baris tujuan (baris pemilik = cermin project);
     * 3. baris pemilik lama TETAP ada sebagai baris company lain (kode/nama lamanya tidak berubah), boleh dihapus
     *    terpisah bila company itu berhenti memakai project.
     * Tersimpan lewat model (Loggable + broadcast) dan callback DB master, seperti edit manual.
     *
     * @return array{from: int, to: int, row: 'existing'|'restored'|'created', code_before: string, code_after: string}
     *
     * @throws ValidationException tidak boleh / project terhapus / company tidak aktif / kode atau nama bentrok
     */
    public function transferOwner(int $projectId, int $toCompanyId): array
    {
        $field = self::FIELD_TRANSFER_COMPANY;
        if (! $this->canTransfer()) {
            throw ValidationException::withMessages([$field => ['Pindah pemilik project hanya untuk admin Master Data di hub, setelah kolom owner_company_id ada.']]);
        }

        return DB::transaction(function () use ($projectId, $toCompanyId, $field): array {
            $project = Project::query()->whereKey($projectId)->lockForUpdate()->first();
            if ($project === null) {
                throw ValidationException::withMessages([$field => ['Project tidak ditemukan atau sudah dihapus: pulihkan (Restore) dulu.']]);
            }
            $from = $this->ownerCompanyIdOf($project);
            if ($toCompanyId === $from) {
                throw ValidationException::withMessages([$field => ['Company '.$this->companyLabel($toCompanyId).' sudah pemilik project ini.']]);
            }
            if (! DB::table(self::TABLE_COMPANY)->where('id', $toCompanyId)->whereNull('deleted_at')->exists()) {
                throw ValidationException::withMessages([$field => ['Company tujuan tidak ditemukan atau sudah dihapus.']]);
            }

            $detail = ProjectDetail::withTrashed()->where('project_id', $project->id)->where('company_id', $toCompanyId)
                ->orderByRaw('deleted_at is null desc')->orderByDesc('id')->first();
            $rowStatus = 'existing';
            if ($detail === null) {
                $data = $this->scope->stamp(self::TABLE_DETAIL, ['project_id' => (int) $project->id] + $this->mirrorValues($project), $this->scope->ownerOf($project));
                // company_id di tabel ini = company project, bukan pemilik data baris
                $data['company_id'] = $toCompanyId;
                $detail = ProjectDetail::create($data);
                $rowStatus = 'created';
            } elseif ($detail->trashed()) {
                $detail->restore();
                $rowStatus = 'restored';
            }

            $code = $this->normalizeCode($detail->project_code_client);
            $name = filled($detail->project_name_client) ? trim((string) $detail->project_name_client) : (string) $project->project_name;
            if ($code === '') {
                throw ValidationException::withMessages([$field => ['Baris detail company '.$this->companyLabel($toCompanyId).' belum punya Project Code Client: isi dulu di Project Detail.']]);
            }
            $codeUser = DB::table(self::TABLE_PROJECT)->where('id', '!=', $project->id)->whereRaw('UPPER(TRIM(project_code)) = ?', [$code])->value('project_code');
            if ($codeUser !== null) {
                throw ValidationException::withMessages([$field => ['Kode '.$code.' (baris company '.$this->companyLabel($toCompanyId).') sudah dipakai project lain sebagai Project Code.']]);
            }
            // kode project baru (= kode baris tujuan) harus sesuai awalan company pemilik baru (ME 1/2/3/8/9, MEB 7, ...)
            if (($prefixError = app(ProjectCodePrefix::class)->message($toCompanyId, $code)) !== null) {
                throw ValidationException::withMessages([$field => [$prefixError.($rowStatus === 'created'
                    ? ' Ubah dulu Project Code project ini, atau tambahkan baris Project Detail company itu dengan kode yang sesuai, lalu pindahkan pemiliknya.'
                    : ' Kode ini diambil dari baris Project Detail company itu: ubah dulu kodenya di Project Detail.')]]);
            }
            // kode project baru (= kode baris tujuan) belum dipakai baris detail company mana pun di project lain
            $this->assertCodeFree($code, (int) $project->id, $field);
            $nameUser = DB::table(self::TABLE_PROJECT)->where('id', '!=', $project->id)->where('project_name', $name)->value('project_code');
            if ($nameUser !== null) {
                throw ValidationException::withMessages([$field => ['Nama "'.$name.'" (baris company '.$this->companyLabel($toCompanyId).') sudah dipakai project '.$nameUser.'.']]);
            }

            $codeBefore = (string) $project->project_code;
            $project->fill([self::OWNER_COLUMN => $toCompanyId, 'project_code' => $code, 'project_name' => $name]);
            $project->save(); // Loggable: sebelum/sesudah owner_company_id + kode/nama
            $this->syncProjectToMaster($project);
            if ($rowStatus !== 'existing') {
                $this->syncToMaster($detail);
            }

            return ['from' => $from, 'to' => $toCompanyId, 'row' => $rowStatus, 'code_before' => $codeBefore, 'code_after' => $code];
        });
    }

    // ---- cermin 1:1 baris pemilik ----

    /**
     * Nilai cermin baris pemilik: kode/nama client = kode/nama project.
     *
     * @return array{project_code_client: string, project_name_client: ?string}
     */
    public function mirrorValues(object $project): array
    {
        return [
            'project_code_client' => (string) $project->project_code,
            'project_name_client' => filled($project->project_name ?? null) ? (string) $project->project_name : null,
        ];
    }

    /**
     * Baris detail aktif company pemilik yang sebelumnya cermin project ini (project_code_client = kode LAMA) dan
     * belum sama dengan kode/nama project sekarang: sudah diisi nilai baru, BELUM disimpan. Baris company lain dan
     * baris ber-kode client sendiri (klien) tidak termasuk. Baris yang kode barunya sudah dipakai baris lain pada
     * project + company yang sama (index unik) dilewati.
     *
     * @return list<ProjectDetail>
     */
    public function pendingMirror(object $project, string $oldCode): array
    {
        $old = $this->normalizeCode($oldCode);
        if ($old === '') {
            return [];
        }

        $pending = [];
        $details = ProjectDetail::query()
            ->where('project_id', (int) $project->id)
            ->where('company_id', $this->ownerCompanyIdOf($project))
            ->orderBy('id')
            ->get();
        foreach ($details as $detail) {
            if ($this->normalizeCode($detail->project_code_client) !== $old) {
                continue;
            }
            $detail->fill($this->mirrorValues($project));
            if ($detail->isDirty() && $this->mirrorConflict($detail) === null) {
                $pending[] = $detail;
            }
        }

        return $pending;
    }

    /**
     * Cermin saat project diubah: baris pemilik yang sebelumnya cermin (kode client = kode LAMA project) ikut diubah
     * ke kode/nama baru. Disimpan lewat model (Loggable + broadcast master) dan callback DB master, sama dengan edit
     * manual Project Detail, supaya perubahannya ikut tersebar ke aplikasi klien.
     *
     * @param  string  $oldCode  project_code sebelum project diubah
     * @return bool true bila ada baris detail yang berubah
     */
    public function mirror(object $project, string $oldCode): bool
    {
        $changed = false;
        foreach ($this->pendingMirror($project, $oldCode) as $detail) {
            $detail->save();
            $this->syncToMaster($detail);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Sebar perubahan baris detail yang disimpan tanpa event model (import menyimpan diam-diam dan mencatat lognya
     * sendiri): broadcast master + callback DB master, seperti yang terjadi saat edit manual.
     */
    public function publish(ProjectDetail $detail): void
    {
        try {
            if (! ProjectDetail::$disableMasterBroadcast && ! MasterBroadcastService::isBroadcastingDisabled() && config('MasterConfig.senada.active', true)) {
                app(MasterBroadcastService::class)->broadcastTableChange(self::TABLE_DETAIL, $detail, 'updated');
            }
        } catch (Throwable $e) {
            report($e);
        }

        $this->syncToMaster($detail);
    }

    /**
     * Rapikan baris pemilik satu project aktif (perintah master:project-detail-mirror): pastikan ada (ensure():
     * dibuat, atau dipulihkan bila terhapus), lalu samakan kode/nama cerminnya dengan project. Project dengan baris
     * pemilik ganda tidak disentuh. $dryRun = hanya melaporkan, tidak menulis.
     *
     * @return array{status: 'ok'|'created'|'restored'|'updated'|'duplicate'|'failed', before: ?string, after: ?string, note: ?string}
     */
    public function reconcile(object $project, bool $dryRun = false): array
    {
        $target = $this->mirrorLabel($this->mirrorValues($project));
        $result = fn (string $status, ?string $before = null, ?string $note = null): array => [
            'status' => $status,
            'before' => $before,
            'after' => in_array($status, ['created', 'restored', 'updated'], true) ? $target : null,
            'note' => $note,
        ];

        $active = ProjectDetail::query()
            ->where('project_id', (int) $project->id)
            ->where('company_id', $this->ownerCompanyIdOf($project))
            ->orderBy('id')
            ->get();
        if ($active->count() > 1) {
            return $result('duplicate', null, $active->count().' baris pemilik aktif: rapikan manual');
        }

        $status = 'ok';
        $detail = $active->first();

        try {
            if ($detail === null) {
                $plan = $this->ensurePlan($project);
                $status = $plan['status'] === 'exists' ? 'ok' : $plan['status'];
                $ensuredId = $dryRun ? $plan['existing']?->id : $this->ensure($project)['id'];
                if ($status === 'created') {
                    return $result('created');
                }
                $detail = ProjectDetail::withTrashed()->find($ensuredId);
                if ($detail === null) {
                    return $result($status);
                }
            }

            $before = $this->mirrorLabel($detail->only(['project_code_client', 'project_name_client']));
            $detail->fill($this->mirrorValues($project));
            if (! $detail->isDirty()) {
                return $result($status, $status === 'restored' ? $before : null);
            }
            if (($conflict = $this->mirrorConflict($detail)) !== null) {
                return $result('failed', $before, 'kode cermin sudah dipakai baris detail id '.$conflict.' pada project ini: rapikan manual');
            }
            if (! $dryRun) {
                $detail->save();
                $this->syncToMaster($detail);
            }

            return $result($status === 'restored' ? 'restored' : 'updated', $before);
        } catch (ValidationException $e) {
            return $result('failed', null, $e->validator->errors()->first());
        } catch (Throwable $e) {
            report($e);

            return $result('failed', null, $e->getMessage());
        }
    }

    /**
     * Id baris detail lain (termasuk yang terhapus) pada project + company yang sama yang sudah memakai kode cermin
     * baru: menyimpannya akan melanggar index unik. null = aman.
     */
    protected function mirrorConflict(ProjectDetail $detail): ?int
    {
        if (! $detail->isDirty('project_code_client')) {
            return null;
        }

        $id = ProjectDetail::withTrashed()
            ->where('project_id', $detail->project_id)
            ->where('company_id', $detail->company_id)
            ->where('project_code_client', $detail->project_code_client)
            ->where('id', '!=', $detail->id)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** callback ke DB master bila aplikasi mengedit langsung dan DB master-nya terpisah (sama dengan ProjectDetailController) */
    protected function syncToMaster(ProjectDetail $detail): void
    {
        try {
            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                LibraryClayController::updateMaster([
                    'sync_tabel' => self::TABLE_DETAIL,
                    'sync_id' => $detail->id,
                    'sync_row' => $detail->toArray(),
                    'sync_list_callback' => config('AppConfig.CALLBACK_URL'),
                ]);
            }
        } catch (Throwable $e) {
            report($e); // project sudah tersimpan: gagal menyalin ke DB master tidak menggagalkan penyimpanannya
        }
    }

    /** callback ke DB master untuk baris master_project (sama dengan update di ProjectController::store) */
    protected function syncProjectToMaster(Project $project): void
    {
        try {
            if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
                LibraryClayController::updateMaster([
                    'sync_tabel' => self::TABLE_PROJECT,
                    'sync_id' => $project->id,
                    'sync_row' => $project->toArray(),
                    'sync_list_callback' => config('AppConfig.CALLBACK_URL'),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function normalizeCode(mixed $code): string
    {
        return strtoupper(trim((string) $code));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function mirrorLabel(array $values): string
    {
        return ($values['project_code_client'] ?? '').' | '.($values['project_name_client'] ?? '');
    }

    /**
     * Ringkasan untuk card: company aktif beserta jumlah Project Code Client (detail aktif pada project aktif), dan
     * cek kesesuaian 1:1 project master dengan baris company pemiliknya (pemilik bawaan, atau owner_company_id).
     *
     * @return array{
     *     company_total: int, company_deleted: int, project_total: int, project_deleted: int, detail_total: int,
     *     owner: array{id: int, code: ?string, name: ?string, active: bool},
     *     companies: array<int, array{id: int, code: string, name: string, detail: int, owner: bool, owned: int}>,
     *     missing: int, missing_sample: array<int, string>, duplicate: int, orphan_company: int, orphan_project: int,
     *     other_owner: int, consistent: bool
     * }
     */
    public function summary(): array
    {
        $owner = $this->companyId();
        $ownerSql = $this->ownerSql(self::TABLE_PROJECT);

        // project aktif per company pemilik (owner_company_id; kosong = pemilik bawaan)
        $owned = DB::table(self::TABLE_PROJECT)->whereNull('deleted_at')
            ->groupBy(DB::raw($ownerSql))
            ->get([DB::raw($ownerSql.' as company_id'), DB::raw('count(*) as total')])
            ->mapWithKeys(fn ($row) => [(int) $row->company_id => (int) $row->total]);

        $companies = DB::table(self::TABLE_COMPANY.' as c')
            ->leftJoin(self::TABLE_DETAIL.' as d', function ($join) {
                $join->on('d.company_id', '=', 'c.id')->whereNull('d.deleted_at')
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))->from(self::TABLE_PROJECT.' as p')->whereColumn('p.id', 'd.project_id')->whereNull('p.deleted_at');
                    });
            })
            ->whereNull('c.deleted_at')
            ->groupBy('c.id', 'c.company_code', 'c.company_name')
            ->orderByRaw('c.id = ? desc', [$owner])->orderBy('c.company_code')
            ->get(['c.id', 'c.company_code', 'c.company_name', DB::raw('count(d.id) as detail')])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->company_code,
                'name' => (string) $row->company_name,
                'detail' => (int) $row->detail,
                'owner' => (int) $row->id === $owner,
                'owned' => $owned[(int) $row->id] ?? 0,
            ])->all();

        // project aktif tanpa baris company pemiliknya sendiri (project non-Meindo tanpa baris Meindo BUKAN kekurangan)
        $projectsAktif = DB::table(self::TABLE_PROJECT)->whereNull('deleted_at');
        $withoutDetail = (clone $projectsAktif)->whereNotExists(function ($q) use ($ownerSql) {
            $q->select(DB::raw(1))->from(self::TABLE_DETAIL.' as d')
                ->whereColumn('d.project_id', self::TABLE_PROJECT.'.id')
                ->whereRaw('d.company_id = '.$ownerSql)->whereNull('d.deleted_at');
        });
        $missing = (clone $withoutDetail)->count();

        $ownerRow = DB::table(self::TABLE_COMPANY)->where('id', $owner)->first(['company_code', 'company_name', 'deleted_at']);

        $detailAktif = DB::table(self::TABLE_DETAIL.' as d')->whereNull('d.deleted_at');
        $projectTotal = (clone $projectsAktif)->count();

        $summary = [
            'company_total' => DB::table(self::TABLE_COMPANY)->whereNull('deleted_at')->count(),
            'company_deleted' => DB::table(self::TABLE_COMPANY)->whereNotNull('deleted_at')->count(),
            'project_total' => $projectTotal,
            'project_deleted' => DB::table(self::TABLE_PROJECT)->whereNotNull('deleted_at')->count(),
            'detail_total' => (clone $detailAktif)->count(),
            'owner' => [
                'id' => $owner,
                'code' => $ownerRow->company_code ?? null,
                'name' => $ownerRow->company_name ?? null,
                'active' => $ownerRow !== null && blank($ownerRow->deleted_at),
            ],
            'companies' => $companies,
            'missing' => $missing,
            'missing_sample' => (clone $withoutDetail)->orderBy('project_code')->limit(5)->pluck('project_code')->map(fn ($c) => (string) $c)->all(),
            'duplicate' => DB::table(self::TABLE_DETAIL.' as d')
                ->join(self::TABLE_PROJECT, self::TABLE_PROJECT.'.id', '=', 'd.project_id')
                ->whereRaw('d.company_id = '.$ownerSql)->whereNull('d.deleted_at')
                ->groupBy('d.project_id')->havingRaw('count(*) > 1')->get(['d.project_id'])->count(),
            // project aktif yang pemiliknya bukan pemilik bawaan (project non-Meindo)
            'other_owner' => $owned->except([$owner])->sum(),
            // detail aktif yang company-nya terhapus / tidak ada: tidak terhitung di company mana pun
            'orphan_company' => (clone $detailAktif)->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from(self::TABLE_COMPANY.' as c')->whereColumn('c.id', 'd.company_id')->whereNull('c.deleted_at');
            })->count(),
            // detail aktif yang project-nya terhapus / tidak ada
            'orphan_project' => (clone $detailAktif)->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from(self::TABLE_PROJECT.' as p')->whereColumn('p.id', 'd.project_id')->whereNull('p.deleted_at');
            })->count(),
        ];
        $summary['consistent'] = $missing === 0 && $summary['duplicate'] === 0 && $summary['owner']['active'];

        return $summary;
    }
}
