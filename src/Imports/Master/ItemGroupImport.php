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

class ItemGroupImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
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
                ->select('id', 'item_group_code', 'item_group_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($item) => strtoupper(trim((string) $item->item_group_code)) . '|' . strtoupper(trim((string) $item->item_group_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $code = trim((string) ($row['item_group_code'] ?? ''));
                $name = trim((string) ($row['item_group_name'] ?? ''));

                if (empty($code)) {
                    $this->error[] = "Row {$row_index} Item Group Code : field is required.";
                } elseif (empty($name)) {
                    $this->error[] = "Row {$row_index} Item Group Name : field is required.";
                } else {
                    $lookupKey = strtoupper($code) . '|' . strtoupper($name);
                    $exists = $this->existingItemGroups->get($lookupKey);

                    if (! $exists) {
                        $data = new ItemGroup();
                        $data->item_group_code = strtoupper($code);
                        $data->item_group_name = $name;
                        $data->save();

                        $this->existingItemGroups->put($lookupKey, (object) [
                            'id' => $data->id,
                            'item_group_code' => $data->item_group_code,
                            'item_group_name' => $data->item_group_name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$code} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Item Group already exists!";
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
