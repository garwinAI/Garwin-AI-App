<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AdminBootstrapService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RegistrationService $registration
    ) {
    }

    public function bootstrap(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new \InvalidArgumentException('A valid admin email and password are required.');
        }

        $find = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $find->execute(['email' => $email]);
        $user = $find->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            $user = $this->registration->register($email, $password, 'Garwin', 'Admin', true);
        } elseif (!password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('The existing account password does not match the bootstrap configuration.');
        }

        $existingAdmin = $this->pdo->prepare('SELECT 1 FROM admin_users WHERE user_id = :user_id');
        $existingAdmin->execute(['user_id' => $user['id']]);
        if ($existingAdmin->fetchColumn() !== false) {
            return false;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO admin_users (user_id, role_key, created_at)
             VALUES (:user_id, :role_key, :created_at)'
        );
        $insert->execute([
            'user_id' => $user['id'],
            'role_key' => 'super_admin',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
