<?php
return [
    'curl' => array(
        'TIMEOUT' => 30,
        'VERIFY' => false,
        'LIST_NETWORK' => env('APP_LIST_NETWORK'),
    ),
    'main' => array(
        'APP_CODE' => 'APP03', /*10 digit max char dari master app */
        'KEY' => '', /*32 digit  char random untuk hasing token harus sama antara server an client untuk decode token dari server */
        'ACTIVE' => env('SSO_ACTIVE', false), /*jika akan login mengunakan sso set ke true [true,false], tambahkan di env SSO_ACTIVE untuk config di lokal development*/
        'TOKEN' => '', /*auth untuk masuk ke sytem api sso*/
        'URL' => env('SSO_URL', 'http://sso.test'), /*harus diakhiri dengan / (slash) url untuk login SSO*/
        'CALL_BACK' => '',
    ),
    'MASTER_TABEL' => ['location', 'project', 'project_detail', 'employee', 'item_code', 'uom', 'company'],
    'master' => array(
        'company' => [
            'MODEL' => env('TABEL_MASTER_COMPANY', 'MasterCompany'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_COMPANY', '["id","employee_name","employee_job_title","employee_email"]')),
        ],
        'project' => [
            'MODEL' => env('TABEL_MASTER_PROJECT', 'MasterProject'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_PROJECT', '["id","project_code","project_name"]')),
        ],
        'location' => [
            'MODEL' => env('TABEL_MASTER_LOCATION', 'MasterLocation'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_LOCATION', '["loc_code","loc_name"]')),
        ],
        'item_code' => [
            'MODEL' => env('TABEL_MASTER_ITEM_CODE', 'MasterItemCode'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_ITEM_CODE', '["item_code","item_name"]')),
        ],
        'uom' => [
            'MODEL' => env('TABEL_MASTER_UOM', 'MasterUom'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_UOM', '["id","uom_code","uom_name"]')),
        ],
    ),
    'lokal' => array(
        'company' => [
            'MODEL' => env('TABEL_MASTER_COMPANY', 'Company'),
            // 'FIELD' => json_decode(env('SYNC_MASTER_COMPANY', '["id","employee_name","employee_job_title","employee_email"]')),
        ],
        'employee' => [
            'MODEL' => env('TABEL_LOKAL_EMPLOYEE', 'Employee'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_EMPLOYEE', '["id","name","job_title","email"]')),
        ],
        'project' => [
            'MODEL' => env('TABEL_LOKAL_PROJECT', 'Project'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_PROJECT', '["id","project_code","project_name"]')),
        ],
        'project_detail' => [
            'MODEL' => env('TABEL_LOKAL_PROJECT_DETAIL', 'ProjectDetail'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_PROJECT', '["id","project_code","project_name"]')), comment untuk sync semua filed
        ],
        'location' => [
            'MODEL' => env('TABEL_LOKAL_LOCATION', 'Location'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_LOCATION', '["location_code","location_name"]')),
        ],
        'item_code' => [
            'MODEL' => env('TABEL_LOKAL_ITEM_CODE', 'Item'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_ITEM_CODE', '["item_code","item_desc"]')),
        ],
        'uom' => [
            'MODEL' => env('TABEL_LOKAL_UOM', 'Uom'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_UOM', '["id","uom_code","uom_name"]')),
        ],
    )
];
