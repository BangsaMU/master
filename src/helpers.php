<?php

if (! function_exists('is_master_db_same_as_default')) {
    /**
     * Check if master database (db_master) is pointing to the same database as the default database connection.
     */
    function is_master_db_same_as_default(): bool
    {
        $defaultConnName = config('database.default', 'mysql');
        $defaultConfig = config("database.connections.{$defaultConnName}", []);
        $masterConfig = config('database.connections.db_master', []);

        if (empty($defaultConfig) || empty($masterConfig)) {
            return true;
        }

        $defaultHost = strtolower(trim($defaultConfig['host'] ?? '127.0.0.1'));
        $masterHost = strtolower(trim($masterConfig['host'] ?? '127.0.0.1'));
        if ($defaultHost === 'localhost') {
            $defaultHost = '127.0.0.1';
        }
        if ($masterHost === 'localhost') {
            $masterHost = '127.0.0.1';
        }

        $defaultPort = (string) ($defaultConfig['port'] ?? '3306');
        $masterPort = (string) ($masterConfig['port'] ?? '3306');

        $defaultDb = strtolower(trim($defaultConfig['database'] ?? ''));
        $masterDb = strtolower(trim($masterConfig['database'] ?? ''));

        return ($defaultHost === $masterHost) && ($defaultPort === $masterPort) && ($defaultDb === $masterDb);
    }
}

if (! function_exists('annotation_launch_url')) {
    /**
     * Link untuk membuka file di viewer annotation.
     * Mode legacy (default): link viewer lama dengan token md5(email).
     * Mode launch (ANNOTATION_TOKEN_MODE=launch): link ke route annotation.open yang membuat token terenkripsi.
     */
    function annotation_launch_url(?string $fileUrl, ?string $callback = null): ?string
    {
        return app(\Bangsamu\Master\Services\AnnotationLaunchTokenService::class)->launchUrl($fileUrl, $callback);
    }
}
