<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Project;
use Bangsamu\Master\Services\ProjectOwnerDetail;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class ProjectImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private $error = [];

    private $success = [];

    /**
     * Company yang diberi project_detail untuk project baru (company pemilik, lalu company pembuat bila lewat
     * delegasi), diambil sekali per import: ProjectOwnerDetail::companyIdsForNewProject().
     *
     * @var list<int>|null
     */
    protected ?array $detailCompanyIds = null;

    protected ?Collection $existingProjects = null;

    public function getImportTable(): string
    {
        return 'master_project';
    }

    public function collection(Collection $rows): void
    {
        // Pre-load existing projects once per chunk (avoid N+1)
        if ($this->existingProjects === null) {
            $this->existingProjects = DB::table('master_project')
                ->select('id', 'project_code', 'deleted_at')
                ->get()
                ->keyBy(fn ($proj) => strtoupper(trim((string) $proj->project_code)));
        }

        // Company baris project_detail, sekali per import (avoid N+1)
        $ownerDetail = app(ProjectOwnerDetail::class);
        $this->detailCompanyIds ??= $ownerDetail->companyIdsForNewProject();

        // Pre-load existing project_detail for duplicate check (avoid N+1)
        $existingDetails = DB::table('master_project_detail')
            ->whereNull('deleted_at')
            ->select('project_id')
            ->pluck('project_id')
            ->flip();

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);
            if ($rowCol->filter()->isNotEmpty()) {
                $projectCode = strtoupper(trim((string) ($row['project_code'] ?? $row['projectcode'] ?? $row['Project Code'] ?? '')));
                $projectName = trim((string) ($row['project_name'] ?? $row['projectname'] ?? $row['Project Name'] ?? ''));

                if (empty($projectCode)) {
                    $this->error[] = "Row {$row_index}: Project Code is required.";
                    continue;
                }

                if (strlen($projectCode) > 10) {
                    $this->error[] = "Row {$row_index} [{$projectCode}]: Project Code exceeds maximum length of 10 characters.";
                    continue;
                }

                if (empty($projectName)) {
                    $this->error[] = "Row {$row_index} [{$projectCode}]: Project Name is required.";
                    continue;
                }

                if (strlen($projectName) > 100) {
                    $this->error[] = "Row {$row_index} [{$projectCode}]: Project Name exceeds maximum length of 100 characters.";
                    continue;
                }

                $exists = $this->existingProjects->get($projectCode);

                if ($exists) {
                    // Duplikat: skip dengan pesan jelas
                    $this->error[] = "Row {$row_index} [{$projectCode}]: Project already exists, skipped.";
                    continue;
                }

                $projectType  = strtoupper(trim((string) ($row['project_type'] ?? $row['projecttype'] ?? $row['Project Type'] ?? '')));
                $startDate    = $row['project_start_date'] ?? $row['projectstartdate'] ?? $row['Project Start Date'] ?? null;
                $completeDate = $row['project_complete_date'] ?? $row['projectcompletedate'] ?? $row['Project Complete Date'] ?? null;
                $remarks      = trim((string) ($row['project_remarks'] ?? $row['projectremarks'] ?? $row['Project Remarks'] ?? ''));

                try {
                    // Insert project baru
                    $data = new Project;
                    $data->project_code = $projectCode;
                    $data->project_name = $projectName;
                    $data->internal_external = ! empty($projectType) ? substr($projectType, 0, 1) : null;
                    $data->project_start_date = $this->fixDate($startDate);
                    $data->project_complete_date = $this->fixDate($completeDate);
                    $data->project_remarks = $remarks;
                    $data->save();

                    // Update cache agar chunk berikutnya tidak duplikat
                    $this->existingProjects->put($projectCode, (object) [
                        'id'           => $data->id,
                        'project_code' => $data->project_code,
                        'deleted_at'   => null,
                    ]);

                    // Auto-create project_detail (konsisten dengan store() manual): baris company pemilik, lalu
                    // company pembuat bila lewat delegasi. Hanya buat jika belum ada project_detail untuk project ini
                    if (! isset($existingDetails[$data->id])) {
                        foreach ($ownerDetail->rowsForNewProject($data, $this->detailCompanyIds) as $detailRow) {
                            try {
                                DB::table('master_project_detail')->insert($detailRow + [
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                // Update local cache
                                $existingDetails->put($data->id, true);
                            } catch (Throwable $e) {
                                // Gagal buat detail tidak menggagalkan import project
                            }
                        }
                    }

                    $this->success[] = "Row {$row_index} [{$projectCode}]: imported successfully.";
                } catch (Throwable $e) {
                    $this->error[] = "Row {$row_index} [{$projectCode}]: Failed to import — {$e->getMessage()}";
                }
            }
        }
    }

    public function fixDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            if (is_numeric($date)) {
                return Carbon::instance(Date::excelToDateTimeObject((int) $date))->format('Y-m-d');
            }

            return Carbon::parse((string) $date)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
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
