<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    |
    |
    */

    'MASTER_DIRECT_EDIT' => env('MASTER_DIRECT_EDIT', false),
    'MASTER_COMPANY_ID' => env('MASTER_COMPANY_ID', 1), // default ke id 1
    // dropdown api/getmaster_*byparams ikut filter Setting/DashboardSettings (kategori = nama tabel) seperti list master
    'SELECT2_SETTING_FILTER' => env('MASTER_SELECT2_SETTING_FILTER', false),

    // middleware route api/get*byparams (select2); default wajib login. ['api'] = publik (batas tabel/kolom tetap berlaku)
    'SELECT2_MIDDLEWARE' => ['web', 'auth'],
    // tabel yang boleh dibaca api/get{tabel}byparams (pola Str::is, mis. 'master_*'); kosong = semua kecuali SELECT2_DENY_TABLES
    'SELECT2_TABLES' => [],
    // tabel tambahan yang ditolak (selain sessions, *token*, jobs, cache, migrations, telescope_*, dll. yang selalu ditolak)
    'SELECT2_DENY_TABLES' => [],
    // kolom tambahan yang tidak pernah keluar / dipakai filter (password, remember_token, api_token selalu ditolak)
    'SELECT2_HIDDEN_COLUMNS' => ['two_factor_secret', 'two_factor_recovery_codes'],

];
