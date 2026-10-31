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
    // MASTER_DIRECT_EDIT=false: tombol "Edit/Tambah di Master Data" di daftar master membuka aplikasi Master Data (base URL
    // tanpa slash akhir, mis. http://192.168.20.187:9002); kosong = tanpa tombol. Path: master/{slug}/{id}/edit
    'MASTER_EDIT_URL' => env('MASTER_EDIT_URL'),
    // siapa melihat tombol: is_admin, atau per modul create_{akhiran} (Tambah) / update_{akhiran} (Edit) dengan nama
    // permission aplikasi Master Data (item code = update_item); override akhiran per slug, mis. ['item-code' => 'item_code']
    'MASTER_EDIT_PERMISSIONS' => [],
    // opsional: satu permission untuk semua modul (kosong = tidak ada)
    'MASTER_EDIT_PERMISSION' => env('MASTER_EDIT_PERMISSION'),
    'MASTER_COMPANY_ID' => env('MASTER_COMPANY_ID', 1), // default ke id 1

    // --- pemilik data (scope app_code + company_id), lihat Services\MasterScope & docs/scope-multi-company.md ---
    // true hanya di aplikasi Master Data (hub): menerima launch token dari aplikasi lain, tim master boleh semua pemilik
    'MASTER_HUB' => env('MASTER_HUB', false),
    // app_code data bersama (dipakai semua aplikasi)
    'GLOBAL_APP_CODE' => env('MASTER_GLOBAL_APP_CODE', 'APP03'),
    // company user login: nama fungsi / class invokable ($user) => master_company.id; kosong = resolve_user_company_id()
    // bila ada, lalu user_details / master_user_details (field_key company_id), lalu MASTER_COMPANY_ID
    'USER_COMPANY_RESOLVER' => env('MASTER_USER_COMPANY_RESOLVER'),
    // permission yang boleh mengelola baris company 0 (berlaku semua company) milik aplikasi sendiri
    'ALL_COMPANY_PERMISSION' => env('MASTER_ALL_COMPANY_PERMISSION', 'is_admin'),
    // saklar transisi: tabel yang kodenya unik per pemilik (kode sama boleh di aplikasi/company lain), dipisah koma,
    // "*" = semua tabel ber-scope. Kosong (default) = kode tetap unik global seperti sebelum v3. Aktifkan per tabel
    // HANYA setelah semua aplikasi yang menyalin tabel itu memakai v3 dan menjalankan master:scope-upgrade di DB
    // lokalnya: salinan lama dengan index unik global akan saling menimpa baris berkode sama saat sinkron (upsert).
    'UNIQUE_PER_OWNER' => array_values(array_filter(array_map('trim', explode(',', (string) env('MASTER_UNIQUE_PER_OWNER', ''))))),

    // --- launch token "Edit di Master Data" (login otomatis + delegasi izin), lihat Services\MasterLaunchToken ---
    // klien: kunci privat Ed25519 base64 (php artisan master:launch-key); kosong = tombol memakai link lama tanpa token
    'LAUNCH_PRIVATE_KEY' => env('MASTER_LAUNCH_PRIVATE_KEY'),
    // masa berlaku token (detik)
    'LAUNCH_TTL' => (int) env('MASTER_LAUNCH_TTL', 120),
    // hub: masa berlaku session delegasi (menit)
    'LAUNCH_SESSION_MINUTES' => (int) env('MASTER_LAUNCH_SESSION_MINUTES', 120),
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

    // route master.table.export (master/get{table}/export): hanya tabel di sini yang bisa diunduh (lainnya 404), nama tabel
    // => permission baca (salah satu cukup, is_admin selalu boleh; selain itu 403). Kolom SELECT2_HIDDEN_COLUMNS +
    // password/remember_token/api_token tidak ikut. App lain menambah tabelnya sendiri, mis. 'mcu_matrix' => ['read_mcu'].
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

    // route crud/{table} (CRUD tabel generik, hanya is_admin): tabel yang boleh dibuka; kosong = semua 404
    'CRUD_TABLES' => [],

];
