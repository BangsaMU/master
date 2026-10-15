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

class CategoryImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
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
                ->select('id', 'category_code', 'category_name')
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(fn ($cat) => strtoupper(trim((string) $cat->category_code)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);
            if ($rowCol->filter()->isNotEmpty()) {
                $categoryCode = trim((string) ($row['category_code'] ?? $row['categorycode'] ?? $row['code'] ?? ''));
                $categoryName = trim((string) ($row['category_name'] ?? $row['categoryname'] ?? $row['name'] ?? ''));
                $remark = trim((string) ($row['remark'] ?? $row['description'] ?? ''));
                $remark = empty($remark) ? null : $remark;

                if (empty($categoryCode)) {
                    $this->error[] = "Row {$row_index} Category Code : field is required.";
                } elseif (empty($categoryName)) {
                    $this->error[] = "Row {$row_index} Category Name : field is required.";
                } elseif (strlen($categoryCode) > 55) {
                    $this->error[] = "Row {$row_index}: Category Code exceeds maximum length of 55 characters.";
                } elseif (strlen($categoryName) > 110) {
                    $this->error[] = "Row {$row_index}: Category Name exceeds maximum length of 110 characters.";
                } else {
                    $codeUpper = strtoupper($categoryCode);
                    $exists = $this->existingCategories->get($codeUpper);

                    if (! $exists) {
                        $data = new Category;
                        $data->category_code = $codeUpper;
                        $data->category_name = $categoryName;
                        $data->remark = $remark;
                        $data->app_code = config('SsoConfig.main.APP_CODE', 'APP03');
                        $data->save();

                        $this->existingCategories->put($codeUpper, (object) [
                            'id' => $data->id,
                            'category_code' => $data->category_code,
                            'category_name' => $data->category_name,
                        ]);

                        $this->success[] = "Row {$row_index} : {$categoryCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Category Code '{$categoryCode}' already exists.";
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
