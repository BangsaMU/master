# sso

Integrasikan CLAY dengan fitur Master data V 0.0.2

Update:
- add global api get tabel
- add global api get tabel with join 
- add replace id tag return field &set[id]=name
- bug fix sync tabel
- add tabel user dan priority sync.
- add master prject CRUD
- update master employe skema add blood_type

buat config baru di file laravel config/database.php

'db_master' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL_MASTER'),
    'host' => env('DB_HOST_MASTER', '127.0.0.1'),
    'port' => env('DB_PORT_MASTER', '3306'),
    'database' => env('DB_DATABASE_MASTER', 'forge'),
    'username' => env('DB_USERNAME_MASTER', 'forge'),
    'password' => env('DB_PASSWORD_MASTER', ''),
    'unix_socket' => env('DB_SOCKET_MASTER', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => false,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],


sesuaikan namanya dengan config di model

## Export Tabel & CRUD Generik (sejak v1.1.101, backport v2.4.10)

- `master/get{table}/export`: hanya tabel `MasterCrudConfig.EXPORT_TABLES` (nama tabel => daftar permission baca, salah
  satu cukup); tabel lain 404, user tanpa `is_admin` / permission itu 403. Kolom `password`, `remember_token`,
  `api_token` + `SELECT2_HIDDEN_COLUMNS` tidak ikut. App yang mengekspor tabel lain wajib menambahkannya; override di
  app mengganti seluruh daftar.
- `crud/{table}`: hanya tabel `MasterCrudConfig.CRUD_TABLES` dan hanya `is_admin`; default kosong = semua 404.
  Constructor `CrudTabelController` tidak lagi menjalankan query sebelum middleware `auth` (dulu injeksi SQL tanpa login).
