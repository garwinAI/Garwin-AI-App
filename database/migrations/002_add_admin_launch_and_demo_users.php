<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $statements = [
            'ALTER TABLE users ADD COLUMN is_demo INTEGER NOT NULL DEFAULT 0',
            'CREATE TABLE admin_users (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                role_key TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE integration_configs (
                provider_key TEXT PRIMARY KEY,
                credentials_json TEXT NOT NULL,
                verified_at TEXT,
                last_tested_at TEXT,
                last_test_message TEXT,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE platform_settings (
                setting_key TEXT PRIMARY KEY,
                value_text TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE INDEX demo_users_created_at ON users (is_demo, created_at)',
        ];
    } elseif ($driver === 'mysql') {
        $statements = [
            'ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0',
            'CREATE TABLE admin_users (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                role_key VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT admin_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE integration_configs (
                provider_key VARCHAR(32) PRIMARY KEY,
                credentials_json JSON NOT NULL,
                verified_at DATETIME NULL,
                last_tested_at DATETIME NULL,
                last_test_message VARCHAR(255) NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE platform_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                value_text TEXT NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE INDEX demo_users_created_at ON users (is_demo, created_at)',
        ];
    } elseif ($driver === 'pgsql') {
        $statements = [
            'ALTER TABLE users ADD COLUMN is_demo SMALLINT NOT NULL DEFAULT 0',
            'CREATE TABLE admin_users (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                role_key VARCHAR(32) NOT NULL,
                created_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE integration_configs (
                provider_key VARCHAR(32) PRIMARY KEY,
                credentials_json JSONB NOT NULL,
                verified_at TIMESTAMP,
                last_tested_at TIMESTAMP,
                last_test_message VARCHAR(255),
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE platform_settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                value_text TEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE INDEX demo_users_created_at ON users (is_demo, created_at)',
        ];
    } else {
        throw new RuntimeException('Unsupported migration database driver.');
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
};
