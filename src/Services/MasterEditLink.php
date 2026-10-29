<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

/**
 * Tombol "Edit di Master Data": bila MASTER_DIRECT_EDIT=false data master di aplikasi ini hanya dibaca (hasil sync hub),
 * ubahannya dikerjakan di aplikasi Master Data (MASTER_EDIT_URL). Path route paket sama di kedua aplikasi dan id baris =
 * id di DB master, jadi link langsung ke master/{slug}/{id}/edit dan master/{slug}/create.
 */
class MasterEditLink
{
    /** tombol tampil: edit lokal mati dan URL aplikasi Master Data diisi */
    public static function enabled(): bool
    {
        return ! config('MasterCrudConfig.MASTER_DIRECT_EDIT') && filled(config('MasterCrudConfig.MASTER_EDIT_URL'));
    }

    /** user yang melihat tombol: is_admin atau MASTER_EDIT_PERMISSION (default update_master_data) */
    public static function allowed(): bool
    {
        $user = auth()->user();

        return self::enabled() && $user !== null
            && ($user->can('is_admin') || $user->can((string) config('MasterCrudConfig.MASTER_EDIT_PERMISSION', 'update_master_data')));
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
        if ($id === null || $id === '' || ! self::allowed()) {
            return '';
        }

        return ' <a href="'.e(self::url($slug, $id)).'" target="_blank" rel="noopener" class="btn btn-warning btn-sm">Edit di Master Data</a>';
    }
}
