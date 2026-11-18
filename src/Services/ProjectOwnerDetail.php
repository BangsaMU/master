<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Bangsamu\Master\Models\MasterProjectDetail;
use Bangsamu\Master\Models\ProjectDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Aturan: master_project adalah milik utama company pemilik (Meindo, master_company.id 1) sehingga setiap project
 * aktif wajib punya TEPAT SATU Project Detail untuk company itu, cermin 1:1 dari project (project_code_client =
 * project_code, project_name_client = project_name). Company lain ditambahkan sebagai baris berikutnya. Service ini
 * - mencari project yang detail company pemiliknya belum ada (tombol "Buat Detail" di daftar Project),
 * - membuat (atau memulihkan, bila detailnya terhapus) detail itu,
 * - menghitung ringkasan company vs project code client untuk card di atas daftar Project,
 * - menentukan baris detail untuk project BARU (form, import): company pemilik, lalu company pembuat bila project
 *   dibuat lewat session delegasi company lain (rowsForNewProject), dan
 * - menjaga cermin saat kode/nama project berubah (mirror) serta merapikan data lama (reconcile, perintah
 *   master:project-detail-mirror).
 * Tombol, card, dan perintah perapian hanya di hub (master-data): aplikasi klien memegang salinan tabel dan tidak
 * mengubah detail milik Meindo.
 */
class ProjectOwnerDetail
{
    public const TABLE_PROJECT = 'master_project';

    public const TABLE_DETAIL = 'master_project_detail';

    public const TABLE_COMPANY = 'master_company';

    public function __construct(protected MasterScope $scope, protected MasterAccess $access) {}

    /** master_company.id pemilik utama project (MasterCrudConfig.PROJECT_OWNER_COMPANY_ID, bawaan 1 = Meindo) */
    public function companyId(): int
    {
        return (int) config('MasterCrudConfig.PROJECT_OWNER_COMPANY_ID', 1);
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
     * Dari id project yang ditampilkan, id yang SUDAH punya detail aktif company pemilik.
     *
     * @param  array<int, int|string>  $projectIds
     * @return array<int, int>
     */
    public function projectIdsWithDetail(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        return DB::table(self::TABLE_DETAIL)
            ->whereIn('project_id', $projectIds)
            ->where('company_id', $this->companyId())
            ->whereNull('deleted_at')
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Buat Project Detail untuk company pemilik, atau pulihkan yang terhapus. Idempoten: sudah ada = tidak membuat lagi.
     *
     * @return array{status: 'created'|'restored'|'exists', id: int}
     *
     * @throws ValidationException project terhapus / kode client sudah dipakai project lain
     */
    public function ensure(object $project): array
    {
        $companyId = $this->companyId();
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
        $companyId = $this->companyId();
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
            ->where('company_id', $this->companyId())
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
            ->where('company_id', $this->companyId())
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
     * cek kesesuaian 1:1 project master dengan detail company pemilik.
     *
     * @return array{
     *     company_total: int, company_deleted: int, project_total: int, project_deleted: int, detail_total: int,
     *     owner: array{id: int, code: ?string, name: ?string, active: bool},
     *     companies: array<int, array{id: int, code: string, name: string, detail: int, owner: bool}>,
     *     missing: int, missing_sample: array<int, string>, duplicate: int, orphan_company: int, orphan_project: int,
     *     consistent: bool
     * }
     */
    public function summary(): array
    {
        $owner = $this->companyId();

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
            ])->all();

        $projectsAktif = DB::table(self::TABLE_PROJECT)->whereNull('deleted_at');
        $withoutDetail = (clone $projectsAktif)->whereNotExists(function ($q) use ($owner) {
            $q->select(DB::raw(1))->from(self::TABLE_DETAIL.' as d')
                ->whereColumn('d.project_id', self::TABLE_PROJECT.'.id')
                ->where('d.company_id', $owner)->whereNull('d.deleted_at');
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
            'duplicate' => DB::table(self::TABLE_DETAIL)->where('company_id', $owner)->whereNull('deleted_at')
                ->groupBy('project_id')->havingRaw('count(*) > 1')->get(['project_id'])->count(),
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
