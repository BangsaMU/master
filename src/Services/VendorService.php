<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\LibraryClay\Controllers\LibraryClayController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class VendorService
{
    /**
     * Synchronize vendor code and name to location tables.
     */
    public function syncVendorLocation(string $vendorCode, ?string $vendorDescription = '', ?string $oldVendorCode = null): int
    {
        $currentTimestamp = now()->format('Y-m-d H:i:s');
        $locationCode = Str::upper(trim($vendorCode));
        $locationName = $vendorDescription ?? '';
        $locationModel = LibraryClayController::resolveModelFromSheetSlug('location');
        $masterLocationModel = LibraryClayController::resolveModelFromSheetSlug('master_location');

        if ($oldVendorCode !== null && Str::upper(trim((string) $oldVendorCode)) !== $locationCode) {
            $oldLocationCode = Str::upper(trim((string) $oldVendorCode));

            $modelsToUpdate = LibraryClayController::isMasterDbSameAsDefault()
                ? [$locationModel]
                : [$locationModel, $masterLocationModel];

            foreach ($modelsToUpdate as $modelClass) {
                $modelClass::where('group_type', 'vendor')
                    ->where('loc_code', $oldLocationCode)
                    ->whereNull('deleted_at')
                    ->get()
                    ->each(function ($location) use ($locationCode, $locationName, $currentTimestamp) {
                        $location->update([
                            'loc_code' => $locationCode,
                            'loc_name' => $locationName,
                            'created_at' => $currentTimestamp,
                        ]);
                    });
            }
        }

        $location = $locationModel::updateOrCreate(
            ['loc_code' => $locationCode, 'group_type' => 'vendor'],
            ['loc_name' => $locationName, 'created_at' => $currentTimestamp]
        );

        if (! LibraryClayController::isMasterDbSameAsDefault()) {
            $masterLocation = $masterLocationModel::updateOrCreate(
                ['loc_code' => $locationCode, 'group_type' => 'vendor'],
                ['loc_name' => $locationName, 'created_at' => $currentTimestamp]
            );

            return (int) $masterLocation->id;
        }

        return (int) $location->id;
    }

    /**
     * Delete vendor location records.
     */
    public function deleteVendorLocation(string $vendorCode): void
    {
        $locationCode = Str::upper(trim($vendorCode));
        $locationModel = LibraryClayController::resolveModelFromSheetSlug('location');

        $locationModel::where('group_type', 'vendor')
            ->where('loc_code', $locationCode)
            ->whereNull('deleted_at')
            ->get()
            ->each(function ($location) {
                $location->delete();
            });

        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
            $masterLocationModel = LibraryClayController::resolveModelFromSheetSlug('master_location');

            $masterLocationModel::where('group_type', 'vendor')
                ->where('loc_code', $locationCode)
                ->whereNull('deleted_at')
                ->get()
                ->each(function ($location) {
                    $location->delete();
                });
        }
    }

    /**
     * Save or update vendor and its contact, including master sync if configured.
     *
     * @param  array<string, mixed>  $vendorData
     * @param  array<string, mixed>  $contactData
     * @return array{vendor: mixed, contact: mixed, vendorChanged: bool, contactChanged: bool, isNew: bool}
     */
    public function saveVendor(array $vendorData, array $contactData = [], ?int $id = null): array
    {
        $vendorModelClass = LibraryClayController::resolveModelFromSheetSlug('vendor');
        $contactModelClass = LibraryClayController::resolveModelFromSheetSlug('vendor_contact');

        $vendorCode = trim((string) ($vendorData['vendor_code'] ?? ''));
        $vendorDescription = trim((string) ($vendorData['vendor_description'] ?? ''));
        $vendorAddress = $vendorData['vendor_address'] ?? null;
        $vendorPhone = $vendorData['vendor_phone'] ?? null;
        $vendorFax = $vendorData['vendor_fax'] ?? null;
        $vendorEmail = $vendorData['vendor_email'] ?? null;

        $isNew = false;
        $oldVendorCode = null;

        if ($id) {
            $vendor = $vendorModelClass::findOrFail($id);
            $oldVendorCode = $vendor->vendor_code;
        } else {
            $vendor = new $vendorModelClass;
            $isNew = true;
        }

        $locId = $this->syncVendorLocation($vendorCode, $vendorDescription, $oldVendorCode);

        $vendor->forceFill([
            'vendor_code' => $vendorCode,
            'vendor_description' => $vendorDescription,
            'vendor_address' => $vendorAddress,
            'vendor_phone' => $vendorPhone,
            'vendor_fax' => $vendorFax,
            'vendor_email' => $vendorEmail,
            'loc_id' => $locId,
        ]);
        $vendor->save();

        // Process vendor contact
        $vendorContact = $contactModelClass::where('vendor_id', $vendor->id)->first();
        if (! $vendorContact) {
            $vendorContact = new $contactModelClass;
            $vendorContact->vendor_id = $vendor->id;
        }

        $vendorContact->forceFill([
            'vendor_contact_name' => $contactData['vendor_contact_name'] ?? null,
            'vendor_contact_phone' => $contactData['vendor_contact_phone'] ?? null,
            'vendor_contact_email' => $contactData['vendor_contact_email'] ?? null,
            'vendor_contact_fax' => $contactData['vendor_contact_fax'] ?? null,
        ]);
        $vendorContact->save();

        $vendorChanged = $vendor->wasChanged();
        $contactChanged = $vendorContact->wasChanged();

        // Handle master DB synchronization
        if (config('MasterCrudConfig.MASTER_DIRECT_EDIT') && ! LibraryClayController::isMasterDbSameAsDefault()) {
            $masterVendorModel = LibraryClayController::resolveModelFromSheetSlug('master_vendor');
            try {
                $masterVendorModel::updateOrCreate(
                    ['vendor_code' => $vendor->vendor_code],
                    [
                        'vendor_description' => $vendor->vendor_description,
                        'vendor_address' => $vendor->vendor_address,
                        'vendor_phone' => $vendor->vendor_phone,
                        'vendor_fax' => $vendor->vendor_fax,
                        'vendor_email' => $vendor->vendor_email,
                        'loc_id' => $locId,
                    ]
                );
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000' || str_contains($exception->getMessage(), 'Duplicate entry')) {
                    $masterVendorModel::where('vendor_code', $vendor->vendor_code)
                        ->whereNull('deleted_at')
                        ->update([
                            'vendor_description' => $vendor->vendor_description,
                            'vendor_address' => $vendor->vendor_address,
                            'vendor_phone' => $vendor->vendor_phone,
                            'vendor_fax' => $vendor->vendor_fax,
                            'vendor_email' => $vendor->vendor_email,
                            'loc_id' => $locId,
                        ]);
                } else {
                    throw $exception;
                }
            }

            $masterContactModel = LibraryClayController::resolveModelFromSheetSlug('master_vendor_contact');
            $masterContactModel::updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'vendor_contact_name' => $vendorContact->vendor_contact_name,
                    'vendor_contact_phone' => $vendorContact->vendor_contact_phone,
                    'vendor_contact_email' => $vendorContact->vendor_contact_email,
                    'vendor_contact_fax' => $vendorContact->vendor_contact_fax,
                ]
            );

            // Callbacks if update on existing records
            if (! $isNew && ($vendorChanged || $contactChanged)) {
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                if ($vendorChanged) {
                    $sync_tabel = 'master_vendor';
                    $sync_id = $vendor->id;
                    $sync_row = $vendor->toArray();
                    LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                if ($contactChanged) {
                    $sync_tabel = 'master_vendor_contact';
                    $sync_id = $vendor->id;
                    $sync_row = $vendorContact->toArray();
                    LibraryClayController::updateMaster(compact('sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
            }
        }

        return [
            'vendor' => $vendor,
            'contact' => $vendorContact,
            'vendorChanged' => $vendorChanged,
            'contactChanged' => $contactChanged,
            'isNew' => $isNew,
        ];
    }
}
