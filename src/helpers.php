<?php

if (!function_exists('is_master_db_same_as_default')) {
    /**
     * Check if master database (db_master) is pointing to the same database as the default database connection.
     *
     * @return bool
     */
    function is_master_db_same_as_default(): bool
    {
        $defaultConnName = config('database.default', 'mysql');
        $defaultConfig = config("database.connections.{$defaultConnName}", []);
        $masterConfig  = config("database.connections.db_master", []);

        if (empty($defaultConfig) || empty($masterConfig)) {
            return true;
        }

        $defaultHost = strtolower(trim($defaultConfig['host'] ?? '127.0.0.1'));
        $masterHost  = strtolower(trim($masterConfig['host'] ?? '127.0.0.1'));
        if ($defaultHost === 'localhost') $defaultHost = '127.0.0.1';
        if ($masterHost === 'localhost')  $masterHost  = '127.0.0.1';

        $defaultPort = (string)($defaultConfig['port'] ?? '3306');
        $masterPort  = (string)($masterConfig['port'] ?? '3306');

        $defaultDb   = strtolower(trim($defaultConfig['database'] ?? ''));
        $masterDb    = strtolower(trim($masterConfig['database'] ?? ''));

        return ($defaultHost === $masterHost) && ($defaultPort === $masterPort) && ($defaultDb === $masterDb);
    }
}
