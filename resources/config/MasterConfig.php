<?php

return [
    'curl' => [
        'TIMEOUT' => 30,
        'VERIFY' => false,
        'LIST_NETWORK' => env('APP_LIST_NETWORK'),
    ],
    'main' => [
        'APP_CODE' => 'APP03', /* 10 digit max char dari master app */
        'KEY' => '', /* 32 digit  char random untuk hasing token harus sama antara server an client untuk decode token dari server */
        'ACTIVE' => env('SSO_ACTIVE', false), /* jika akan login mengunakan sso set ke true [true,false], tambahkan di env SSO_ACTIVE untuk config di lokal development */
        'TOKEN' => '', /* auth untuk masuk ke sytem api sso */
        'URL' => env('SSO_URL', 'http://sso.test'), /* harus diakhiri dengan / (slash) url untuk login SSO */
        'CALL_BACK' => '',
    ],
    'MASTER_TABEL' => ['location', 'project', 'project_detail', 'employee', 'item_code', 'uom', 'company'],
    'master' => [
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
    ],
    'lokal' => [
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
            'MODEL' => env('TABEL_LOKAL_ITEM_CODE', 'ItemCode'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_ITEM_CODE', '["item_code","item_desc"]')),
        ],
        'uom' => [
            'MODEL' => env('TABEL_LOKAL_UOM', 'Uom'),
            // 'FIELD' => json_decode(env('SYNC_LOKAL_UOM', '["id","uom_code","uom_name"]')),
        ],
    ],
    'senada' => [
        'active' => env('SENADA_BROADCAST_ACTIVE', true),
        'url' => env('SENADA_URL', 'http://192.168.20.187:9029'),
        'api_key' => env('SENADA_API_KEY', 'snd_masterdata_key_secret'),
        'timeout' => env('SENADA_TIMEOUT', 3),
        'channel' => env('SENADA_CHANNEL_MASTER_ITEMS', 'masterdata.items'),
        'app_key' => env('REVERB_APP_KEY', 'senada_hub_key'),
        'app_secret' => env('REVERB_APP_SECRET', 'senada_hub_secret'),
        'reverb_host' => env('REVERB_HOST', '192.168.20.187'),
        'reverb_port' => env('REVERB_PORT', 9029),
        'reverb_scheme' => env('REVERB_SCHEME', 'http'),
    ],
    'sync' => [
        'schedule_enabled' => env('MASTER_SYNC_SCHEDULE_ENABLED', true),
        'schedule_frequency' => env('MASTER_SYNC_SCHEDULE_INTERVAL', 'hourly'), // 'hourly', 'everyThirtyMinutes', 'everyTwoHours', 'daily', or cron '0 * * * *'
        'schedule_limit' => (int) env('MASTER_SYNC_SCHEDULE_LIMIT', 50),
        'lock_ttl' => (int) env('MASTER_SYNC_LOCK_TTL', 300), // Default 300s (5m), max 3600s (1h)
        'browser_cooldown' => (int) env('MASTER_SYNC_BROWSER_COOLDOWN', 300), // 5 minutes cooldown for browser catch-up
        'browser_auto_catchup' => env('MASTER_SYNC_BROWSER_AUTO_CATCHUP', true),
    ],
];
