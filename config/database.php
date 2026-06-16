<?php
/**
 * Database connection settings.
 * Consumed by core/Database.php to build the PDO DSN.
 *
 * Values are read from environment variables when present (production /
 * Railway), and fall back to the local development credentials otherwise.
 * Both the project's own DB_* names and Railway's default MYSQL* names are
 * supported. The local fallbacks (root/root) are for local development only.
 */

// Return the first non-empty environment variable from the given names, or
// the provided default when none are set.
$env = static function (array $names, $default) {
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
};

return [
    'host'    => $env(['DB_HOST', 'MYSQLHOST'],     'localhost'),
    'port'    => $env(['DB_PORT', 'MYSQLPORT'],     '3306'),
    'dbname'  => $env(['DB_NAME', 'MYSQLDATABASE'], 'it_helpdesk'),
    'user'    => $env(['DB_USER', 'MYSQLUSER'],     'root'),
    'pass'    => $env(['DB_PASS', 'MYSQLPASSWORD'], 'root'),
    'charset' => $env(['DB_CHARSET'],               'utf8mb4'),
];
