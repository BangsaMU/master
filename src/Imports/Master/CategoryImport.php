<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Category;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingCategories = null;

    public function getImportTable(): string
    {
        return 'master_category';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingCategories === null) {
            $this->existingCategories = DB::table('master_category')
                ->select('id', 'category_code', 'category_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($cat) => strtoupper(trim((string) $cat->category_code)) . '|' . strtoupper(trim((string) $cat->category_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $categoryCode = trim((string) ($row['category_code'] ?? ''));
                $categoryName = trim((string) ($row['category_name'] ?? ''));
                $remark = $row['remark'] ?? null;

                if (empty($categoryCode)) {
                    $this->error[] = "Row {$row_index} Category Code : field is required.";
                } elseif (empty($categoryName)) {
                    $this->error[] = "Row {$row_index} Category Name : field is required.";
                } else {
                    $lookupKey = strtoupper($categoryCode) . '|' . strtoupper($categoryName);
                    $exists = $this->existingCategories->get($lookupKey);

                    if (! $exists) {
                        $data = new Category();
                        $data->category_code = strtoupper($categoryCode);
                        $data->category_name = $categoryName;
                        $data->remark = $remark;
                        $data->save();

                        $this->existingCategories->put($lookupKey, (object) [
                            'id' => $data->id,
                            'category_code' => $data->category_code,
                            'category_name' => $data->category_name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$categoryCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Category already exists!";
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
