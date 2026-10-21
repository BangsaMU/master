<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unik: project + project_code_client + company.
 */
class ProjectDetailImportDefinition extends MasterImportDefinition
{
    protected Collection $projects;

    protected Collection $companies;

    protected Collection $existing;

    public function entity(): string
    {
        return 'project-detail';
    }

    public function label(): string
    {
        return 'Project Detail';
    }

    public function table(): string
    {
        return 'master_project_detail';
    }

    public function permission(): string
    {
        return 'project_detail';
    }

    public function keyLabel(): string
    {
        return 'Project Code + Project Code Client + Company Code';
    }

    public function fields(): array
    {
        return [
            'project_code' => 'Project Code',
            'project_code_client' => 'Project Code Client',
            'project_name_client' => 'Project Name Client',
            'company_code' => 'Company Code',
        ];
    }

    public function updatableFields(): array
    {
        return ['project_name_client'];
    }

    public function normalize(array $raw): array
    {
        return [
            'project_code' => self::upper($this->str($raw, ['project_code', 'projectcode'])),
            'project_code_client' => self::upper($this->str($raw, ['project_code_client', 'projectcodeclient'])),
            'project_name_client' => $this->str($raw, ['project_name_client', 'projectnameclient']),
            'company_code' => self::upper($this->str($raw, ['company_code', 'companycode'])),
        ];
    }

    public function validate(array $data): ?string
    {
        return match (true) {
            $data['project_code'] === '' => 'Project Code wajib diisi.',
            $data['project_code_client'] === '' => 'Project Code Client wajib diisi.',
            mb_strlen($data['project_code_client']) > 10 => 'Project Code Client melebihi 10 karakter.',
            $data['project_name_client'] === '' => 'Project Name Client wajib diisi.',
            mb_strlen($data['project_name_client']) > 100 => 'Project Name Client melebihi 100 karakter.',
            $data['company_code'] === '' => 'Company Code wajib diisi.',
            default => null,
        };
    }

    public function key(array $data): string
    {
        return $data['project_code'].'|'.$data['project_code_client'].'|'.$data['company_code'];
    }

    public function keyDisplay(array $data): string
    {
        return $data['project_code'].' / '.$data['project_code_client'].' / '.$data['company_code'];
    }

    public function prepare(array $rows): void
    {
        $this->projects = $this->fetchByValues('master_project', 'project_code', array_column($rows, 'project_code'), ['id', 'project_code', 'deleted_at'])
            ->whereNull('deleted_at')
            ->keyBy(fn ($row) => self::upper($row->project_code));
        $this->companies = $this->fetchByValues('master_company', 'company_code', array_column($rows, 'company_code'), ['id', 'company_code', 'deleted_at'])
            ->whereNull('deleted_at')
            ->keyBy(fn ($row) => self::upper($row->company_code));

        $projectCodes = $this->projects->mapWithKeys(fn ($p) => [$p->id => self::upper($p->project_code)]);
        $companyCodes = $this->companies->mapWithKeys(fn ($c) => [$c->id => self::upper($c->company_code)]);

        $this->existing = collect();
        foreach ($this->projects->pluck('id')->chunk(1000) as $ids) {
            DB::table('master_project_detail')
                ->select('id', 'project_id', 'project_code_client', 'project_name_client', 'company_id', 'deleted_at')
                ->whereIn('project_id', $ids->all())
                ->get()
                ->each(function ($row) use ($projectCodes, $companyCodes) {
                    if (isset($companyCodes[$row->company_id])) {
                        $key = $projectCodes[$row->project_id].'|'.self::upper($row->project_code_client).'|'.$companyCodes[$row->company_id];
                        // utamakan data aktif bila ada duplikat soft delete
                        if (! $this->existing->has($key) || $row->deleted_at === null) {
                            $this->existing->put($key, $row);
                        }
                    }
                });
        }
    }

    public function existing(array $data): ?array
    {
        $row = $this->existing->get($this->key($data));

        return $row ? [
            'id' => (int) $row->id,
            'trashed' => $row->deleted_at !== null,
            'owner' => null,
            'values' => [
                'project_code' => $data['project_code'],
                'project_code_client' => (string) $row->project_code_client,
                'project_name_client' => (string) $row->project_name_client,
                'company_code' => $data['company_code'],
            ],
        ] : null;
    }

    public function check(array $data, ?array $existing): ?string
    {
        if (! $this->projects->has($data['project_code'])) {
            return "Project Code '{$data['project_code']}' tidak ditemukan di master project.";
        }
        if (! $this->companies->has($data['company_code'])) {
            return "Company Code '{$data['company_code']}' tidak ditemukan di master company.";
        }

        return null;
    }

    public function create(array $data): array
    {
        $model = new ProjectDetail;
        $model->project_id = $this->projects->get($data['project_code'])->id;
        $model->project_code_client = $data['project_code_client'];
        $model->project_name_client = $data['project_name_client'];
        $model->company_id = $this->companies->get($data['company_code'])->id;

        return [$this->saveNew($model)];
    }

    public function update(int $id, array $changes, array $data): array
    {
        $log = $this->saveChanges(ProjectDetail::findOrFail($id), array_map(fn ($c) => $c['new'], $changes));

        return $log ? [$log] : [];
    }
}
