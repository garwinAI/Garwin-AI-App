<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $statement = 'CREATE TABLE appzone_checkout_sessions (
            purchase_id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            app_id INTEGER NOT NULL REFERENCES appzone_items(id) ON DELETE CASCADE,
            stripe_checkout_session_id TEXT UNIQUE,
            checkout_url TEXT,
            expires_at INTEGER,
            amount_cents INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT NOT NULL,
            paid_at TEXT
        )';
    } elseif ($driver === 'mysql') {
        $statement = 'CREATE TABLE appzone_checkout_sessions (
            purchase_id VARCHAR(64) PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            app_id BIGINT UNSIGNED NOT NULL,
            stripe_checkout_session_id VARCHAR(255) NULL UNIQUE,
            checkout_url VARCHAR(2048) NULL,
            expires_at BIGINT NULL,
            amount_cents BIGINT UNSIGNED NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'pending\',
            created_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            CONSTRAINT appzone_checkout_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT appzone_checkout_app_fk FOREIGN KEY (app_id) REFERENCES appzone_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    } elseif ($driver === 'pgsql') {
        $statement = 'CREATE TABLE appzone_checkout_sessions (
            purchase_id VARCHAR(64) PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            app_id BIGINT NOT NULL REFERENCES appzone_items(id) ON DELETE CASCADE,
            stripe_checkout_session_id VARCHAR(255) UNIQUE,
            checkout_url VARCHAR(2048),
            expires_at BIGINT,
            amount_cents BIGINT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'pending\',
            created_at TIMESTAMP NOT NULL,
            paid_at TIMESTAMP
        )';
    } else {
        throw new RuntimeException('The configured database driver does not support AppZone checkout.');
    }

    $pdo->exec($statement);
    $pdo->exec(
        'CREATE INDEX appzone_checkout_user_item ON appzone_checkout_sessions (user_id, app_id, status)'
    );
};
