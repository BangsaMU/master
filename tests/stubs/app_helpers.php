<?php

/*
 * Stub helper global milik app (paket memakai helper app yang memasangnya). Hanya untuk test paket.
 */

if (! function_exists('checkPermission')) {
    /** Stub helper app: izin ditolak bila tercantum di config test.denied_permissions. */
    function checkPermission($permission)
    {
        return auth()->check() && ! in_array($permission, config('test.denied_permissions', []), true);
    }
}

if (! function_exists('configDefAction')) {
    /** Stub helper app untuk config halaman form master. */
    function configDefAction($id = null, $data = null)
    {
        return $data;
    }
}
