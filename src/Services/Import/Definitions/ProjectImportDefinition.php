<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Unik: project_code (project_name juga unik).
 * Project baru otomatis dibuatkan project_detail untuk company pemilik (PROJECT_OWNER_COMPANY_ID), ditambah company
 * pembuat bila import dijalankan lewat session delegasi company lain; project lama yang namanya diubah ikut mengubah
 * baris cermin company pemilik (ProjectOwnerDetail).
 * Kolom opsional Owner Company Code: kosong = perilaku itu; kode company lain = project BARU milik company itu (satu
 * baris detail company itu saja, aturan sama dengan form: ProjectOwnerDetail::newProjectPlan). Project lama: harus sama
 * dengan pemilik sekarang (pemilik hanya dipindah lewat Edit Project → Pindah pemilik).
 */
class ProjectImportDefinition extends MasterImportDefinition
{
    protected Collection $existing;

    protected Collection $existingNames;

    /** @var list<int>|null company baris project_detail untuk project baru, diambil sekali per proses import */
    protected ?array $detailCompanyIds = null;

    /** company aktif per kode (kolom Owner Company Code) */
    protected Collection $ownerCompanies;

    public function entity(): string
    {
        return 'project';
    }

    public function label(): string
    {
        return 'Project';
    }

    public function table(): string
    {
        return 'master_project';
    }

    public function permission(): string
    {
        return 'project';
    }

    public function keyLabel(): string
    {
        return 'Project Code';
    }

    public function masterSyncKey(): ?string
    {
        return 'project_code';
    }

    public function fields(): array
    {
        return [
            'project_code' => 'Project Code',
            'project_name' => 'Project Name',
            'internal_external' => 'Project Type',
            'project_start_date' => 'Start Date',
            'project_complete_date' => 'Complete Date',
            'project_remarks' => 'Remarks',
        ];
    }

    public function updatableFields(): array
    {
        return ['project_name', 'internal_external', 'project_start_date', 'project_complete_date', 'project_remarks'];
    }

    public function normalize(array $raw): array
    {
        $type = self::upper($this->str($raw, ['project_type', 'projecttype', 'internal_external']));

        return [
            'project_code' => self::upper($this->str($raw, ['project_code', 'projectcode'])),
            'project_name' => $this->str($raw, ['project_name', 'projectname']),
            'internal_external' => $type !== '' ? substr($type, 0, 1) : '',
            'project_start_date' => $this->date($raw['project_start_date'] ?? $raw['projectstartdate'] ?? null),
            'project_complete_date' => $this->date($raw['project_complete_date'] ?? $raw['projectcompletedate'] ?? null),
            'project_remarks' => $this->str($raw, ['project_remarks', 'projectremarks', 'remarks']),
            // bukan kolom master_project: dibaca terpisah (ownerPlan), tidak ikut fields()
            'owner_company_code' => self::upper($this->str($raw, ['owner_company_code', 'ownercompanycode', 'owner_company'])),
        ];
    }

    public function validate(array $data): ?string
    {
        return match (true) {
            $data['project_code'] === '' => 'Project Code wajib diisi.',
            mb_strlen($data['project_code']) > 10 => 'Project Code melebihi 10 karakter.',
            $data['project_name'] === '' => 'Project Name wajib diisi.',
            mb_strlen($data['project_name']) > 100 => 'Project Name melebihi 100 karakter.',
            mb_strlen($data['project_remarks']) > 255 => 'Project Remarks melebihi 255 karakter.',
            default => null,
        };
    }

    public function key(array $data): string
    {
        return $data['project_code'];
    }

    public function prepare(array $rows): void
    {
        $select = ['id', 'project_code', 'project_name', 'internal_external', 'project_start_date', 'project_complete_date', 'project_remarks', 'deleted_at'];
        $this->existing = $this->fetchByValues('master_project', 'project_code', array_column($rows, 'project_code'), $select)
            ->keyBy(fn ($row) => self::upper($row->project_code));
        $this->existingNames = $this->fetchByValues('master_project', 'project_name', array_column($rows, 'project_name'), ['id', 'project_code', 'project_name'])
            ->keyBy(fn ($row) => self::upper($row->project_name));
        $this->ownerCompanies = $this->fetchByValues('master_company', 'company_code', array_column($rows, 'owner_company_code'), ['id', 'company_code', 'deleted_at'])
            ->whereNull('deleted_at')
            ->keyBy(fn ($row) => self::upper($row->company_code));
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));
        if (! $row) {
            return null;
        }

        $values = [];
        foreach (array_keys($this->fields()) as $field) {
            $values[$field] = (string) ($row->{$field} ?? '');
        }
        foreach (['project_start_date', 'project_complete_date'] as $field) {
            $values[$field] = $values[$field] !== '' ? substr($values[$field], 0, 10) : '';
        }

        return ['id' => (int) $row->id, 'trashed' => $row->deleted_at !== null, 'owner' => null, 'values' => $values];
    }

    public function check(array $data, ?array $existing): ?string
    {
        $owner = $this->existingNames->get(self::upper($data['project_name']));
        if ($owner && self::upper($owner->project_code) !== $data['project_code']) {
            return "Project Name '{$data['project_name']}' sudah dipakai Project Code '{$owner->project_code}'.";
        }
        // project baru: satu kode satu project, kode (= kode baris pemilik) belum dipakai baris detail company mana pun.
        // Project lama tidak dicek: kunci import = Project Code, jadi kodenya tidak berubah lewat import.
        if ($existing === null && ($usage = app(ProjectOwnerDetail::class)->codeUsageMessage($data['project_code'])) !== null) {
            return $usage;
        }

        return $this->ownerError($data, $existing);
    }

    /** Kolom Owner Company Code: company harus aktif; project lama tidak dipindah pemiliknya; project baru = aturan form. */
    protected function ownerError(array $data, ?array $existing): ?string
    {
        $code = $data['owner_company_code'] ?? '';
        if ($code === '') {
            return null;
        }
        $company = $this->ownerCompanies->get($code);
        if ($company === null) {
            return "Owner Company Code '{$code}' tidak ditemukan di master company (atau sudah dihapus).";
        }

        $ownerDetail = app(ProjectOwnerDetail::class);
        if ($existing !== null) {
            return (int) $company->id === $ownerDetail->ownerCompanyIdOf((int) $existing['id'])
                ? null
                : "Pemilik project {$data['project_code']} tidak diubah lewat import: pakai Edit Project → Pindah pemilik.";
        }

        try {
            $ownerDetail->newProjectPlan((int) $company->id); // kode bebas sudah dicek di check()
        } catch (ValidationException $e) {
            return $e->validator->errors()->first();
        }

        return null;
    }

    /**
     * Pemilik + company baris detail project baru dari kolom Owner Company Code (kosong = perilaku lama, diambil sekali
     * per import).
     *
     * @return array{owner: ?int, companies: list<int>, other: bool}
     */
    protected function ownerPlan(array $data): array
    {
        $ownerDetail = app(ProjectOwnerDetail::class);
        $company = ($data['owner_company_code'] ?? '') !== '' ? $this->ownerCompanies->get($data['owner_company_code']) : null;
        if ($company !== null) {
            $plan = $ownerDetail->newProjectPlan((int) $company->id); // sudah dicek di check(); throw = baris gagal
            if ($plan['other']) {
                return $plan;
            }
        }

        $this->detailCompanyIds ??= $ownerDetail->companyIdsForNewProject();

        return ['owner' => $ownerDetail->companyIdForNewProject(), 'companies' => $this->detailCompanyIds, 'other' => false];
    }

    public function create(array $data): array
    {
        $ownerDetail = app(ProjectOwnerDetail::class);
        $plan = $this->ownerPlan($data);

        $model = new Project;
        foreach (array_keys($this->fields()) as $field) {
            $model->{$field} = $data[$field] !== '' ? $data[$field] : null;
        }
        if ($ownerDetail->hasOwnerColumn() && $plan['owner'] !== null) {
            $model->{ProjectOwnerDetail::OWNER_COLUMN} = (int) $plan['owner'];
        }
        $logs = [$this->saveNew($model)];

        foreach ($ownerDetail->rowsForNewProject($model, $plan['companies']) as $row) {
            $detail = new ProjectDetail;
            foreach ($row as $field => $value) {
                $detail->{$field} = $value;
            }
            $logs[] = $this->saveNew($detail);
        }

        return $logs;
    }

    public function update(int $id, array $changes, array $data): array
    {
        $project = Project::findOrFail($id);
        $oldCode = (string) $project->project_code;

        $log = $this->saveChanges($project, array_map(fn ($c) => $c['new'], $changes));
        if (! $log) {
            return [];
        }
        $logs = [$log];

        // cermin 1:1: baris detail company pemilik ikut kode/nama baru. Disimpan diam-diam seperti baris import
        // lain (log ditulis engine, lengkap dengan alasan update); disebar setelah commit lewat syncToMaster().
        if ($project->wasChanged(['project_code', 'project_name'])) {
            foreach (app(ProjectOwnerDetail::class)->pendingMirror($project, $oldCode) as $detail) {
                if ($mirrorLog = $this->saveChanges($detail, [])) {
                    $logs[] = $mirrorLog;
                }
            }
        }

        return $logs;
    }

    /** Baris cermin pemilik yang ikut berubah disebar seperti edit manual Project Detail (broadcast + callback DB master). */
    public function syncToMaster(Model $model): void
    {
        if ($model instanceof ProjectDetail && ! $model->wasRecentlyCreated) {
            app(ProjectOwnerDetail::class)->publish($model);

            return;
        }

        parent::syncToMaster($model);
    }

    protected function date(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        try {
            return is_numeric($value)
                ? Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d')
                : Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }
}
