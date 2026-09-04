<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Department;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DepartmentImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private array $error = [];
    private array $success = [];
    protected ?Collection $existingDepartments = null;

    public function getImportTable(): string
    {
        return 'master_department';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingDepartments === null) {
            $this->existingDepartments = DB::table('master_department')
                ->select('id', 'department_code', 'department_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($dept) => strtoupper(trim((string) $dept->department_code)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $code = trim((string) ($row['department_code'] ?? ''));
                $name = trim((string) ($row['department_name'] ?? ''));

                if (empty($code)) {
                    $this->error[] = "Row {$row_index} Department Code : field is required.";
                } elseif (empty($name)) {
                    $this->error[] = "Row {$row_index} Department Name : field is required.";
                } else {
                    $codeUpper = strtoupper($code);
                    $exists = $this->existingDepartments->get($codeUpper);

                    if (! $exists) {
                        $dept = Department::create([
                            'department_code' => $codeUpper,
                            'department_name' => $name,
                        ]);

                        $this->existingDepartments->put($codeUpper, (object) [
                            'id' => $dept->id,
                            'department_code' => $codeUpper,
                            'department_name' => $name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$code} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Department already exists!";
                    }
                }
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
