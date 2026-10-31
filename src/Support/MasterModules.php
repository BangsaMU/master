<?php

declare(strict_types=1);

namespace Bangsamu\Master\Support;

/**
 * Satu sumber nama modul master: slug route (master/{slug}) <-> tabel <-> nama permission.
 *
 * Nama permission seragam di semua aplikasi: {aksi}_{tabel tanpa master_} dengan aksi read/create/update/delete
 * (import = create), mis. update_item_code. Nama lama tetap diterima sebagai alias selama transisi
 * (item -> item_code, itemgroup -> item_group); `php artisan master:permissions` menggabungkannya ke nama baru.
 */
class MasterModules
{
    public const ABILITIES = ['read', 'create', 'update', 'delete'];

    /**
     * slug => [tabel, alias akhiran permission lama].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const MODULES = [
        'brand' => ['master_brand', []],
        'category' => ['master_category', []],
        'company' => ['master_company', []],
        'department' => ['master_department', []],
        'employee' => ['master_employee', []],
        'item-code' => ['master_item_code', ['item']],
        'item-group' => ['master_item_group', ['itemgroup']],
        'job-position' => ['master_job_position', []],
        'location' => ['master_location', []],
        'pca' => ['master_pca', []],
        'priority' => ['master_priority', []],
        'project' => ['master_project', []],
        'project-detail' => ['master_project_detail', []],
        'status' => ['master_status', []],
        'uom' => ['master_uom', []],
        'vendor' => ['master_vendor', []],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::MODULES[$slug]);
    }

    public static function table(string $slug): string
    {
        return self::MODULES[$slug][0] ?? 'master_'.str_replace('-', '_', $slug);
    }

    /** slug untuk tabel (master_item_code => item-code), null bila bukan modul master */
    public static function slugForTable(string $table): ?string
    {
        foreach (self::MODULES as $slug => [$moduleTable]) {
            if ($moduleTable === $table) {
                return $slug;
            }
        }

        return null;
    }

    /** akhiran permission baru: nama tabel tanpa master_ (item-code => item_code) */
    public static function suffix(string $slug): string
    {
        return substr(self::table($slug), strlen('master_'));
    }

    /** nama permission baru, mis. permission('item-code', 'update') = update_item_code */
    public static function permission(string $slug, string $ability): string
    {
        return $ability.'_'.self::suffix($slug);
    }

    /**
     * Semua nama yang diterima untuk modul + aksi: nama baru lalu alias lama.
     *
     * @return list<string>
     */
    public static function permissionNames(string $slug, string $ability): array
    {
        $names = [self::permission($slug, $ability)];
        foreach (self::MODULES[$slug][1] ?? [] as $alias) {
            $names[] = $ability.'_'.$alias;
        }

        return $names;
    }

    /**
     * Semua permission baru seluruh modul (untuk master:permissions).
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        $names = [];
        foreach (array_keys(self::MODULES) as $slug) {
            foreach (self::ABILITIES as $ability) {
                $names[] = self::permission($slug, $ability);
            }
        }

        return $names;
    }

    /**
     * alias lama => nama baru (read_item => read_item_code, ...).
     *
     * @return array<string, string>
     */
    public static function legacyAliases(): array
    {
        $map = [];
        foreach (self::MODULES as $slug => [, $aliases]) {
            foreach ($aliases as $alias) {
                foreach (self::ABILITIES as $ability) {
                    $map[$ability.'_'.$alias] = self::permission($slug, $ability);
                }
            }
        }

        return $map;
    }

    /** aksi route resource -> aksi permission (store dengan id = update) */
    public static function abilityForRouteAction(string $action, bool $hasId = false): ?string
    {
        return match ($action) {
            'index', 'show' => 'read',
            'create', 'import', 'importtemplate' => 'create',
            'store' => $hasId ? 'update' : 'create',
            'edit', 'update', 'update_template_json' => 'update',
            'destroy' => 'delete',
            default => null,
        };
    }
}
