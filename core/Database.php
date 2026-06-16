<?php
/**
 * Database — singleton PDO wrapper.
 *
 * Provides a single shared PDO connection configured for utf8mb4 and
 * exception-based error handling, as required by the project rules.
 */
class Database
{
    /** @var PDO|null Shared connection instance. */
    private static $instance = null;

    // Prevent instantiation and cloning; use Database::connection().
    private function __construct() {}
    private function __clone() {}

    /**
     * Return the shared PDO connection, creating it on first call.
     *
     * @return PDO
     */
    public static function connection()
    {
        if (self::$instance === null) {
            $config = require CONFIG_PATH . '/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$instance = new PDO($dsn, $config['user'], $config['pass'], $options);
        }

        return self::$instance;
    }
}
