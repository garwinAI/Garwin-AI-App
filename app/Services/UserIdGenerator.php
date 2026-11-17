<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class UserIdGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function generate(): string
    {
        $check = $this->pdo->prepare('SELECT 1 FROM users WHERE user_id = :user_id LIMIT 1');
        $alphabetLength = strlen(self::ALPHABET);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = '';
            for ($position = 0; $position < 9; $position++) {
                $candidate .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            $check->execute(['user_id' => $candidate]);
            if ($check->fetchColumn() === false) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique public user ID.');
    }
}
