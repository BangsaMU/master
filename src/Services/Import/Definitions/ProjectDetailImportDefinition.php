<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import\Definitions;

use Bangsamu\Master\Models\ProjectDetail;
use Bangsamu\Master\Services\Import\MasterImportDefinition;
use Bangsamu\Master\Services\ProjectDetailNumberReset;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unik: project + project_code_client + company. Baris baru juga ditolak bila Project Code Client-nya sudah dipakai
 * project lain oleh company mana pun (baris aktif / Project Code di DB, atau baris baru lain di file yang sama), lewat
 * ProjectOwnerDetail::codeUsageMessage dengan pesan yang sama seperti form Project Detail (satu kode satu project).
 *
 * Kolom opsional Yearly Reset (number_reset_yearly, ProjectDetailNumberReset): Y/Ya = reset nomor tahunan, N/Tidak =
 * tidak, kosong = baris lama tidak diubah (baris baru = tidak). Hanya admin Master Data; dari user lain diabaikan.
 */
class ProjectDetailImportDefinition extends MasterImportDefinition
{
    protected Collection $projects;

    protected Collection $companies;

    protected Collection $existing;

    /** @var array<string, string> Project Code Client baris baru di file ini => Project Code project-nya */
    protected array $newCodes = [];

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
        $fields = [
            'project_code' => 'Project Code',
            'project_code_client' => 'Project Code Client',
            'project_name_client' => 'Project Name Client',
            'company_code' => 'Company Code',
        ];
        if ($this->numberReset()->available()) {
            $fields[ProjectDetailNumberReset::COLUMN] = ProjectDetailNumberReset::LABEL;
        }

        return $fields;
    }

    public function updatableFields(): array
    {
        return $this->numberReset()->canEdit()
            ? ['project_name_client', ProjectDetailNumberReset::COLUMN]
            : ['project_name_client'];
    }

    protected function numberReset(): ProjectDetailNumberReset
    {
        return app(ProjectDetailNumberReset::class);
    }

    public function normalize(array $raw): array
    {
        return [
            'project_code' => self::upper($this->str($raw, ['project_code', 'projectcode'])),
            'project_code_client' => self::upper($this->str($raw, ['project_code_client', 'projectcodeclient'])),
            'project_name_client' => $this->str($raw, ['project_name_client', 'projectnameclient']),
            'company_code' => self::upper($this->str($raw, ['company_code', 'companycode'])),
            // Ya / Tidak / '' (kosong); isi tak dikenali disimpan apa adanya supaya validate() bisa menolaknya
            ProjectDetailNumberReset::COLUMN => $this->numberResetValue(
                $this->str($raw, ['yearly_reset', 'yearlyreset', 'number_reset_yearly', 'numberresetyearly', 'reset_tahunan', 'resettahunan'])
            ),
        ];
    }

    protected function numberResetValue(string $raw): string
    {
        return ProjectDetailNumberReset::normalizeImportValue($raw) ?? trim($raw);
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
            ! in_array($data[ProjectDetailNumberReset::COLUMN] ?? '', ['', ProjectDetailNumberReset::YES, ProjectDetailNumberReset::NO], true)
                => ProjectDetailNumberReset::LABEL." '".$data[ProjectDetailNumberReset::COLUMN]."' tidak dikenali: isi Y atau N (kosong = tidak diubah).",
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

        $this->newCodes = [];
        $this->existing = collect();
        foreach ($this->projects->pluck('id')->chunk(1000) as $ids) {
            $select = ['id', 'project_id', 'project_code_client', 'project_name_client', 'company_id', 'deleted_at'];
            if ($this->numberReset()->available()) {
                $select[] = ProjectDetailNumberReset::COLUMN;
            }
            DB::table('master_project_detail')
                ->select($select)
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
                ProjectDetailNumberReset::COLUMN => $this->numberReset()->isYearly($row) ? ProjectDetailNumberReset::YES : ProjectDetailNumberReset::NO,
            ],
        ] : null;
    }

    public function notes(array $data): array
    {
        if (($data[ProjectDetailNumberReset::COLUMN] ?? '') !== '' && ! $this->numberReset()->canEdit()) {
            return [ProjectDetailNumberReset::LABEL.' diabaikan: hanya admin Master Data yang bisa mengubah.'];
        }

        return [];
    }

    public function check(array $data, ?array $existing): ?string
    {
        if (! $this->projects->has($data['project_code'])) {
            return "Project Code '{$data['project_code']}' tidak ditemukan di master project.";
        }
        if (! $this->companies->has($data['company_code'])) {
            return "Company Code '{$data['company_code']}' tidak ditemukan di master company.";
        }

        // baris pemilik project: kode/nama mengikuti Project, tidak diubah lewat import Project Detail
        if ($existing !== null && ($row = DB::table('master_project_detail')->where('id', $existing['id'])->first()) !== null) {
            return app(ProjectOwnerDetail::class)->detailChangeDenial($row, [
                'project_code_client' => $data['project_code_client'],
                'project_name_client' => $data['project_name_client'],
            ]);
        }

        return $existing === null ? $this->newCodeError($data) : null;
    }

    /**
     * Baris baru: satu kode satu project (company mana pun). Kode itu belum dipakai project lain, baik di DB (baris detail
     * aktif / Project Code project aktif, ProjectOwnerDetail::codeUsageMessage) maupun di baris baru lain file ini.
     * Beberapa company di project yang sama boleh memakai kode yang sama. Baris lama tidak dicek: kunci import memuat
     * kodenya, jadi kodenya tidak berubah lewat import.
     */
    protected function newCodeError(array $data): ?string
    {
        $projectId = (int) $this->projects->get($data['project_code'])->id;
        $usage = app(ProjectOwnerDetail::class)->codeUsageMessage($data['project_code_client'], $projectId);
        if ($usage !== null) {
            return $usage;
        }

        $claimedBy = $this->newCodes[$data['project_code_client']] ?? null;
        if ($claimedBy !== null && $claimedBy !== $data['project_code']) {
            return 'Kode '.$data['project_code_client'].' sudah dipakai sebagai Project Code Client project '.$claimedBy
                .' di baris lain file ini: satu kode hanya boleh menunjuk satu project (nomor dokumen akan bentrok).';
        }
        $this->newCodes[$data['project_code_client']] = $data['project_code'];

        return null;
    }

    public function create(array $data): array
    {
        $model = new ProjectDetail;
        $model->project_id = $this->projects->get($data['project_code'])->id;
        $model->project_code_client = $data['project_code_client'];
        $model->project_name_client = $data['project_name_client'];
        $model->company_id = $this->companies->get($data['company_code'])->id;
        if ($this->numberReset()->canEdit() && ($data[ProjectDetailNumberReset::COLUMN] ?? '') !== '') {
            $model->{ProjectDetailNumberReset::COLUMN} = ProjectDetailNumberReset::toColumnValue($data[ProjectDetailNumberReset::COLUMN]);
        }

        return [$this->saveNew($model)];
    }

    public function update(int $id, array $changes, array $data): array
    {
        $attributes = array_map(fn ($c) => $c['new'], $changes);
        if (array_key_exists(ProjectDetailNumberReset::COLUMN, $attributes)) {
            // updatableFields() hanya memuat kolom ini untuk admin; nilai file Ya/Tidak → 1/0
            $attributes[ProjectDetailNumberReset::COLUMN] = ProjectDetailNumberReset::toColumnValue($attributes[ProjectDetailNumberReset::COLUMN]);
        }
        $log = $this->saveChanges(ProjectDetail::findOrFail($id), $attributes);

        return $log ? [$log] : [];
    }
}
