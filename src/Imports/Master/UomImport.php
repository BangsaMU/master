<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Uom;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UomImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private $error = [];

    private $success = [];

    protected ?Collection $existingCodes = null;

    protected ?Collection $existingNames = null;

    public function getImportTable(): string
    {
        return 'master_uom';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingCodes === null || $this->existingNames === null) {
            $existing = DB::table('master_uom')
                ->select('id', 'uom_code', 'uom_name')
                ->whereNull('deleted_at')
                ->get();

            $this->existingCodes = $existing->keyBy(fn ($uom) => strtoupper(trim((string) $uom->uom_code)));
            $this->existingNames = $existing->keyBy(fn ($uom) => strtoupper(trim((string) $uom->uom_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);
            if ($rowCol->filter()->isNotEmpty()) {
                $code = trim((string) ($row['uom_code'] ?? $row['uomcode'] ?? $row['uom_code'] ?? $row['code'] ?? ''));
                $name = trim((string) ($row['uom_name'] ?? $row['uomname'] ?? $row['uom_name'] ?? $row['name'] ?? ''));

                if (empty($code)) {
                    $this->error[] = "Row {$row_index} UOM Code : field is required.";
                } elseif (empty($name)) {
                    $this->error[] = "Row {$row_index} UOM Name : field is required.";
                } elseif (strlen($code) > 10) {
                    $this->error[] = "Row {$row_index}: UOM Code exceeds maximum length of 10 characters.";
                } elseif (strlen($name) > 25) {
                    $this->error[] = "Row {$row_index}: UOM Name exceeds maximum length of 25 characters.";
                } else {
                    $codeUpper = strtoupper($code);
                    $nameUpper = strtoupper($name);

                    $hasCode = $this->existingCodes->has($codeUpper);
                    $hasName = $this->existingNames->has($nameUpper);

                    if ($hasCode) {
                        $this->error[] = "Row {$row_index}: UOM Code '{$code}' already exists.";
                    } elseif ($hasName) {
                        $this->error[] = "Row {$row_index}: UOM Name '{$name}' already exists.";
                    } else {
                        $data = new Uom;
                        $data->uom_code = $codeUpper;
                        $data->uom_name = $name;
                        $data->app_code = config('SsoConfig.main.APP_CODE', 'APP03');
                        $data->save();

                        $obj = (object) [
                            'id' => $data->id,
                            'uom_code' => $data->uom_code,
                            'uom_name' => $data->uom_name,
                        ];

                        $this->existingCodes->put($codeUpper, $obj);
                        $this->existingNames->put($nameUpper, $obj);

                        $this->success[] = "Row {$row_index} : {$code} has been imported successfully.";
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
