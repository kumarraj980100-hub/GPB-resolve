<?php
require_once __DIR__ . '/../includes/helpers.php';

function get_db(): PDO
{
    $driver = getenv('DB_DRIVER') ?: 'mysql';

    if ($driver === 'mysql' || getenv('DB_HOST')) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db = getenv('DB_NAME') ?: 'gpb_resolve';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        try {
            $pdo = new PDO("mysql:host={$host};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo = new PDO("mysql:host={$host};dbname={$db};charset={$charset}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            initialize_schema($pdo, 'mysql');
            seed_default_data($pdo);
            return $pdo;
        } catch (PDOException $e) {
            if (getenv('DB_DRIVER') !== false || getenv('DB_HOST') !== false) {
                throw $e;
            }
        }
    }

    $dbFile = APP_ROOT . '/database/gpb_resolve.sqlite';
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    initialize_schema($pdo, 'sqlite');
    seed_default_data($pdo);
    return $pdo;
}

function initialize_schema(PDO $pdo, string $driver = 'mysql'): void
{
    $schemaFile = $driver === 'sqlite'
        ? APP_ROOT . '/database/schema.sqlite.sql'
        : APP_ROOT . '/database/schema.mysql.sql';

    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        return;
    }

    $pdo->exec($sql);
    ensure_phone_number_columns($pdo, $driver);
}

function ensure_phone_number_columns(PDO $pdo, string $driver): void
{
    foreach (['users', 'admin_access_requests'] as $table) {
        if ($driver === 'sqlite') {
            $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
            $hasPhoneNumber = array_filter($columns, static function (array $column): bool {
                return $column['name'] === 'phone_number';
            });
        } else {
            $column = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'phone_number'")->fetch();
            $hasPhoneNumber = $column ? [$column] : [];
        }

        if (!$hasPhoneNumber) {
            $type = $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(30) NULL';
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN phone_number {$type}");
        }
    }
}

function seed_default_data(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count === 0) {
        $seed = file_get_contents(APP_ROOT . '/database/seed.sql');
        if ($seed !== false) {
            $pdo->exec($seed);
        }
    }
}
