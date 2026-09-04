<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\MasterVendor;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VendorImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingVendors = null;

    public function getImportTable(): string
    {
        return 'master_vendor';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingVendors === null) {
            $this->existingVendors = DB::table('master_vendor')
                ->select('id', 'vendor_code', 'vendor_description', 'deleted_at')
                ->get()
                ->keyBy(fn ($v) => strtoupper(trim((string) $v->vendor_code)) . '|' . strtoupper(trim((string) $v->vendor_description)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $vendorCode = trim((string) ($row['vendor_code'] ?? ''));
                $vendorDesc = trim((string) ($row['vendor_description'] ?? ''));

                if (empty($vendorCode)) {
                    $this->error[] = "Row {$row_index} Vendor Code : field is required.";
                } elseif (empty($vendorDesc)) {
                    $this->error[] = "Row {$row_index} Vendor Description : field is required.";
                } else {
                    $lookupKey = strtoupper($vendorCode) . '|' . strtoupper($vendorDesc);
                    $exists = $this->existingVendors->get($lookupKey);

                    if (! $exists) {
                        $data = MasterVendor::create([
                            'vendor_code' => strtoupper($vendorCode),
                            'vendor_description' => $vendorDesc,
                        ]);

                        $this->existingVendors->put($lookupKey, (object) [
                            'id' => $data->id,
                            'vendor_code' => $data->vendor_code,
                            'vendor_description' => $data->vendor_description,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$vendorCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: Vendor already exists!";
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
