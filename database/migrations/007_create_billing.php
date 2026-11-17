<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $statements = [
            'ALTER TABLE memberships ADD COLUMN stripe_customer_id TEXT',
            'ALTER TABLE memberships ADD COLUMN stripe_subscription_id TEXT',
            'ALTER TABLE memberships ADD COLUMN current_period_end TEXT',
            'ALTER TABLE memberships ADD COLUMN cancel_at_period_end INTEGER NOT NULL DEFAULT 0',
            'ALTER TABLE memberships ADD COLUMN stripe_event_created INTEGER',
            'CREATE UNIQUE INDEX memberships_stripe_customer ON memberships (stripe_customer_id)',
            'CREATE UNIQUE INDEX memberships_stripe_subscription ON memberships (stripe_subscription_id)',
            'CREATE TABLE membership_plans (
                plan_key TEXT PRIMARY KEY,
                display_name TEXT NOT NULL,
                price_cents INTEGER NOT NULL,
                billing_interval TEXT NOT NULL,
                storage_quota_bytes INTEGER NOT NULL,
                ai_budget_cents INTEGER NOT NULL,
                team_seats INTEGER NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE stripe_webhook_events (
                stripe_event_id TEXT PRIMARY KEY,
                event_type TEXT NOT NULL,
                event_created INTEGER NOT NULL,
                received_at TEXT NOT NULL
            )',
        ];
    } elseif ($driver === 'mysql') {
        $statements = [
            'ALTER TABLE memberships ADD COLUMN stripe_customer_id VARCHAR(255) NULL',
            'ALTER TABLE memberships ADD COLUMN stripe_subscription_id VARCHAR(255) NULL',
            'ALTER TABLE memberships ADD COLUMN current_period_end DATETIME NULL',
            'ALTER TABLE memberships ADD COLUMN cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE memberships ADD COLUMN stripe_event_created BIGINT NULL',
            'CREATE UNIQUE INDEX memberships_stripe_customer ON memberships (stripe_customer_id)',
            'CREATE UNIQUE INDEX memberships_stripe_subscription ON memberships (stripe_subscription_id)',
            'CREATE TABLE membership_plans (
                plan_key VARCHAR(32) PRIMARY KEY,
                display_name VARCHAR(80) NOT NULL,
                price_cents BIGINT UNSIGNED NOT NULL,
                billing_interval VARCHAR(16) NOT NULL,
                storage_quota_bytes BIGINT UNSIGNED NOT NULL,
                ai_budget_cents BIGINT UNSIGNED NOT NULL,
                team_seats INT UNSIGNED NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE stripe_webhook_events (
                stripe_event_id VARCHAR(255) PRIMARY KEY,
                event_type VARCHAR(128) NOT NULL,
                event_created BIGINT NOT NULL,
                received_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    } elseif ($driver === 'pgsql') {
        $statements = [
            'ALTER TABLE memberships ADD COLUMN stripe_customer_id VARCHAR(255)',
            'ALTER TABLE memberships ADD COLUMN stripe_subscription_id VARCHAR(255)',
            'ALTER TABLE memberships ADD COLUMN current_period_end TIMESTAMP',
            'ALTER TABLE memberships ADD COLUMN cancel_at_period_end SMALLINT NOT NULL DEFAULT 0',
            'ALTER TABLE memberships ADD COLUMN stripe_event_created BIGINT',
            'CREATE UNIQUE INDEX memberships_stripe_customer ON memberships (stripe_customer_id)',
            'CREATE UNIQUE INDEX memberships_stripe_subscription ON memberships (stripe_subscription_id)',
            'CREATE TABLE membership_plans (
                plan_key VARCHAR(32) PRIMARY KEY,
                display_name VARCHAR(80) NOT NULL,
                price_cents BIGINT NOT NULL,
                billing_interval VARCHAR(16) NOT NULL,
                storage_quota_bytes BIGINT NOT NULL,
                ai_budget_cents BIGINT NOT NULL,
                team_seats INTEGER NOT NULL,
                enabled SMALLINT NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NOT NULL
            )',
            'CREATE TABLE stripe_webhook_events (
                stripe_event_id VARCHAR(255) PRIMARY KEY,
                event_type VARCHAR(128) NOT NULL,
                event_created BIGINT NOT NULL,
                received_at TIMESTAMP NOT NULL
            )',
        ];
    } else {
        throw new RuntimeException('The configured database driver does not support billing.');
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO membership_plans
         (plan_key, display_name, price_cents, billing_interval, storage_quota_bytes, ai_budget_cents, team_seats, enabled, updated_at)
         VALUES (:plan_key, :display_name, :price_cents, :billing_interval, :storage_quota_bytes, :ai_budget_cents, :team_seats, :enabled, :updated_at)'
    );
    foreach ([
        ['free', 'Free', 0, 'none', 1073741824, 300, 0],
        ['pro', 'Pro', 4999, 'month', 26843545600, 800, 3],
    ] as [$key, $name, $price, $interval, $quota, $budget, $seats]) {
        $insert->execute([
            'plan_key' => $key,
            'display_name' => $name,
            'price_cents' => $price,
            'billing_interval' => $interval,
            'storage_quota_bytes' => $quota,
            'ai_budget_cents' => $budget,
            'team_seats' => $seats,
            'enabled' => 1,
            'updated_at' => $now,
        ]);
    }
};
