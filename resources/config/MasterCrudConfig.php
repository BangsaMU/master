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

    // route master.table.export (master/get{table}/export): hanya tabel di sini yang bisa diunduh (lainnya 404), nama tabel
    // => permission baca (salah satu cukup, is_admin selalu boleh; selain itu 403). Kolom password/remember_token/api_token
    // + SELECT2_HIDDEN_COLUMNS tidak ikut. App lain menambah tabelnya sendiri, mis. 'mcu_matrix' => ['is_medic'].
    'EXPORT_TABLES' => [
        'master_category' => ['read_category'],
        'master_company' => ['read_company'],
        'master_department' => ['read_department'],
        'master_employee' => ['read_employee'],
        'master_item_group' => ['read_itemgroup'],
        'master_location' => ['read_location'],
        'master_pca' => ['read_pca'],
        'master_priority' => ['read_priority'],
        'master_project' => ['read_project'],
        'master_project_detail' => ['read_project_detail', 'read_project'],
        'master_uom' => ['read_uom'],
        'master_vendor' => ['read_vendor'],
    ],
    // kolom tambahan yang tidak ikut export (password, remember_token, api_token selalu dibuang)
    'SELECT2_HIDDEN_COLUMNS' => ['two_factor_secret', 'two_factor_recovery_codes'],

    // route crud/{table} (CRUD tabel generik, hanya is_admin): tabel yang boleh dibuka; kosong = semua 404
    'CRUD_TABLES' => [],

];
