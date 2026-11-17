<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;
use RuntimeException;

final class Connection
{
    public static function connect(): PDO
    {
        $driver = strtolower(trim((string) Env::get('DB_CONNECTION', 'sqlite')));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $database = Env::get('DB_DATABASE', 'var/garwin.sqlite');
            $path = str_starts_with($database, DIRECTORY_SEPARATOR)
                ? $database
                : dirname(__DIR__, 2) . '/' . $database;
            $directory = dirname($path);

            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create the SQLite database directory.');
            }

            $pdo = new PDO('sqlite:' . $path, options: $options);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        }

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported DB_CONNECTION. Use sqlite, mysql, or pgsql.');
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $database = Env::get('DB_DATABASE');
        $username = Env::get('DB_USERNAME');
        $password = Env::get('DB_PASSWORD', '');

        if ($database === null || $username === null) {
            throw new RuntimeException('DB_DATABASE and DB_USERNAME must be configured for the selected database.');
        }

        if ($driver === 'pgsql') {
            $sslMode = Env::get('DB_SSLMODE', 'require');
            if (!in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
                throw new RuntimeException('DB_SSLMODE must be a supported PostgreSQL SSL mode.');
            }

            return new PDO(
                "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslMode}",
                $username,
                $password,
                $options
            );
        }

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            $options
        );
    }
}
