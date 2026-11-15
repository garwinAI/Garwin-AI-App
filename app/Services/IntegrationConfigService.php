<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class IntegrationConfigService
{
    private const DEFINITIONS = [
        'stripe' => [
            'label' => 'Stripe',
            'fields' => [
                'secret_key' => ['label' => 'Secret key', 'type' => 'password'],
                'publishable_key' => ['label' => 'Publishable key', 'type' => 'text'],
                'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'password'],
            ],
        ],
        'resend' => [
            'label' => 'Resend',
            'fields' => [
                'api_key' => ['label' => 'API key', 'type' => 'password'],
                'from_email' => ['label' => 'Verified sender email', 'type' => 'email'],
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'fields' => [
                'api_key' => ['label' => 'API key', 'type' => 'password'],
            ],
        ],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ApiSecretCipher $cipher
    ) {
    }

    /** @return array<string, array{label: string, fields: array<string, array{label: string, type: string}>}> */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /** @return array<string, string> */
    public function credentials(string $provider): array
    {
        $this->definition($provider);
        $statement = $this->pdo->prepare(
            'SELECT credentials_json FROM integration_configs WHERE provider_key = :provider_key'
        );
        $statement->execute(['provider_key' => $provider]);
        $encoded = $statement->fetchColumn();

        if (!is_string($encoded)) {
            return [];
        }

        $encrypted = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($encrypted)) {
            throw new RuntimeException('Stored provider configuration is invalid.');
        }

        $credentials = [];
        foreach ($encrypted as $field => $value) {
            if (!is_string($field) || !is_string($value)) {
                throw new RuntimeException('Stored provider configuration is invalid.');
            }
            $credentials[$field] = $this->cipher->decrypt($value);
        }

        return $credentials;
    }

    public function save(string $provider, array $input): void
    {
        $definition = $this->definition($provider);
        $existing = $this->credentials($provider);
        $credentials = $existing;
        $changed = false;

        foreach ($definition['fields'] as $field => $metadata) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Invalid provider configuration value.');
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (strlen($value) > 4096) {
                throw new \InvalidArgumentException('Provider credentials are too long.');
            }
            if ($metadata['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Enter a valid sender email address.');
            }
            if (($existing[$field] ?? null) !== $value) {
                $changed = true;
            }
            $credentials[$field] = $value;
        }

        if ($credentials === []) {
            throw new \InvalidArgumentException('Enter provider credentials before saving.');
        }

        $encrypted = [];
        foreach ($credentials as $field => $value) {
            $encrypted[$field] = $this->cipher->encrypt($value);
        }
        $json = json_encode($encrypted, JSON_THROW_ON_ERROR);
        $now = gmdate('Y-m-d H:i:s');
        $exists = $this->hasProvider($provider);

        if ($exists) {
            $statement = $this->pdo->prepare(
                'UPDATE integration_configs
                 SET credentials_json = :credentials_json,
                     verified_at = :verified_at,
                     last_tested_at = :last_tested_at,
                     last_test_message = :last_test_message,
                     updated_at = :updated_at
                 WHERE provider_key = :provider_key'
            );
            $statement->execute([
                'credentials_json' => $json,
                'verified_at' => $changed ? null : $this->value($provider, 'verified_at'),
                'last_tested_at' => $changed ? null : $this->value($provider, 'last_tested_at'),
                'last_test_message' => $changed ? null : $this->value($provider, 'last_test_message'),
                'updated_at' => $now,
                'provider_key' => $provider,
            ]);

            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO integration_configs (provider_key, credentials_json, updated_at)
             VALUES (:provider_key, :credentials_json, :updated_at)'
        );
        $statement->execute([
            'provider_key' => $provider,
            'credentials_json' => $json,
            'updated_at' => $now,
        ]);
    }

    /** @return list<array{key: string, label: string, configured: bool, verified_at: ?string, last_tested_at: ?string, last_test_message: ?string, configured_fields: list<string>}> */
    public function statuses(): array
    {
        $statuses = [];
        foreach (self::DEFINITIONS as $provider => $definition) {
            $credentials = $this->credentials($provider);
            $row = $this->row($provider);
            $configuredFields = [];
            foreach ($definition['fields'] as $field => $_metadata) {
                if (isset($credentials[$field]) && $credentials[$field] !== '') {
                    $configuredFields[] = $field;
                }
            }

            $statuses[] = [
                'key' => $provider,
                'label' => $definition['label'],
                'configured' => count($configuredFields) === count($definition['fields']),
                'verified_at' => $row['verified_at'] ?? null,
                'last_tested_at' => $row['last_tested_at'] ?? null,
                'last_test_message' => $row['last_test_message'] ?? null,
                'configured_fields' => $configuredFields,
            ];
        }

        return $statuses;
    }

    public function recordTest(string $provider, bool $success, string $message): void
    {
        $this->definition($provider);
        $statement = $this->pdo->prepare(
            'UPDATE integration_configs
             SET verified_at = :verified_at,
                 last_tested_at = :last_tested_at,
                 last_test_message = :last_test_message,
                 updated_at = :updated_at
             WHERE provider_key = :provider_key'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'verified_at' => $success ? $now : null,
            'last_tested_at' => $now,
            'last_test_message' => mb_substr($message, 0, 255),
            'updated_at' => $now,
            'provider_key' => $provider,
        ]);
    }

    /** @return array<string, string> */
    public function requiredCredentials(string $provider): array
    {
        $definition = $this->definition($provider);
        $credentials = $this->credentials($provider);
        foreach ($definition['fields'] as $field => $metadata) {
            if (!isset($credentials[$field]) || $credentials[$field] === '') {
                throw new \InvalidArgumentException("Complete {$metadata['label']} before testing {$definition['label']}.");
            }
        }

        if ($provider === 'stripe') {
            $secretMode = [];
            $publishableMode = [];
            if (!preg_match('/\A(?:sk|rk)_(test|live)_[A-Za-z0-9_]+\z/', $credentials['secret_key'], $secretMode)
                || !preg_match('/\Apk_(test|live)_[A-Za-z0-9_]+\z/', $credentials['publishable_key'], $publishableMode)
                || !str_starts_with($credentials['webhook_secret'], 'whsec_')
                || $secretMode[1] !== $publishableMode[1]) {
                throw new \InvalidArgumentException(
                    'Use valid Stripe key formats, matching test/live modes, and a whsec_ webhook signing secret.'
                );
            }
        }

        return $credentials;
    }

    private function definition(string $provider): array
    {
        if (!isset(self::DEFINITIONS[$provider])) {
            throw new \InvalidArgumentException('Unknown provider.');
        }

        return self::DEFINITIONS[$provider];
    }

    private function hasProvider(string $provider): bool
    {
        return $this->row($provider) !== null;
    }

    private function row(string $provider): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT verified_at, last_tested_at, last_test_message
             FROM integration_configs WHERE provider_key = :provider_key'
        );
        $statement->execute(['provider_key' => $provider]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function value(string $provider, string $field): ?string
    {
        $row = $this->row($provider);
        $value = $row[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
