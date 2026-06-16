<?php
/**
 * Installer — first-run database bootstrap.
 *
 * On a fresh deployment the target database exists but holds no tables yet
 * (e.g. Railway provisions an empty MySQL database). On the first request,
 * ensure() detects the empty schema and imports database/schema.sql once.
 *
 * The CREATE DATABASE / USE statements in schema.sql are stripped at runtime
 * so the tables are created inside the already-connected database, whatever
 * its name is. schema.sql itself is never modified.
 */
class Installer
{
    /**
     * Create the schema on first run when it is missing. Cheap no-op once the
     * tables already exist (a single SHOW TABLES check).
     *
     * @return void
     */
    public static function ensure()
    {
        $pdo = Database::connection();

        // Already installed? The users table is part of the base schema.
        $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($exists !== false) {
            return;
        }

        $schemaFile = BASE_PATH . '/database/schema.sql';
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            return;
        }

        // Run inside the connected database: drop the CREATE DATABASE block and
        // the USE statement, then execute the remaining DDL/seed as one batch.
        $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
        $sql = preg_replace('/USE\s+`?[A-Za-z0-9_]+`?\s*;/i', '', $sql);

        $pdo->exec($sql);
    }
}
