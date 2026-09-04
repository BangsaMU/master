<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\MasterProject as ModelsMasterProject;
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

class ProjectImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingProjects = null;

    public function getImportTable(): string
    {
        return 'master_project';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingProjects === null) {
            $this->existingProjects = DB::table('master_project')
                ->select('id', 'project_code', 'deleted_at')
                ->get()
                ->keyBy(fn ($proj) => strtoupper(trim((string) $proj->project_code)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $projectCode = trim((string) ($row['project_code'] ?? ''));
                $projectName = trim((string) ($row['project_name'] ?? ''));

                if (empty($projectCode)) {
                    $this->error[] = "Row {$row_index} project Code : field is required.";
                } elseif (empty($projectName)) {
                    $this->error[] = "Row {$row_index} project Name : field is required.";
                } else {
                    $codeUpper = strtoupper($projectCode);
                    $exists = $this->existingProjects->get($codeUpper);

                    if (! $exists) {
                        $data = new ModelsMasterProject();
                        $data->project_code = $codeUpper;
                        $data->project_name = $projectName;
                        $data->internal_external = $row['project_type'] ?? null;
                        $data->project_start_date = $this->fixDate($row['project_start_date'] ?? null);
                        $data->project_complete_date = $this->fixDate($row['project_complete_date'] ?? null);
                        $data->save();

                        $this->existingProjects->put($codeUpper, (object) [
                            'id' => $data->id,
                            'project_code' => $data->project_code,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$projectCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: project already exists!";
                    }
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
