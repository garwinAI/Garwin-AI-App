<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthenticationService
{
    private const MAX_ATTEMPTS = 10;
    private const WINDOW_MINUTES = 15;
    private static ?string $dummyHash = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{id: int, user_id: string, email: string, first_name: string, last_name: string, is_admin: int}|null */
    public function attempt(string $email, string $password, string $remoteAddress): ?array
    {
        $email = strtolower(trim($email));
        $ipHash = hash('sha256', $remoteAddress);
        $windowStart = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $prune = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :retention_cutoff');
        $prune->execute(['retention_cutoff' => gmdate('Y-m-d H:i:s', time() - 86400)]);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :email AND ip_hash = :ip_hash AND attempted_at >= :window_start'
        );
        $count->execute(['email' => $email, 'ip_hash' => $ipHash, 'window_start' => $windowStart]);
        $blocked = (int) $count->fetchColumn() >= self::MAX_ATTEMPTS;

        $findUser = $this->pdo->prepare(
            "SELECT u.id, u.user_id, u.email, u.password_hash, u.first_name, u.last_name,
                    CASE WHEN a.role_key = 'super_admin' THEN 1 ELSE 0 END AS is_admin
             FROM users u
             LEFT JOIN admin_users a ON a.user_id = u.id
             WHERE u.email = :email LIMIT 1"
        );
        $findUser->execute(['email' => $email]);
        $user = $findUser->fetch();
        $hash = $user === false ? self::dummyHash() : $user['password_hash'];
        $passwordIsValid = password_verify($password, $hash);

        if ($blocked || $user === false || !$passwordIsValid) {
            $insert = $this->pdo->prepare(
                'INSERT INTO login_attempts (email, ip_hash, attempted_at) VALUES (:email, :ip_hash, :attempted_at)'
            );
            $insert->execute([
                'email' => $email,
                'ip_hash' => $ipHash,
                'attempted_at' => gmdate('Y-m-d H:i:s'),
            ]);

            return null;
        }

        $clear = $this->pdo->prepare('DELETE FROM login_attempts WHERE email = :email AND ip_hash = :ip_hash');
        $clear->execute(['email' => $email, 'ip_hash' => $ipHash]);
        $activity = $this->pdo->prepare(
            'INSERT INTO activity_events (user_id, event_type, created_at) VALUES (:user_id, :event_type, :created_at)'
        );
        $activity->execute([
            'user_id' => $user['id'],
            'event_type' => 'account.login',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        unset($user['password_hash']);

        return $user;
    }

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $hash = password_hash(bin2hex(random_bytes(32)), $algorithm);
            if ($hash === false) {
                throw new \RuntimeException('Unable to initialize login protection.');
            }

            self::$dummyHash = $hash;
        }

        return self::$dummyHash;
    }
}
