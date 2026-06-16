<?php
/**
 * Database connection settings.
 * Consumed by core/Database.php to build the PDO DSN.
 *
 * WARNING — LOCAL DEVELOPMENT ONLY.
 * The credentials below (root/root) are for a local dev environment only.
 * They MUST be changed before any production deployment. See .env.example
 * for the documented variables; a full .env loader is intentionally out of
 * scope for the current phases.
 */

return [
    'host'    => 'localhost',
    'port'    => '3306',
    'dbname'  => 'it_helpdesk',
    'user'    => 'root',
    'pass'    => 'root',
    'charset' => 'utf8mb4',
];
