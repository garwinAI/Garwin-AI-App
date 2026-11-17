<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DemoUserService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RegistrationService $registration,
        private readonly StorageProvisioner $storage
    ) {
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_demo = 1')->fetchColumn();
    }

    /** @return list<array{id: int, email: string, first_name: string, last_name: string, created_at: string}> */
    public function list(): array
    {
        return $this->pdo->query(
            'SELECT id, email, first_name, last_name, created_at
             FROM users WHERE is_demo = 1 ORDER BY created_at DESC, id DESC LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $amount): int
    {
        if ($amount < 1 || $amount > 100) {
            throw new \InvalidArgumentException('Choose between 1 and 100 demo users.');
        }

        $created = 0;
        for ($index = 0; $index < $amount; $index++) {
            $user = null;
            try {
                $suffix = bin2hex(random_bytes(8));
                $user = $this->registration->register(
                    "demo.{$suffix}@example.invalid",
                    bin2hex(random_bytes(32)),
                    'Demo',
                    'User ' . str_pad((string) ($created + 1), 3, '0', STR_PAD_LEFT),
                    true
                );
                $markDemo = $this->pdo->prepare('UPDATE users SET is_demo = 1 WHERE id = :id');
                $markDemo->execute(['id' => $user['id']]);
                $created++;
            } catch (\Throwable $exception) {
                if ($user !== null) {
                    try {
                        $this->storage->removeForUser('UCSD-' . $user['user_id'] . '-001');
                        $delete = $this->pdo->prepare('DELETE FROM users WHERE id = :id AND is_demo = 0');
                        $delete->execute(['id' => $user['id']]);
                    } catch (\Throwable $cleanupException) {
                        throw new RuntimeException(
                            "Demo-user creation stopped after {$created} accounts and cleanup failed.",
                            0,
                            $cleanupException
                        );
                    }
                }

                throw new RuntimeException(
                    "Demo-user creation stopped after {$created} accounts.",
                    0,
                    $exception
                );
            }
        }

        return $created;
    }

    public function removeAll(): int
    {
        $rows = $this->pdo->query(
            'SELECT u.id, u.user_id, s.storage_key
             FROM users u
             JOIN storage_accounts s ON s.user_id = u.id
             WHERE u.is_demo = 1'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $expectedStorageKey = 'UCSD-' . $row['user_id'] . '-001';
            if (!hash_equals($expectedStorageKey, (string) $row['storage_key'])) {
                throw new RuntimeException('Demo-user storage ownership validation failed; no accounts were removed.');
            }
        }
        foreach ($rows as $row) {
            $this->storage->removeForUser('UCSD-' . $row['user_id'] . '-001');
        }

        $delete = $this->pdo->prepare('DELETE FROM users WHERE is_demo = 1');
        $delete->execute();

        return $delete->rowCount();
    }
}
