<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Location;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LocationImport implements ToCollection, WithHeadingRow, WithMultipleSheets, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingLocations = null;

    public function getImportTable(): string
    {
        return 'master_location';
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $rows)
    {
        if ($this->existingLocations === null) {
            $this->existingLocations = DB::table('master_location')
                ->select('id', 'loc_code', 'loc_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($loc) => strtoupper(trim((string) $loc->loc_code)) . '|' . strtoupper(trim((string) $loc->loc_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $locCode = trim((string) ($row['location_code'] ?? ''));
                $locName = trim((string) ($row['location_name'] ?? ''));

                if (empty($locCode)) {
                    $this->error[] = "Row {$row_index} Location Code : field is required.";
                } elseif (empty($locName)) {
                    $this->error[] = "Row {$row_index} Location Name : field is required.";
                } else {
                    $lookupKey = strtoupper($locCode) . '|' . strtoupper($locName);
                    $exists = $this->existingLocations->get($lookupKey);

                    if (! $exists) {
                        $data = new Location();
                        $data->loc_code = strtoupper($locCode);
                        $data->loc_name = $locName;
                        $data->save();

                        $this->existingLocations->put($lookupKey, (object) [
                            'id' => $data->id,
                            'loc_code' => $data->loc_code,
                            'loc_name' => $data->loc_name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$locCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Location already exists!";
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
