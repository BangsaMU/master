<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\ItemGroup;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemGroupImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private $error = [];

    private $success = [];

    protected ?Collection $existingItemGroups = null;

    public function getImportTable(): string
    {
        return 'master_item_group';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingItemGroups === null) {
            $this->existingItemGroups = DB::table('master_item_group')
                ->select('id', 'item_group_code', 'item_group_name')
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(fn ($item) => strtoupper(trim((string) $item->item_group_code)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);
            if ($rowCol->filter()->isNotEmpty()) {
                $code = trim((string) ($row['item_group_code'] ?? $row['itemgroupcode'] ?? $row['group_code'] ?? $row['item_group'] ?? ''));
                $name = trim((string) ($row['item_group_name'] ?? $row['itemgroupname'] ?? $row['group_name'] ?? ''));

                if (empty($code)) {
                    $this->error[] = "Row {$row_index} Item Group Code : field is required.";
                } elseif (empty($name)) {
                    $this->error[] = "Row {$row_index} Item Group Name : field is required.";
                } elseif (strlen($code) > 25) {
                    $this->error[] = "Row {$row_index}: Item Group Code exceeds maximum length of 25 characters.";
                } elseif (strlen($name) > 55) {
                    $this->error[] = "Row {$row_index}: Item Group Name exceeds maximum length of 55 characters.";
                } else {
                    $codeUpper = strtoupper($code);
                    $exists = $this->existingItemGroups->get($codeUpper);

                    if (! $exists) {
                        $data = new ItemGroup;
                        $data->item_group_code = $codeUpper;
                        $data->item_group_name = $name;
                        $data->app_code = config('SsoConfig.main.APP_CODE', 'APP03');
                        $data->save();

                        $this->existingItemGroups->put($codeUpper, (object) [
                            'id' => $data->id,
                            'item_group_code' => $data->item_group_code,
                            'item_group_name' => $data->item_group_name,
                        ]);

                        $this->success[] = "Row {$row_index} : {$code} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Item Group Code '{$code}' already exists.";
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
