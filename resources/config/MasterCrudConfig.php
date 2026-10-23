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

];
