<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;
use PDOException;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function shouldApplyMigration(string $name): bool
    {
        $optionalMigrations = ['006_create_wordbase.php'];
        if (!in_array($name, $optionalMigrations, true)) {
            return true;
        }

        $value = Env::get('APP_ENABLE_OPTIONAL_MIGRATIONS', 'false');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return list<string> */
    public function migrate(string $directory): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (migration TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
            );
        } elseif ($driver === 'mysql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(255) PRIMARY KEY, applied_at TIMESTAMP NOT NULL)'
            );
        } elseif ($driver === 'pgsql') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(255) PRIMARY KEY, applied_at TIMESTAMP NOT NULL)'
            );
        } else {
            throw new RuntimeException('The configured database driver does not support migrations.');
        }

        $applied = $this->pdo->query('SELECT migration FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_fill_keys($applied, true);
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $newMigrations = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->shouldApplyMigration($name)) {
                continue;
            }
            if (isset($applied[$name])) {
                continue;
            }

            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("Migration {$name} must return a callable.");
            }

            $this->pdo->beginTransaction();
            try {
                $migration($this->pdo);
                $statement = $this->pdo->prepare(
                    'INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)'
                );
                $statement->execute([
                    'migration' => $name,
                    'applied_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $this->pdo->commit();
                $newMigrations[] = $name;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
        }

        return $newMigrations;
    }

    /** @return list<string> */
    public function pending(string $directory): array
    {
        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $names = array_values(array_filter(array_map('basename', $files), fn (string $name): bool => $this->shouldApplyMigration($name)));

        try {
            $applied = $this->pdo->query('SELECT migration FROM schema_migrations')
                ->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException) {
            return $names;
        }

        return array_values(array_diff($names, $applied));
    }
}
