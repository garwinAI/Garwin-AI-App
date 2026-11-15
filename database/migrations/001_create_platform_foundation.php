<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $statements = [
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL COLLATE BINARY UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                terms_accepted_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE memberships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                plan_key TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE user_preferences (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                preferences_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE dashboard_layouts (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                layout_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE ai_budgets (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                budget_cents INTEGER NOT NULL,
                spent_cents INTEGER NOT NULL DEFAULT 0,
                period_starts_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE storage_accounts (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                storage_key TEXT NOT NULL UNIQUE,
                quota_bytes INTEGER NOT NULL,
                used_bytes INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE storage_folders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                folder_name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (user_id, folder_name)
            )',
            'CREATE TABLE activity_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                event_type TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                ip_hash TEXT NOT NULL,
                attempted_at TEXT NOT NULL
            )',
            'CREATE INDEX login_attempts_lookup ON login_attempts (email, ip_hash, attempted_at)',
            'CREATE INDEX activity_events_user_time ON activity_events (user_id, created_at)',
        ];
    } elseif ($driver === 'mysql') {
        $statements = [
            'CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                terms_accepted_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE memberships (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL UNIQUE,
                plan_key VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT memberships_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE user_preferences (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                preferences_json JSON NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT preferences_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE dashboard_layouts (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                layout_json JSON NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT dashboard_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE ai_budgets (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                budget_cents BIGINT UNSIGNED NOT NULL,
                spent_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
                period_starts_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT budgets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE storage_accounts (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                storage_key VARCHAR(64) NOT NULL UNIQUE,
                quota_bytes BIGINT UNSIGNED NOT NULL,
                used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT storage_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE storage_folders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                folder_name VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY storage_folder_user_name (user_id, folder_name),
                CONSTRAINT folders_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE activity_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX activity_events_user_time (user_id, created_at),
                CONSTRAINT activity_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                ip_hash CHAR(64) NOT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX login_attempts_lookup (email, ip_hash, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    } elseif ($driver === 'pgsql') {
        $statements = [
            'CREATE TABLE users (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                user_id CHAR(9) COLLATE "C" NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                terms_accepted_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE memberships (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                plan_key VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE user_preferences (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                preferences_json JSONB NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE dashboard_layouts (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                layout_json JSONB NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE ai_budgets (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                budget_cents BIGINT NOT NULL,
                spent_cents BIGINT NOT NULL DEFAULT 0,
                period_starts_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE storage_accounts (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                storage_key VARCHAR(64) NOT NULL UNIQUE,
                quota_bytes BIGINT NOT NULL,
                used_bytes BIGINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE storage_folders (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                folder_name VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                UNIQUE (user_id, folder_name)
            )',
            'CREATE TABLE activity_events (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                event_type VARCHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE login_attempts (
                id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                ip_hash CHAR(64) NOT NULL,
                attempted_at TIMESTAMP NOT NULL
            )',
            'CREATE INDEX login_attempts_lookup ON login_attempts (email, ip_hash, attempted_at)',
            'CREATE INDEX activity_events_user_time ON activity_events (user_id, created_at)',
        ];
    } else {
        throw new RuntimeException('Unsupported migration database driver.');
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
};
