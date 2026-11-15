<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class RegistrationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly UserIdGenerator $userIds,
        private readonly StorageProvisioner $storage
    ) {
    }

    /** @return array{id: int, user_id: string, email: string, first_name: string, last_name: string} */
    public function register(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        bool $termsAccepted
    ): array {
        if (!$termsAccepted) {
            throw new \InvalidArgumentException('Terms acceptance is required.');
        }

        $email = strtolower(trim($email));
        $emailCheck = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $emailCheck->execute(['email' => $email]);
        if ($emailCheck->fetchColumn() !== false) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $userId = $this->userIds->generate();
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $passwordHash = password_hash($password, $algorithm);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to securely hash the password.');
        }

        $freePlan = (new MembershipPlanService($this->pdo))->get('free');
        $directoryName = $this->storage->createForUser($userId);
        $now = gmdate('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();
            $insertSql = 'INSERT INTO users (user_id, email, password_hash, first_name, last_name, terms_accepted_at, created_at, updated_at)
                 VALUES (:user_id, :email, :password_hash, :first_name, :last_name, :terms_accepted_at, :created_at, :updated_at)';
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $insertSql .= ' RETURNING id';
            }
            $insertUser = $this->pdo->prepare($insertSql);
            $insertUser->execute([
                'user_id' => $userId,
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => trim($firstName),
                'last_name' => trim($lastName),
                'terms_accepted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $internalId = (int) ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                ? $insertUser->fetchColumn()
                : $this->pdo->lastInsertId());

            $membership = $this->pdo->prepare(
                'INSERT INTO memberships (user_id, plan_key, status, created_at, updated_at)
                 VALUES (:user_id, :plan_key, :status, :created_at, :updated_at)'
            );
            $membership->execute([
                'user_id' => $internalId,
                'plan_key' => $freePlan['plan_key'],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $preferences = $this->pdo->prepare(
                'INSERT INTO user_preferences (user_id, preferences_json, created_at, updated_at)
                 VALUES (:user_id, :preferences_json, :created_at, :updated_at)'
            );
            $preferences->execute([
                'user_id' => $internalId,
                'preferences_json' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $dashboard = $this->pdo->prepare(
                'INSERT INTO dashboard_layouts (user_id, layout_json, created_at, updated_at)
                 VALUES (:user_id, :layout_json, :created_at, :updated_at)'
            );
            $dashboard->execute([
                'user_id' => $internalId,
                'layout_json' => '{"widgets":[]}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $budget = $this->pdo->prepare(
                'INSERT INTO ai_budgets (user_id, budget_cents, spent_cents, period_starts_at, created_at, updated_at)
                 VALUES (:user_id, :budget_cents, :spent_cents, :period_starts_at, :created_at, :updated_at)'
            );
            $budget->execute([
                'user_id' => $internalId,
                'budget_cents' => $freePlan['ai_budget_cents'],
                'spent_cents' => 0,
                'period_starts_at' => gmdate('Y-m-01 00:00:00'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $storageAccount = $this->pdo->prepare(
                'INSERT INTO storage_accounts (user_id, storage_key, quota_bytes, used_bytes, created_at, updated_at)
                 VALUES (:user_id, :storage_key, :quota_bytes, :used_bytes, :created_at, :updated_at)'
            );
            $storageAccount->execute([
                'user_id' => $internalId,
                'storage_key' => $directoryName,
                'quota_bytes' => $freePlan['storage_quota_bytes'],
                'used_bytes' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $folder = $this->pdo->prepare(
                'INSERT INTO storage_folders (user_id, folder_name, created_at) VALUES (:user_id, :folder_name, :created_at)'
            );
            foreach (StorageProvisioner::DEFAULT_FOLDERS as $name) {
                $folder->execute([
                    'user_id' => $internalId,
                    'folder_name' => $name,
                    'created_at' => $now,
                ]);
            }

            $activity = $this->pdo->prepare(
                'INSERT INTO activity_events (user_id, event_type, created_at) VALUES (:user_id, :event_type, :created_at)'
            );
            $activity->execute([
                'user_id' => $internalId,
                'event_type' => 'account.registered',
                'created_at' => $now,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            try {
                $this->storage->removeForUser($directoryName);
            } catch (\Throwable $cleanupException) {
                throw new RuntimeException('Registration failed and private storage cleanup also failed.', 0, $cleanupException);
            }

            throw $exception;
        }

        return [
            'id' => $internalId,
            'user_id' => $userId,
            'email' => $email,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
        ];
    }
}
