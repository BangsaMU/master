<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\UoM;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UomImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingUoms = null;

    public function getImportTable(): string
    {
        return 'master_uom';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingUoms === null) {
            $this->existingUoms = DB::table('master_uom')
                ->select('id', 'uom_code', 'uom_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($uom) => strtoupper(trim((string) $uom->uom_code)) . '|' . strtoupper(trim((string) $uom->uom_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $code = trim((string) ($row['uom_code'] ?? ''));
                $name = trim((string) ($row['uom_name'] ?? ''));

                if (empty($code)) {
                    $this->error[] = "Row {$row_index} UOM Code : field is required.";
                } elseif (empty($name)) {
                    $this->error[] = "Row {$row_index} UOM Name : field is required.";
                } else {
                    $lookupKey = strtoupper($code) . '|' . strtoupper($name);
                    $exists = $this->existingUoms->get($lookupKey);

                    if (! $exists) {
                        $data = new UoM();
                        $data->uom_code = strtoupper($code);
                        $data->uom_name = $name;
                        $data->save();

                        $this->existingUoms->put($lookupKey, (object) [
                            'id' => $data->id,
                            'uom_code' => $data->uom_code,
                            'uom_name' => $data->uom_name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$code} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: UOM already exists!";
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
