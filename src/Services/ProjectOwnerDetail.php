<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Models\MasterProjectDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan: master_project adalah milik utama company pemilik (Meindo, master_company.id 1) sehingga setiap project
 * aktif wajib punya TEPAT SATU Project Detail untuk company itu (project_code_client = project_code). Service ini
 * - mencari project yang detail company pemiliknya belum ada (tombol "Buat Detail" di daftar Project),
 * - membuat (atau memulihkan, bila detailnya terhapus) detail itu, dan
 * - menghitung ringkasan company vs project code client untuk card di atas daftar Project.
 * Hanya di hub (master-data): aplikasi klien memegang salinan tabel dan tidak mengubah detail milik Meindo.
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
            return ['status' => 'exists', 'id' => (int) $existing->id];
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
