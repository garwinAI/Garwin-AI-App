<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use RuntimeException;

final class ApiSecretCipher
{
    public function isConfigured(): bool
    {
        $configured = Env::get('APP_KEY');
        if ($configured === null || !str_starts_with($configured, 'base64:')) {
            return false;
        }

        $key = base64_decode(substr($configured, 7), true);

        return is_string($key) && strlen($key) === 32;
    }

    public function encrypt(string $value): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt provider credentials.');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $value): string
    {
        $payload = base64_decode($value, true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Stored provider credential is invalid.');
        }

        $nonce = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            ''
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt provider credentials. Check APP_KEY.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        $configured = Env::get('APP_KEY');
        if (!$this->isConfigured() || $configured === null) {
            throw new RuntimeException('Set a valid APP_KEY before saving provider credentials.');
        }

        $key = base64_decode(substr($configured, 7), true);
        if ($key === false) {
            throw new RuntimeException('APP_KEY is invalid.');
        }

        return $key;
    }
}
