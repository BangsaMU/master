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
use Throwable;

class LocationImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets
{
    use HandlesBatchImportBroadcast;

    private array $error = [];

    private array $success = [];

    /** Pre-loaded existing locations keyed by "LOC_CODE|group_type" */
    protected ?Collection $existingLocations = null;

    protected array $allowedGroupTypes = [
        'office',
        'warehouse',
        'vendor',
        'clinic',
        'ohih',
        'mcu',
        'hrd',
    ];

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

    public function collection(Collection $rows): void
    {
        // Pre-load existing locations once per chunk (zero N+1 queries)
        if ($this->existingLocations === null) {
            $this->existingLocations = DB::table('master_location')
                ->whereNull('deleted_at')
                ->select('id', 'loc_code', 'loc_name', 'group_type', 'deleted_at')
                ->get()
                ->keyBy(fn ($loc) =>
                    strtoupper(trim((string) $loc->loc_code)) . '|' . strtolower(trim((string) ($loc->group_type ?? 'office')))
                );
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);

            if ($rowCol->filter()->isNotEmpty()) {
                $locCode  = strtoupper(trim((string) ($row['location_code'] ?? $row['loc_code'] ?? $row['Location Code'] ?? '')));
                $locName  = trim((string) ($row['location_name'] ?? $row['loc_name'] ?? $row['Location Name'] ?? ''));
                $rawGroup = strtolower(trim((string) ($row['group_type'] ?? $row['grouptype'] ?? $row['Group Type'] ?? '')));
                $groupType = empty($rawGroup) ? 'office' : $rawGroup;

                if (empty($locCode)) {
                    $this->error[] = "Row {$row_index}: Location Code is required.";
                    continue;
                }

                if (strlen($locCode) > 15) {
                    $this->error[] = "Row {$row_index} [{$locCode}]: Location Code exceeds maximum length of 15 characters.";
                    continue;
                }

                if (empty($locName)) {
                    $this->error[] = "Row {$row_index} [{$locCode}]: Location Name is required.";
                    continue;
                }

                if (strlen($locName) > 50) {
                    $this->error[] = "Row {$row_index} [{$locCode}]: Location Name exceeds maximum length of 50 characters.";
                    continue;
                }

                if (! in_array($groupType, $this->allowedGroupTypes, true)) {
                    $allowedStr = implode(', ', $this->allowedGroupTypes);
                    $this->error[] = "Row {$row_index} [{$locCode}]: Invalid Group Type [{$rawGroup}]. Allowed values: {$allowedStr}.";
                    continue;
                }

                $lookupKey = $locCode . '|' . $groupType;
                $exists = $this->existingLocations->get($lookupKey);

                if ($exists) {
                    $this->error[] = "Row {$row_index} [{$locCode} / {$groupType}]: Location already exists, skipped.";
                    continue;
                }

                try {
                    $data = new Location;
                    $data->loc_code = $locCode;
                    $data->loc_name = $locName;
                    $data->group_type = $groupType;
                    $data->save();

                    $this->existingLocations->put($lookupKey, (object) [
                        'id'         => $data->id,
                        'loc_code'   => $data->loc_code,
                        'loc_name'   => $data->loc_name,
                        'group_type' => $data->group_type,
                        'deleted_at' => null,
                    ]);

                    $this->success[] = "Row {$row_index} [{$locCode} / {$groupType}]: imported successfully.";
                } catch (Throwable $e) {
                    $this->error[] = "Row {$row_index} [{$locCode}]: Failed to import — {$e->getMessage()}";
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
