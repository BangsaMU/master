# sso

Integrasikan CLAY dengan fitur Master data 

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
