<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

/**
 * Tombol "Edit di Master Data": bila MASTER_DIRECT_EDIT=false data master di aplikasi ini hanya dibaca (hasil sync hub),
 * ubahannya dikerjakan di aplikasi Master Data (MASTER_EDIT_URL). Path route paket sama di kedua aplikasi dan id baris =
 * id di DB master, jadi link langsung ke master/{slug}/{id}/edit dan master/{slug}/create.
 * Izin per modul: `create_{akhiran}` (tombol Tambah) dan `update_{akhiran}` (tombol Edit), nama permission mengikuti
 * aplikasi Master Data (meindo_master), mis. item code = `update_item`.
 */
class MasterEditLink
{
    /**
     * Slug route => akhiran permission (override per slug lewat MasterCrudConfig.MASTER_EDIT_PERMISSIONS).
     *
     * @var array<string, string>
     */
    public const DEFAULT_PERMISSIONS = [
        'category' => 'category',
        'company' => 'company',
        'department' => 'department',
        'employee' => 'employee',
        'item-code' => 'item',
        'item-group' => 'itemgroup',
        'location' => 'location',
        'pca' => 'pca',
        'project' => 'project',
        'project-detail' => 'project_detail',
        'uom' => 'uom',
        'vendor' => 'vendor',
    ];

    /** tombol tampil: edit lokal mati dan URL aplikasi Master Data diisi */
    public static function enabled(): bool
    {
        return ! config('MasterCrudConfig.MASTER_DIRECT_EDIT') && filled(config('MasterCrudConfig.MASTER_EDIT_URL'));
    }

    /** nama permission tombol untuk modul ($ability create = Tambah, update = Edit) */
    public static function permission(string $slug, string $ability): string
    {
        $map = (array) config('MasterCrudConfig.MASTER_EDIT_PERMISSIONS', []) + self::DEFAULT_PERMISSIONS;

        return $ability.'_'.($map[$slug] ?? str_replace('-', '_', $slug));
    }

    /**
     * User boleh melihat tombol modul ini: is_admin, permission semua modul (MASTER_EDIT_PERMISSION, opsional), atau
     * permission modul itu.
     */
    public static function allowed(string $slug, string $ability = 'update'): bool
    {
        $user = auth()->user();
        if (! self::enabled() || $user === null) {
            return false;
        }

        $allModules = (string) config('MasterCrudConfig.MASTER_EDIT_PERMISSION');

        return $user->can('is_admin')
            || ($allModules !== '' && $user->can($allModules))
            || $user->can(self::permission($slug, $ability));
    }

    /** URL edit baris ($id diisi) atau tambah data ($id null) di aplikasi Master Data */
    public static function url(string $slug, int|string|null $id = null): string
    {
        $base = rtrim((string) config('MasterCrudConfig.MASTER_EDIT_URL'), '/').'/master/'.$slug;

        return $id === null ? $base.'/create' : $base.'/'.rawurlencode((string) $id).'/edit';
    }

    /**
     * Tombol di atas tabel (render di layouts.dashboard.index, buka tab baru).
     *
     * @return array{id: string, title: string, icon: string, url: string, target: string}
     */
    public static function createButton(string $slug): array
    {
        return ['id' => 'create', 'title' => 'Tambah di Master Data', 'icon' => 'btn-primary', 'url' => self::url($slug), 'target' => '_blank'];
    }

    /** tombol aksi per baris DataTables; string kosong bila tidak berlaku */
    public static function rowButton(string $slug, int|string|null $id): string
    {
        if ($id === null || $id === '' || ! self::allowed($slug, 'update')) {
            return '';
        }

        return ' <a href="'.e(self::url($slug, $id)).'" target="_blank" rel="noopener" class="btn btn-warning btn-sm">Edit di Master Data</a>';
    }
}
