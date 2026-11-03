<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services\Import;

use Bangsamu\Master\Models\DashboardSettings;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Saklar import master di tabel dashboard_settings (group `master_import`).
 *
 * `master_import.auto_create_refs`: referensi (mis. UoM/PCA/Category/Item Group di import Item Code) yang belum ada di
 * master dibuat otomatis saat import. Default 0 = ditolak: baris dengan referensi baru menjadi Error dan dilewati.
 */
final class MasterImportSettings
{
    public const GROUP = 'master_import';

    public const AUTO_CREATE_REFS = 'master_import.auto_create_refs';

    /**
     * Buat baris saklar (nilai awal 0 = tolak) bila belum ada, supaya bisa diubah dari halaman Dashboard Settings.
     */
    public static function ensure(): void
    {
        try {
            if (! Schema::hasTable('dashboard_settings')) {
                return;
            }

            DashboardSettings::query()->firstOrCreate(['key' => self::AUTO_CREATE_REFS], [
                'value' => '0',
                'group' => self::GROUP,
                'type' => 'checkbox',
                'label' => 'Import: buat otomatis referensi yang belum ada di master (mis. UoM, PCA, Category, Item Group). '
                    .'Mati = baris dengan referensi baru ditolak dan dilewati.',
                'order' => 1,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** true = referensi baru dibuat otomatis; false (default, juga bila setting tidak terbaca) = ditolak. */
    public static function autoCreateRefs(): bool
    {
        self::ensure();

        try {
            $value = DashboardSettings::query()->where('key', self::AUTO_CREATE_REFS)->value('value');
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
