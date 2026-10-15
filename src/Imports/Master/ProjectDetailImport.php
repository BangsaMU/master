<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Import Project Detail dari Excel/CSV.
 *
 * Kolom template yang diharapkan:
 *  - project_code        : kode project induk (wajib, harus ada di master_project)
 *  - project_code_client : kode project dari sisi client (wajib)
 *  - project_name_client : nama project dari sisi client (wajib)
 *  - company_code        : kode company (wajib, harus ada di master_company)
 *
 * Validasi unik: kombinasi (project_id + project_code_client + company_id)
 */
class ProjectDetailImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private array $error = [];

    private array $success = [];

    /** Pre-loaded projects keyed by uppercase project_code → {id, project_code} */
    protected ?Collection $projectLookup = null;

    /** Pre-loaded companies keyed by uppercase company_code → {id, company_code} */
    protected ?Collection $companyLookup = null;

    /** Pre-loaded existing details keyed by "project_id|project_code_client_upper|company_id" */
    protected ?Collection $existingDetails = null;

    public function getImportTable(): string
    {
        return 'master_project_detail';
    }

    public function collection(Collection $rows): void
    {
        // --- Pre-load lookups once per chunk (zero N+1 queries dalam loop) ---

        if ($this->projectLookup === null) {
            $this->projectLookup = DB::table('master_project')
                ->whereNull('deleted_at')
                ->select('id', 'project_code')
                ->get()
                ->keyBy(fn ($p) => strtoupper(trim((string) $p->project_code)));
        }

        if ($this->companyLookup === null) {
            $this->companyLookup = DB::table('master_company')
                ->whereNull('deleted_at')
                ->select('id', 'company_code')
                ->get()
                ->keyBy(fn ($c) => strtoupper(trim((string) $c->company_code)));
        }

        if ($this->existingDetails === null) {
            $this->existingDetails = DB::table('master_project_detail')
                ->whereNull('deleted_at')
                ->select('project_id', 'project_code_client', 'company_id')
                ->get()
                ->keyBy(fn ($d) =>
                    $d->project_id . '|' . strtoupper(trim((string) $d->project_code_client)) . '|' . $d->company_id
                );
        }

        // --- Proses baris per baris ---

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);

            // Skip baris kosong
            if ($rowCol->filter()->isEmpty()) {
                continue;
            }

            $projectCode    = strtoupper(trim((string) ($row['project_code'] ?? $row['projectcode'] ?? $row['Project Code'] ?? '')));
            $codeClient     = strtoupper(trim((string) ($row['project_code_client'] ?? $row['projectcodeclient'] ?? $row['Project Code Client'] ?? '')));
            $nameClient     = trim((string) ($row['project_name_client'] ?? $row['projectnameclient'] ?? $row['Project Name Client'] ?? ''));
            $companyCode    = strtoupper(trim((string) ($row['company_code'] ?? $row['companycode'] ?? $row['Company Code'] ?? '')));

            // Validasi kolom wajib
            if (empty($projectCode)) {
                $this->error[] = "Row {$row_index}: Project Code is required.";
                continue;
            }
            if (empty($codeClient)) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}]: Project Code Client is required.";
                continue;
            }
            if (strlen($codeClient) > 10) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}]: Project Code Client exceeds maximum length of 10 characters.";
                continue;
            }
            if (empty($nameClient)) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}]: Project Name Client is required.";
                continue;
            }
            if (strlen($nameClient) > 100) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}]: Project Name Client exceeds maximum length of 100 characters.";
                continue;
            }
            if (empty($companyCode)) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}]: Company Code is required.";
                continue;
            }

            // Validasi relasi: project harus ada
            $project = $this->projectLookup->get($projectCode);
            if (! $project) {
                $this->error[] = "Row {$row_index}: Project Code [{$projectCode}] not found in master_project.";
                continue;
            }

            // Validasi relasi: company harus ada
            $company = $this->companyLookup->get($companyCode);
            if (! $company) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}]: Company Code [{$companyCode}] not found in master_company.";
                continue;
            }

            // Validasi unik: project_id + project_code_client + company_id
            $uniqueKey = $project->id . '|' . $codeClient . '|' . $company->id;
            if ($this->existingDetails->has($uniqueKey)) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}, Company: {$companyCode}]: already exists, skipped.";
                continue;
            }

            // Insert
            try {
                DB::table('master_project_detail')->insert([
                    'project_id'          => $project->id,
                    'project_code_client' => $codeClient,
                    'project_name_client' => $nameClient,
                    'company_id'          => $company->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // Update cache agar chunk berikutnya tidak duplikat
                $this->existingDetails->put($uniqueKey, true);

                $this->success[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}, Company: {$companyCode}]: imported successfully.";
            } catch (Throwable $e) {
                $this->error[] = "Row {$row_index} [Project: {$projectCode}, Client: {$codeClient}]: Failed to insert — {$e->getMessage()}";
            }
        }
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getSuccess(): array
    {
        return $this->success;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
