<?php

/*
 * Stub helper global milik app (paket memakai helper app yang memasangnya). Hanya untuk test paket.
 */

if (! function_exists('checkPermission')) {
    /**
     * Stub helper app: izin ditolak bila tercantum di config test.denied_permissions; bila test.allowed_permissions
     * diisi (array) hanya izin di daftar itu yang diberikan.
     */
    function checkPermission($permission)
    {
        if (! auth()->check() || in_array($permission, config('test.denied_permissions', []), true)) {
            return false;
        }
        $allowed = config('test.allowed_permissions');

        return ! is_array($allowed) || in_array($permission, $allowed, true);
    }
}

if (! function_exists('configDefAction')) {
    /** Stub helper app untuk config halaman form master. */
    function configDefAction($id = null, $data = null)
    {
        return $data;
    }
}

if (! function_exists('searchConfig')) {
    /** Stub helper app: data modal Search di halaman daftar master (layouts.dashboard.index). */
    function searchConfig($data, $view_tabel_index = [])
    {
        return [
            'modal_format' => ['identification' => 'modal_search', 'title' => 'Search', 'button_title' => 'Search'],
            'form_format' => ['identification' => 'form_search', 'url' => ''],
            'list_input' => [],
        ];
    }
}
