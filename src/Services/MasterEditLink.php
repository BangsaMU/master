<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Support\MasterModules;

/**
 * Tombol "Edit di Master Data": bila MASTER_DIRECT_EDIT=false data master di aplikasi ini hanya dibaca (hasil sync hub),
 * ubahannya dikerjakan di aplikasi Master Data (MASTER_EDIT_URL). Path route paket sama di kedua aplikasi dan id baris =
 * id di DB master, jadi link langsung ke master/{slug}/{id}/edit dan master/{slug}/create.
 * Izin per modul: `create_{tabel}` (tombol Tambah) dan `update_{tabel}` (tombol Edit), nama seragam MasterModules
 * (mis. update_item_code; nama lama update_item tetap diterima). Tombol Edit hanya pada baris milik aplikasi + company
 * user (MasterAccess). Dengan MASTER_LAUNCH_PRIVATE_KEY link lewat master-launch.go: user langsung masuk Master Data
 * tanpa login ulang dengan delegasi izinnya (MasterLaunchToken).
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
     * User boleh melihat tombol modul ini: is_admin, permission semua modul (MASTER_EDIT_PERMISSION, opsional),
     * permission override MASTER_EDIT_PERMISSIONS, atau permission modul seragam (MasterModules, nama baru + alias lama).
     * Bila $row diisi (baris / id) baris tabel ber-scope juga harus milik aplikasi ini + company user (MasterAccess),
     * termasuk untuk is_admin: data bersama / aplikasi lain hanya diubah tim Master Data.
     */
    public static function allowed(string $slug, string $ability = 'update', mixed $row = null): bool
    {
        $user = auth()->user();
        if (! self::enabled() || $user === null) {
            return false;
        }
        $allModules = (string) config('MasterCrudConfig.MASTER_EDIT_PERMISSION');
        $override = (array) config('MasterCrudConfig.MASTER_EDIT_PERMISSIONS', []);
        $permitted = $user->can('is_admin')
            || ($allModules !== '' && $user->can($allModules))
            || (isset($override[$slug]) && $user->can($ability.'_'.$override[$slug]))
            || app(MasterAccess::class)->hasPermission($slug, $ability);

        if (! $permitted) {
            return false;
        }
        if ($row === null || ! MasterModules::exists($slug)) {
            return true;
        }

        $access = app(MasterAccess::class);
        $scope = app(MasterScope::class);

        return $access->rowOwnedBy(MasterModules::table($slug), $row, $scope->appCode(), $scope->userCompanyId(), $access->canManageAllCompanies());
    }

    /**
     * URL edit baris ($id diisi) atau tambah data ($id null) di aplikasi Master Data. Dengan kunci launch
     * (MASTER_LAUNCH_PRIVATE_KEY) lewat route lokal master-launch.go: user langsung masuk tanpa login ulang, membawa
     * aplikasi + company + izinnya (MasterLaunchToken). Tanpa kunci: link langsung seperti sebelumnya.
     */
    public static function url(string $slug, int|string|null $id = null): string
    {
        if (app(MasterLaunchToken::class)->canIssue()) {
            return route('master-launch.go', $id === null ? ['slug' => $slug] : ['slug' => $slug, 'id' => $id]);
        }

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

    /** tombol aksi per baris DataTables ($row = baris DataTables, untuk cek pemilik); string kosong bila tidak berlaku */
    public static function rowButton(string $slug, int|string|null $id, mixed $row = null): string
    {
        if ($id === null || $id === '' || ! self::allowed($slug, 'update', $row ?? $id)) {
            return '';
        }

        return ' <a href="'.e(self::url($slug, $id)).'" target="_blank" rel="noopener" class="btn btn-warning btn-sm">Edit di Master Data</a>';
    }
}
