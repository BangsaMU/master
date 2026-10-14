<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Services\VendorService;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class VendorImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private array $error = [];

    private array $success = [];

    protected ?Collection $existingVendors = null;

    protected ?VendorService $vendorService = null;

    public function __construct(?VendorService $vendorService = null)
    {
        $this->vendorService = $vendorService ?? app(VendorService::class);
    }

    public function getImportTable(): string
    {
        return 'master_vendor';
    }

    public function collection(Collection $rows): void
    {
        if ($this->existingVendors === null) {
            $this->existingVendors = DB::table('master_vendor')
                ->whereNull('deleted_at')
                ->pluck('vendor_code')
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->flip();
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;
            $hasData = ! empty(array_filter($rowArray, fn ($val) => $val !== null && trim((string) $val) !== ''));

            if ($hasData) {
                $vendorCode = trim((string) ($rowArray['vendor_code'] ?? ''));
                $vendorDesc = trim((string) ($rowArray['vendor_name'] ?? $rowArray['vendor_description'] ?? ''));
                $vendorAddress = trim((string) ($rowArray['vendor_address'] ?? ''));
                $vendorPhone = trim((string) ($rowArray['vendor_phone'] ?? ''));
                $vendorFax = trim((string) ($rowArray['vendor_fax'] ?? ''));
                $vendorEmail = trim((string) ($rowArray['vendor_email'] ?? ''));

                $contactName = trim((string) ($rowArray['vendor_contact_name'] ?? $rowArray['contact_name'] ?? ''));
                $contactPhone = trim((string) ($rowArray['vendor_contact_phone'] ?? $rowArray['contact_phone'] ?? ''));
                $contactEmail = trim((string) ($rowArray['vendor_contact_email'] ?? $rowArray['contact_email'] ?? ''));
                $contactFax = trim((string) ($rowArray['vendor_contact_fax'] ?? $rowArray['contact_fax'] ?? ''));

                if (empty($vendorCode)) {
                    $this->error[] = "Row {$row_index} Vendor Code : field is required.";
                } elseif (empty($vendorDesc)) {
                    $this->error[] = "Row {$row_index} Vendor Description : field is required.";
                } elseif (empty($vendorAddress)) {
                    $this->error[] = "Row {$row_index} Vendor Address : field is required.";
                } else {
                    $normalizedCode = strtoupper($vendorCode);

                    if ($this->existingVendors->has($normalizedCode)) {
                        $this->error[] = "Row {$row_index}: Vendor already exists!";
                    } else {
                        try {
                            $vendorData = [
                                'vendor_code' => $normalizedCode,
                                'vendor_description' => $vendorDesc,
                                'vendor_address' => $vendorAddress,
                                'vendor_phone' => $vendorPhone !== '' ? $vendorPhone : null,
                                'vendor_fax' => $vendorFax !== '' ? $vendorFax : null,
                                'vendor_email' => $vendorEmail !== '' ? $vendorEmail : null,
                            ];

                            $contactData = [
                                'vendor_contact_name' => $contactName !== '' ? $contactName : null,
                                'vendor_contact_phone' => $contactPhone !== '' ? $contactPhone : null,
                                'vendor_contact_email' => $contactEmail !== '' ? $contactEmail : null,
                                'vendor_contact_fax' => $contactFax !== '' ? $contactFax : null,
                            ];

                            $this->vendorService->saveVendor($vendorData, $contactData);
                            $this->existingVendors->put($normalizedCode, true);

                            $this->success[] = "Row {$row_index} : {$vendorCode} has been imported successfully.";
                        } catch (Throwable $e) {
                            $this->error[] = "Row {$row_index} : {$e->getMessage()}";
                        }
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
